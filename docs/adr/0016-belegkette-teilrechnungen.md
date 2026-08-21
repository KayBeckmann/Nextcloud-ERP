# ADR-0016: Belegkette Angebot → Auftrag → Lieferschein/Rechnung, Teilrechnungen

**Status:** accepted
**Datum:** 2026-08-21

## Kontext

Nutzerwunsch nach den Anpassungen aus ADR-0015 (Projektpflicht, Lieferscheine,
Picker):

1. Die Kundenvorbelegung aus dem Projekt (bisher nur im Angebot, siehe
   ADR-0015-Nachtrag) soll für **alle** projektgebundenen Anlage-Formulare
   gelten: Angebote, Aufträge, Rechnungen, Lieferscheine.
2. Angebote sollen sich in Aufträge wandeln lassen.
3. Aufträge sollen sich in Lieferscheine wandeln lassen — **nur Artikel und
   Produkte, keine Arbeitsstunden**.
4. Aufträge und Lieferscheine sollen sich in Rechnungen wandeln lassen.
5. Teilrechnungen sollen möglich sein — entweder durch Positionsauswahl
   (inkl. Teilmengen) oder als Materialabschlag (Pauschalbetrag ohne
   Positionsbezug).
6. Die Schlussrechnung muss am Ende die bereits gestellten Teilrechnungen und
   die darauf erfassten Teilzahlungen auflisten.

Bisher waren Aufträge (`erp_orders`) reine Titel/Status/Beschreibung-Objekte
ohne Positionen — die einzige vorhandene Umwandlung war Angebot → Rechnung
(`InvoiceService::createFromQuote()`). ADR-0013 hatte "Proratierungs-/
Verrechnungslogik zwischen mehreren Teilrechnungen eines Auftrags" bewusst
als eigenes, größeres Feature zurückgestellt — das ist jetzt genau der
angefragte Umfang.

## Entscheidung

### Aufträge bekommen Positionen

`erp_order_positions` — identisches Schema wie `erp_quote_positions`
(ADR-0011), aber flach ohne Gruppen (Aufträge sind Ausführungseinheiten,
keine gegliederte Angebotsdarstellung): `order_id`, `position_type`
(`article`/`product`/`labor`/`custom`), `reference_id`, `description`,
`quantity`, `unit`, `unit_price_net`, `vat_rate_percent`, `position_order`.
Wie bei Angebotspositionen bleiben Auftragspositionen jederzeit änderbar
(kein Entwurf-Zwang wie bei Rechnungen) — Aufträge kennen kein
GoBD-relevantes "Ausstellen".

`erp_orders` bekommt zusätzlich `customer_contact_uid` (nullable) und
`quote_id` (nullable) — ein Auftrag kann aus einem Angebot entstehen.

### Angebot → Auftrag

`OrderService::createFromQuote(int $quoteId)` kopiert Titel, Kunde und alle
Angebotspositionen 1:1 in einen neuen Auftrag (Snapshot-Prinzip, wie
`InvoiceService::createFromQuote()`). Keine Live-Referenz danach — spätere
Änderungen am Angebot wirken sich nicht auf den Auftrag aus.

### Rückverfolgung für Teilkonvertierungen

`erp_invoice_positions` und `erp_delivery_note_positions` bekommen
`order_position_id` (nullable) — verweist auf die Auftragsposition, aus der
eine Rechnungs- bzw. Lieferscheinposition entstanden ist. Damit lässt sich
zur Laufzeit berechnen, wie viel von einer Auftragsposition bereits berechnet
bzw. geliefert wurde (`InvoicePositionMapper::sumQuantityForOrderPosition()`,
`DeliveryNotePositionMapper::sumQuantityForOrderPosition()`), ohne einen
mutierbaren Zählerstand pflegen zu müssen — analog zu `isOverdue()` in
`InvoiceService`, das den Status ebenfalls zur Laufzeit aus vorhandenen
Daten ableitet statt ihn zu speichern.

Diese "bereits verplante Menge" ist **informativ**, kein hartes Limit mit
Locking — bei gleichzeitiger Bearbeitung durch zwei Nutzer ist ein
Überbuchen theoretisch möglich (siehe "Nicht Teil dieser Phase").

### Auftrag → Lieferschein: nur Artikel und Produkte

`DeliveryNoteService::createFromOrder(int $orderId, array $positions, ?string $notes)`
übernimmt ausschließlich Auftragspositionen mit `position_type` `article`
oder `product` — **`labor` wird nicht übernommen** (expliziter
Nutzerwunsch: "keine Zeiten" auf dem Lieferschein), `custom` ebenfalls
nicht, da Lieferscheine reine Warenbewegung dokumentieren (ADR-0015). Der
Aufrufer wählt Positionen und Mengen (`{orderPositionId, quantity}`);
`quantity` darf die noch nicht gelieferte Restmenge nicht überschreiten
(Validierung, kein Locking).

### Auftrag/Lieferschein → Rechnung

`InvoiceService::createFromOrder(int $orderId, ..., array $positions)` —
wie bei `createFromQuote()`, aber mit Positionsauswahl statt "alles
übernehmen", damit Teilrechnungen durch Positionsauswahl möglich sind.
Jede erzeugte `InvoicePosition` bekommt `order_position_id` gesetzt.

`InvoiceService::createFromDeliveryNote(int $deliveryNoteId, ..., array $positions)`
— Lieferscheinpositionen haben bewusst keine Preise (ADR-0015). Ist die
gewählte Lieferscheinposition mit einer Auftragsposition verknüpft
(`order_position_id`), wird deren Preis/MwSt.-Satz übernommen. Ohne
Verknüpfung (z. B. eine frei angelegte Lieferscheinposition ohne Auftrag)
muss der Aufrufer `unitPriceNet`/`vatRatePercent` explizit mitschicken,
sonst wirft der Service `\InvalidArgumentException`.

### Teilrechnung = Rechnung mit `type='partial'`

Kein neues Datenmodell nötig — `type` kennt `partial`/`final` bereits
(ADR-0013, bisher nur als Kennzeichnung ohne Fachlogik). Zwei Wege zur
Teilrechnung:

1. **Positionsauswahl**: `createFromOrder()`/`createFromDeliveryNote()` mit
   `type='partial'` und einer Teilmenge der Positionen (ggf. mit
   Teilmengen einzelner Positionen).
2. **Materialabschlag**: eine leere Rechnung mit `type='partial'` und
   `order_id` gesetzt (`invoice#create`, bereits vorhanden), auf die über
   den bestehenden `addPosition()`-Weg ein einzelner Freitext-Posten
   (`position_type='custom'`, kein `order_position_id`, frei wählbarer
   Betrag) gesetzt wird — bewusst kein Bezug zu einer Auftragsposition,
   da ein Abschlag i. d. R. ein Pauschalbetrag ist, kein Teil einer
   bestimmten Position.

### Schlussrechnung listet Teilrechnungen und Teilzahlungen

`InvoiceService::getFullInvoice()` liefert zusätzlich `relatedInvoices` —
alle anderen Rechnungen mit demselben `order_id` (Nummer, Typ, Status,
Bruttosumme, `paidAmount`), sortiert nach `created_at`. Das Feld wird
immer mitgeliefert, sobald `order_id` gesetzt ist (nicht nur bei
`type='final'`) — so sieht man den Belegzusammenhang auch bei einer
einzelnen Teilrechnung. Das Frontend rendert diese Liste als eigenen
Abschnitt **am Ende** der Rechnungsansicht (Nutzeranforderung), unterhalb
des normalen Netto-/MwSt.-Abschlussblocks.

Bewusst **keine automatische Verrechnung/Subtraktion** der
Teilrechnungsbeträge von der Schlussrechnungssumme — reine Auflistung zur
Transparenz. Eine rechtlich korrekte Abschlagsrechnungs-Verrechnung nach
§ 14 UStG (anteilige Netto-/MwSt.-Verrechnung je Steuersatz, gesonderter
Ausweis bereits versteuerter Beträge) ist ein eigenes, rechtlich zu
prüfendes Feature (siehe "Nicht Teil dieser Phase", identische Begründung
wie die zurückgestellte Pflichtangaben-Prüfung in ADR-0013).

### Kundenvorbelegung für alle projektgebundenen Anlage-Formulare

`AuftraegeView`/`RechnungenView` bekommen wie `AngeboteView` (2026-08-21)
ein `customerContactUid`-Prop, das `ProjektDetailView` aus
`project.customerContactUid` durchreicht und das beim Öffnen des
jeweiligen Anlage-Formulars als Vorbelegung übernommen wird (änderbar,
kein Zwang).

> **Korrektur beim Umsetzen:** `LieferscheineView` bekommt **kein**
> `customerContactUid`-Prop — Lieferscheine haben bewusst kein eigenes
> Kundenfeld (ADR-0015: "dokumentieren nur die gelieferte Menge, keinen
> Wert"), das Anlage-Formular fragt nur eine Notiz ab. Es gibt dort nichts
> vorzubelegen; den Kunden trägt das Projekt bzw. der verknüpfte Auftrag.

### Aufträge bekommen eine eigene Detailseite

Bisher war der Aufträge-Tab in `ProjektDetailView` eine reine Inline-Liste
ohne Positionen. Analog zu `AngebotDetailView`/`RechnungDetailView` entsteht
`AuftragDetailView.vue` mit Positionsverwaltung, Kunde-Picker und den
Umwandlungs-Buttons ("In Lieferschein wandeln", "In Rechnung wandeln").
`AuftraegeView.vue` (Liste + Anlegen) ersetzt die bisherige Inline-Logik im
Aufträge-Tab, analog zu `AngeboteView.vue`.

### Rechte

Aufträge bleiben bei `ResourceType::Auftraege` (bestehend). Die neuen
Umwandlungs-Endpunkte prüfen jeweils Schreibrecht auf **beiden** beteiligten
Ressourcen (z. B. `invoice#createFromOrder` braucht Lesen auf Aufträge und
Schreiben auf Rechnungen) — dieselbe Logik wie bereits bei
`invoice#createFromQuote` (implizit: nur Rechnungen-Schreibrecht geprüft,
Angebot wird nur gelesen). Wir übernehmen das unverändert: die Zielressource
(Lieferschein/Rechnung) bestimmt das benötigte Schreibrecht, die
Quellressource (Angebot/Auftrag/Lieferschein) muss nur lesbar sein.

## Nicht Teil dieser Phase

- **Keine automatische Verrechnung/Subtraktion** von Teilrechnungsbeträgen in
  der Schlussrechnung — reine Auflistung, siehe oben. Echte
  Abschlagsrechnungs-Arithmetik nach § 14 UStG ist ein eigenes, rechtlich zu
  prüfendes Feature.
- **Kein Locking gegen doppeltes Verplanen von Mengen** bei gleichzeitiger
  Bearbeitung — die "bereits berechnete/gelieferte Menge"-Anzeige ist
  informativ, keine DB-Constraint.
- **Keine UI-Optimierung für Teillieferungen** (Auftrag → mehrere
  Lieferscheine mit Teilmengen) — technisch durch die Positionsauswahl
  möglich, aber nicht gesondert vereinfacht (z. B. kein automatisches
  "Restmenge vorschlagen").
- **Rechnung → Auftrag zurück** (Stornoweg) bleibt ausschließlich über
  Gutschriften (ADR-0013) — keine neue Rückwandlung.
- **Auftragspositionen ohne Entwurfs-Zwang** — anders als bei Rechnungen
  gibt es keinen "Auftrag ausstellen"-Schritt, der Positionen einfriert.
  Das ist eine bewusste Vereinfachung (siehe "Alternativen erwogen").

## Konsequenzen

- Die vollständige Kette Angebot → Auftrag → Lieferschein/Rechnung ist ohne
  manuelles Neuabtippen von Positionen möglich.
- Teilrechnungen sind über den bestehenden Rechnungs-Mechanismus (Entwurf →
  Positionen → Ausstellen) abgebildet, kein Parallelmodell.
- Der Beleg-Zusammenhang (welche Teilrechnungen/Zahlungen gehören zu welchem
  Auftrag) ist jederzeit nachvollziehbar über `relatedInvoices`, auch ohne
  dass eine "Schlussrechnung" formal existiert.

## Alternativen erwogen

- **Auftragspositionen mit eigenem Entwurf/Ausstellen-Zyklus** (wie
  Rechnungen): hätte Konsistenz mit dem Rechnungs-Modell geboten, aber für
  Aufträge gibt es kein GoBD-Erfordernis für Unveränderlichkeit — unnötige
  Komplexität für den aktuellen Umfang.
- **Mutierbarer `invoiced_quantity`-Zählerstand direkt auf der
  Auftragsposition** statt Laufzeitberechnung: schneller lesbar, aber
  fehleranfällig bei nachträglichem Löschen einer Rechnungsposition (Zähler
  müsste konsistent mitgepflegt werden) — die Laufzeitberechnung ist
  einfacher korrekt zu halten und folgt demselben Muster wie `isOverdue()`.
- **Materialabschlag als eigenes Datenfeld** (`deposit_amount` auf der
  Rechnung) statt normaler Freitext-Position: hätte eine Sonderbehandlung in
  der Netto-/MwSt.-Berechnung erfordert; als normale Position fügt er sich
  ohne Zusatzlogik in `QuoteCalculationService::calculate()` ein.
- **Automatische Verrechnung der Teilrechnungen in der Schlussrechnung**:
  fachlich naheliegend, aber die korrekte Verrechnung ist steuerrechtlich
  nicht trivial (unterschiedliche MwSt.-Sätze je Teilrechnung, bereits
  ausgewiesene vs. noch offene Beträge) — zurückgestellt, siehe "Nicht Teil
  dieser Phase".
