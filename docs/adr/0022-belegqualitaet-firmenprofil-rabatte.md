# ADR-0022: Belegqualität — Firmenprofil, Gruppen im PDF, Positionspflege, Rabatte

**Status:** accepted
**Datum:** 2026-08-25

## Kontext

Direktes Nutzerfeedback nach dem ersten eigenen Test der in Phase 12
(ADR-0021) gebauten PDF-Erzeugung: Das erzeugte Angebots-PDF ist "so
nicht zu gebrauchen", weil selbst die minimal erwarteten Angaben fehlen —
Firmenname, Kundendaten, Datum, Bindefrist. Das war kein Versehen,
sondern eine bewusste Lücke aus ADR-0021 ("Nicht Teil dieser Phase: …
vollständige §14 UStG-Konformität"), die sich in der Praxis als zu groß
herausgestellt hat: Phase 12 lieferte den technischen Mechanismus
(HTML→PDF, Ablage, Dateiname), aber keinen brauchbaren Inhalt.

Zusätzlich, im selben Feedback:

- Die bereits bestehenden Positionsgruppen (ADR "Nutzerwunsch
  2026-08-21", Web-UI-Anzeige seit dem Gruppen-Meilenstein) tauchen im
  PDF überhaupt nicht auf — die generierten Dokumente rendern Positionen
  als flache Liste, obwohl die Web-Ansicht sie längst gruppiert zeigt.
- Einmal angelegte Positionen sind nur lösch-, nicht editierbar — eine
  Mengen- oder Preiskorrektur bedeutet Löschen + Neuanlegen.
- Es gibt keinerlei Rabattkonzept — weder pro Position noch auf den
  gesamten Beleg.

## Entscheidung

### Firmenprofil (neu)

Neue Tabelle `erp_company_profile` mit genau einer Zeile (id=1,
Singleton). Felder: `name`, `address_line`, `postal_code`, `city`,
`country`, `tax_id` (USt-IdNr. oder Steuernummer), `email`, `phone`,
`footer_text` (mehrzeiliges Freitextfeld). Bewusst kein vollständiges
Impressum-Datenmodell (Handelsregister, Geschäftsführer,
Bankverbindung, Kleinunternehmerhinweis, …) — dafür steht
`footer_text` als freies Textfeld zur Verfügung, das Kay selbst befüllt.
Verwaltung analog zum bestehenden `VatRateService`-Muster unter
Einstellungen (`ResourceType::Einstellungen`-Gate), da es sich ebenso um
systemweite Stammdaten handelt.

### Kundendaten im PDF

`ContactsService` bekommt eine neue Methode, die zu einer Contact-UID
Anzeigename **und** Anschrift (aus dem `ADR`-Feld des vCard-Kontakts)
liefert. Bisher wurde nur der Anzeigename nachgeschlagen (für die
Web-Ansicht ausreichend); für ein Kunden-PDF wird die Anschrift nötig,
die Nextclouds Kontakte-App bereits pflegt — keine Datenduplikation ins
ERP-Schema.

### Datum und Bindefrist

Belegdatum: `createdAt` des jeweiligen Belegs (bereits vorhanden).
Bindefrist: nur beim Angebot, aus dem bereits bestehenden Feld
`validUntil` (bisher nur intern genutzt, nie im PDF angezeigt).

### Gruppen im PDF

`renderHtml()` aller fünf Belegtypen wird von einer flachen
Positionsliste auf gruppierte Darstellung umgestellt — dieselbe
Gruppierungslogik, die die Web-Ansicht bereits nutzt (Gruppentitel als
Zwischenüberschrift, Positionen ohne Gruppe am Ende oder in einem
"Sonstige Positionen"-Block).

### Positionspflege (editierbar statt nur lösch-/neu-anlegbar)

Neuer `updatePosition()`-Servicemethode + `PUT
.../positions/{id}`-Endpunkt für Quote/Order/Invoice/CreditNote
(Lieferschein hat keine Preise, dort bleibt nur die Beschreibung/Menge
relevant — auch dafür ein `updatePosition()`, aber ohne
Preis-/Rabattfelder). Kein zusätzliches serverseitiges
Status-Gate ("nur im Entwurf editierbar") über das hinaus, was bereits
für `addPosition`/`removePosition` gilt — das ist bislang rein
frontend-seitig durchgesetzt (`v-if="status === 'draft'"`); dieselbe
bestehende Inkonsistenz wird hier nicht neu eingeführt, nur nicht weiter
verschärft.

### Rabatte

- **Pro Position:** neues Feld `discountPercent` (float, 0–100, Default
  0) auf `QuotePosition`/`OrderPosition`/`InvoicePosition`/
  `CreditNotePosition`. Wirkt vor der MwSt.-Berechnung:
  `netTotal = quantity * unitPriceNet * (1 - discountPercent / 100)`.
- **Pro Beleg:** neues Feld `discountPercent` auf `Quote`/`Order`/
  `Invoice` (nicht auf `CreditNote` — eine Gutschrift ist bereits eine
  Korrektur, ein zusätzlicher Rabatt darauf wäre nur verwirrend; nicht
  auf `DeliveryNote` — dort gibt es keine Preise). Wirkt **zusätzlich**
  zum Positionsrabatt, anteilig je MwSt.-Satz-Bucket: jede
  Steuersatz-Bemessungsgrundlage wird um den Beleg-Rabattsatz reduziert,
  bevor die MwSt. darauf berechnet wird. Das hält die MwSt.-Aufteilung
  bei gemischten Steuersätzen korrekt, statt den Rabatt nur auf die
  Bruttosumme zu ziehen.
- `QuoteCalculationService::calculate()` (die einzige, von allen fünf
  Belegtypen geteilte Berechnungsklasse) bekommt beide Rabattstufen als
  Eingabe und gibt zusätzlich `netSubtotalBeforeDiscount` sowie den
  Beleg-Rabattbetrag zurück, damit das PDF eine eigene Rabattzeile zeigen
  kann.

## Konsequenzen

- Migration ist rein additiv (neue Tabelle, neue nullable/defaultete
  Spalten) — keine Datenmigration nötig, bestehende Belege bekommen
  `discount_percent = 0`.
- Bereits erzeugte PDFs (aus Phase 12) werden nicht rückwirkend neu
  gerendert — nur künftig ausgestellte/versendete Belege zeigen die
  überarbeiteten Inhalte.
- **Nicht Teil dieser Phase:** vollständige §14-UStG-Konformität für
  Rechnungen (bleibt wie in ADR-0021 zurückgestellt), Firmenlogo im PDF,
  mehrere Firmenprofile (Mandantenfähigkeit), Rabatt-Staffeln/Mengenrabatte,
  Freigabeworkflow für Rabatte oberhalb eines Schwellwerts.

## Alternativen erwogen

- **Rabatt nur als Freitext-Position mit negativem Betrag** (kein
  eigenes Feld) — verworfen: verwässert die Netto-/MwSt.-Berechnung
  (eine negative Freitext-Position hat einen eigenen MwSt.-Satz, der
  nicht zwingend zu den rabattierten Positionen passt) und macht den
  Rabatt im PDF nicht als das erkennbar, was er ist.
- **Firmenprofil als generischer Key-Value-Store** statt eigener Tabelle
  mit festen Spalten — verworfen: die Felder sind bekannt und endlich,
  eine eigene Tabelle ist einfacher zu validieren und zu migrieren als
  ein Schlüssel-Wert-Schema, das im PDF-Template ohnehin wieder feste
  Feldnamen erwartet.
