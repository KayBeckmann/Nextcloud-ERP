# ADR-0013: Rechnungen, Gutschriften und Zahlungsstatus

**Status:** accepted
**Datum:** 2026-08-20

> **Hinweis (2026-08-21):** `project_id` ist für Rechnungen seit
> [ADR-0015](0015-projektpflicht-lieferscheine-picker.md) Pflicht (`NOT
> NULL`), Gutschriften bekommen dort zusätzlich eine eigene
> `project_id`-Spalte. Der Rest dieses ADRs (Nummernvergabe, Snapshot-
> Prinzip, Storno-Konzept) gilt unverändert.

## Kontext

Roadmap Phase 7 verlangt abrechenbare Dokumente aus Angebot/Auftrag:
Rechnungsentwürfe, Positionen aus Material/Stunden/Angebot,
Abschlags-/Teil-/Schlussrechnungen, ein Gutschriften-/Storno-Konzept,
manipulationsarme Rechnungsnummernlogik, Rechnungs-PDF im Projektordner und
Zahlungsstatus. Die Prüfkriterien fordern außerdem, dass rechtliche offene
Punkte dokumentiert werden, bevor produktiv abgerechnet wird — dieses ADR
übernimmt diese Dokumentation explizit im Abschnitt "Nicht Teil dieser
Phase".

## Entscheidung

### Rechnungen aus Angebot oder direkt

`erp_invoices` (invoice_number nullable — siehe Nummernlogik unten, type:
`invoice`/`partial`/`final`, status: `draft`/`issued`/`partially_paid`/
`paid`/`cancelled`, project_id nullable, order_id nullable, quote_id
nullable — Quelle bei Übernahme, customer_contact_uid, title, issued_at
nullable, due_date, paid_amount, notes, document_file_id nullable,
created_at/updated_at).

`erp_invoice_positions` (invoice_id, position_type: `article`/`product`/
`labor`/`custom`, reference_id nullable, description, quantity, unit,
unit_price_net, vat_rate_percent, position_order) — identisches Schema wie
`erp_quote_positions` (ADR-0011), bewusst dieselbe Snapshot-Logik: Preise
werden beim Hinzufügen einmalig übernommen, keine Live-Referenz.

Eine Rechnung kann aus einem angenommenen Angebot erzeugt werden
(`InvoiceService::createFromQuote()` kopiert dessen Positionen 1:1 in die
Rechnung) oder direkt manuell befüllt werden (z. B. für Fälle ohne
vorheriges Angebot). `type` deckt Abschlags-/Teil-/Schlussrechnungen als
reine Kennzeichnung ab — die Roadmap fordert ausdrücklich nur, sie
"vorzubereiten oder einzuführen", keine Proratierungs-/Verrechnungslogik
zwischen mehreren Teilrechnungen eines Auftrags (das wäre ein eigenes,
größeres Feature).

**Netto-/MwSt.-Berechnung wird wiederverwendet:** Da Rechnungspositionen
dasselbe Schema wie Angebotspositionen haben (keine Gruppen nötig, da
Rechnungen flach sind), ruft `InvoiceService` direkt
`QuoteCalculationService::calculate([], $positions)` aus ADR-0011 auf,
statt eine near-identische Kopie der Berechnung zu pflegen.

### Positionen sind nur im Entwurf änderbar

`addPosition()`/`removePosition()` werfen `\DomainException`, sobald
`status !== 'draft'` — sobald eine Rechnung ausgestellt ist, sind ihre
Positionen unveränderlich (GoBD-Grundgedanke: eine ausgestellte Rechnung
wird nicht nachträglich editiert, sondern über eine Gutschrift korrigiert).

### Rechnungsnummernlogik: Nummer erst beim Ausstellen, nicht beim Anlegen

Anders als bei Angebotsnummern (ADR-0011: `A-%05d` direkt aus der
Entity-ID, weil Entwürfe dort unproblematisch sind) darf eine
Rechnungsnummer **keine Lücken durch verworfene Entwürfe** haben
("manipulationsarme Nummernlogik", Roadmap-Prüfkriterium). Deshalb bleibt
`invoice_number` bei `draft` `null` und wird erst bei `issue()` über einen
dedizierten Zähler vergeben: `erp_invoice_counters` (year, kind
[`invoice`/`credit_note`], next_sequence, UNIQUE(year, kind)). Format:
`R-{Jahr}-{Sequenz:05d}` (Rechnungen), `G-{Jahr}-{Sequenz:05d}`
(Gutschriften) — Jahresbezug ist in Deutschland üblich und erleichtert die
Archivzuordnung. Die Sequenzvergabe läuft in einer DB-Transaktion
(`IDBConnection::beginTransaction()`/`commit()`) um Race Conditions bei
gleichzeitigem Ausstellen zu vermeiden. Bewusst kein Entity/Mapper für
diese Zählertabelle — der atomare Read-Increment-Write-Zyklus ist eine
reine Zähloperation, kein abfragbares Fachobjekt.

### Zahlungsstatus als einfacher Betragsabgleich

`paid_amount` wird über `recordPayment(amount)` erhöht;
`status` wechselt automatisch zwischen `issued` → `partially_paid` → `paid`
je nachdem, ob `paid_amount` die Bruttosumme erreicht. Kein eigenes
Zahlungs-/Buchungsjournal mit Einzelbuchungen — für den aktuellen
MVP-Umfang reicht ein laufender Betrag; ein Journal wäre ein eigenes
späteres Feature (z. B. für Teilzahlungen mit Datum/Referenz je Zahlung).

### Gutschriften als einziger Korrekturweg für ausgestellte Rechnungen

`erp_credit_notes` (credit_note_number nullable — dieselbe
Erst-beim-Ausstellen-Logik wie bei Rechnungen, invoice_id, status:
`draft`/`issued`, reason, cancels_invoice, issued_at, created_at/updated_at)
+ `erp_credit_note_positions` (credit_note_id, description, quantity, unit,
unit_price_net, vat_rate_percent, position_order — bewusst ohne
`position_type`/`reference_id`, da Korrekturzeilen i. d. R. Freitext-Beträge
sind, kein Bezug auf Artikel/Produkt).

Zwei Wege, eine Gutschrift anzulegen:

1. **Vollstorno** (`CreditNoteService::createFullCancellation()`): kopiert
   alle Positionen der Rechnung unverändert, setzt `cancels_invoice = true`.
   Bei `issue()` wird zusätzlich die referenzierte Rechnung auf
   `status = 'cancelled'` gesetzt.
2. **Teilkorrektur** (`CreditNoteService::createPartial()`): freie
   Positionsliste, `cancels_invoice = false` — ändert den Rechnungsstatus
   nicht, dient nur der buchhalterischen Korrektur eines Teilbetrags.

Eine Rechnung wird nie gelöscht oder direkt auf `cancelled` gesetzt, ohne
dass eine Gutschrift existiert — das erzwingt den in der Roadmap geforderten
nachvollziehbaren Storno-Weg.

### Rechnungsdokument im Projektordner — bewusst kein PDF-Binärexport

`ErpFolderService::ensureInvoiceFolder()` legt
`ERP/Projekte/<Projektnummer>/Rechnungen` an. Beim Ausstellen einer
Rechnung mit `project_id` schreibt `InvoiceService` eine einfache,
druckfähige HTML-Repräsentation der Rechnung dorthin (Nextcloud-Files-API,
`Folder::newFile()`) und speichert die resultierende `fileId` als
`document_file_id`. Das erfüllt "Rechnungs-PDF im Projektordner" im Kern
(ein echtes, ansehbares/druckbares Dokument landet automatisch im richtigen
Ordner), ohne eine neue PDF-Bibliothek als Abhängigkeit einzuführen — der
Nutzer kann die HTML-Datei im Browser öffnen und über "Drucken → Als PDF
speichern" ein PDF erzeugen. Eine echte serverseitige PDF-Erzeugung
(dompdf/mPDF o. Ä.) ist bewusst zurückgestellt, siehe unten — identische
Begründung wie der Angebots-PDF-Export in ADR-0011. Ist kein `project_id`
gesetzt, wird kein Dokument erzeugt (optional, analog zum optionalen
Kalendertermin bei Abwesenheiten in ADR-0012) — das Ausstellen scheitert
dadurch nicht.

### Rechte

Rechnungen und Gutschriften teilen sich `ResourceType::Rechnungen` (bereits
im Rechte-Enum aus ADR-0008 vorgesehen).

## Nicht Teil dieser Phase (rechtliche offene Punkte, siehe Roadmap-Prüfkriterium)

- **Echter PDF-Export** (dompdf/mPDF/wkhtmltopdf) — neue Abhängigkeit, eigene
  Architekturentscheidung (Lizenz, Ressourcenverbrauch), siehe oben.
- **XRechnung/ZUGFeRD** (strukturierte E-Rechnungsformate) — ausdrücklich in
  der Roadmap als "später zu prüfen" markiert, nicht MVP.
- **Vollständige Pflichtangaben-Prüfung nach § 14 UStG** (fortlaufende
  Rechnungsnummer ✓, Steuernummer/USt-IdNr. des Ausstellers, Leistungsdatum
  getrennt vom Rechnungsdatum, Kleinunternehmerregelung-Hinweis) — die
  Datenfelder für Rechnungsnummer/Datum/Positionen/MwSt. sind vorhanden,
  Firmenstammdaten (eigene USt-IdNr., Anschrift) sind noch keine eigene
  Entität. Vor produktivem Einsatz muss ein Firmenstammdaten-Datensatz
  ergänzt und die HTML-Vorlage rechtlich geprüft werden.
- **Zahlungsjournal mit Einzelbuchungen** (Datum/Referenz je Teilzahlung,
  Mahnwesen) — aktuell nur ein laufender `paid_amount`-Betrag.
- **Export-Schnittstelle für Steuerberater** (DATEV o. Ä.) — Datenmodell ist
  vorbereitet (nachvollziehbare Netto-/MwSt.-Aufschlüsselung je Rechnung/
  Gutschrift), aber kein Exportformat implementiert.
- **Angebot → Rechnung automatisch bei Auftragsabschluss** — bleibt
  vorerst ein manueller Schritt (`createFromQuote()`), keine
  Auto-Erzeugung beim Ändern des Auftragsstatus.

## Konsequenzen

- Rechnungsnummern haben keine Lücken durch verworfene Entwürfe, da die
  Sequenz erst beim Ausstellen vergeben wird.
- Eine ausgestellte Rechnung ist unveränderlich; jede Korrektur läuft über
  eine nachvollziehbare Gutschrift mit eigener Nummer.
- Ohne echten PDF-Export ist das erzeugte Dokument eine HTML-Datei — für
  den internen Gebrauch und "Drucken als PDF" ausreichend, für einen
  produktiven Rechnungsversand an Kunden muss vor Aktivierung dieser
  Funktion die Pflichtangaben-Prüfung (siehe oben) nachgezogen werden.

## Alternativen erwogen

- Rechnungsnummer direkt aus der Entity-ID (wie bei Angeboten): einfacher,
  aber verworfene Entwürfe hätten Nummernlücken hinterlassen —
  inakzeptabel laut Roadmap-Prüfkriterium "manipulationsarm".
- Ausgestellte Rechnungen direkt editierbar mit Änderungshistorie
  (Audit-Log statt Gutschriftenzwang): näher an manchen kommerziellen
  ERP-Systemen, aber deutlich mehr Komplexität (Versionierung) für denselben
  buchhalterischen Effekt wie eine einfache Gutschrift.
- PDF-Erzeugung sofort mit einer Bibliothek wie dompdf: hätte eine neue
  Composer-Abhängigkeit und potenzielle Docker-Permission-Fallstricke
  (siehe bereits dokumentierte Composer-`-u root`-Notwendigkeit) in diese
  Phase gezogen, ohne dass es ein hartes Prüfkriterium der Roadmap ist.
