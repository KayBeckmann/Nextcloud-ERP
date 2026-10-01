# ADR-0027: Verrechnung bereits gestellter Teilrechnungen in der Schlussrechnung

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

ADR-0016 hatte die automatische Verrechnung von Teilrechnungsbeträgen in
der Schlussrechnung bewusst zurückgestellt ("steuerrechtlich nicht
trivial") und stattdessen nur eine reine, nicht verrechnete Auflistung
(`relatedInvoices`) gebaut. `status.md` dokumentierte das seitdem offen:
"Keine automatische Verrechnung/Subtraktion von Teilrechnungsbeträgen in
der Schlussrechnung ... echte Abschlagsrechnungs-Arithmetik nach § 14
UStG bleibt offen."

**Gesetzliche Grundlage (§ 14 Abs. 5 Satz 2 UStG, konkretisiert in
Abschn. 14.8 UStAE):** Rechnet eine Rechnung über die gesamte Leistung
ab, müssen die vor Ausführung vereinnahmten Teilentgelte und die darauf
entfallenden Steuerbeträge abgesetzt werden, sofern über sie Rechnungen
mit gesondertem Steuerausweis erteilt wurden. Für mehrere Teilzahlungen
genügt es, die **Summe** der Teilentgelte und die Summe der
Steuerbeträge abzusetzen — keine zwingende Einzelposition in der
Haupttabelle, aber eine nachvollziehbare Aufstellung.

## Entscheidung

**Neues Feld `finalSettlement`** in `InvoiceService::getFullInvoice()`,
ausschließlich für `type === 'final'` mit `orderId` und mindestens einer
tatsächlich ausgestellten (nicht Entwurf, nicht storniert)
Geschwister-Rechnung desselben Auftrags:

```
{
  totalOrderValue:    { netSubtotal, vatBreakdown[], grossTotal }  // diese Rechnung + alle vorigen
  previouslyInvoiced: { netSubtotal, vatBreakdown[], grossTotal }  // nur die vorigen, summiert
  remainingDue:        { netSubtotal, vatBreakdown[], grossTotal } // totalOrderValue − previouslyInvoiced
  priorInvoices: [ {invoiceNumber, issuedAt, netSubtotal, vatAmount, grossTotal}, ... ]
}
```

`priorInvoices` ist die vom Gesetz verlangte "besondere Aufstellung" —
jede zuvor ausgestellte Teilrechnung einzeln mit Netto/MwSt./Brutto.
`previouslyInvoiced` ist deren Summe (erfüllt die vereinfachte Variante
"Summe der Teilentgelte und Steuerbeträge"). Beide werden geliefert,
das Web-UI/PDF zeigt beides.

**Zentrale Erkenntnis beim Entwurf:** `remainingDue` ist **rechnerisch
immer identisch** mit der `calculation` dieser Rechnung selbst
(`totalOrderValue − previouslyInvoiced` kürzt sich algebraisch exakt
dazu heraus, unabhängig davon, ob die Schlussrechnung nur die
verbleibenden Positionen enthält oder den gesamten Auftragswert erneut
auflistet). Die gesetzliche Pflicht ist eine **Darstellungspflicht**
(die Herleitung "Gesamt − bereits berechnet = verbleibend" muss auf dem
Beleg sichtbar sein), keine Pflicht, den tatsächlich fälligen Betrag zu
verändern. Deshalb:

- **`InvoiceService::recordPayment()` bleibt unverändert** — prüft
  weiterhin gegen die eigene `calculation.grossTotal` dieser Rechnung,
  nicht gegen `finalSettlement`. Keine Verhaltensänderung an der
  bestehenden Zahlungs-/Statuslogik.
- `finalSettlement` ist rein additiv: zusätzliches Feld in der
  API-Antwort, zusätzlicher Abschnitt im PDF (`DocumentHtmlBuilder::
  finalSettlement()`) und im Web-UI, verändert nichts Bestehendes.

**Nur tatsächlich ausgestellte Teilrechnungen zählen** (`status` ∈
`{issued, partially_paid, paid}`) — ein Entwurf hat keine Rechnungsnummer
und keinen gesonderten Steuerausweis, eine stornierte Rechnung wurde
durch ihre Gutschrift bereits neutralisiert; beide würden das Gesetz
("Rechnungen mit gesondertem Steuerausweis") nicht erfüllen.

**PDF-Erzeugung beim Ausstellen:** `renderHtml()` berechnet
`finalSettlement` zum Zeitpunkt des `issue()`-Aufrufs und schreibt das
Ergebnis fest in das erzeugte PDF/HTML-Dokument (wie alle anderen
Belegdaten, ADR-0013: Rechnung wird beim Ausstellen unveränderlich).
Spätere neue Teilrechnungen zum selben Auftrag ändern das bereits
ausgestellte Schlussrechnungs-Dokument nicht rückwirkend — nur die
Live-API-Antwort (`GET /invoices/{id}`) würde sie (bei einer erneuten
Abfrage) berücksichtigen, was aber für eine bereits ausgestellte,
unveränderliche Rechnung ohnehin nur noch für den Web-UI-Blick relevant
ist, nicht für das Dokument selbst.

## Nicht Teil dieser Phase

- **Keine Unterscheidung Anzahlung (§14 Abs. 5) vs. echte
  Teilleistungsabrechnung** — beide Fälle werden identisch über `type
  = 'partial'` behandelt (ADR-0016) und identisch verrechnet. Fachlich
  sind das unterschiedliche Konzepte, die Verrechnungslogik ist für
  beide aber unschädlich korrekt (siehe "Zentrale Erkenntnis" oben).
- **Keine Berücksichtigung von Gutschriften** in der Verrechnung — eine
  Teil-Gutschrift auf eine bereits gestellte Teilrechnung mindert
  `previouslyInvoiced` in dieser Version nicht. Bei Bedarf eigene
  Erweiterung.
- **Keine automatische Prüfung**, ob `totalOrderValue` tatsächlich dem
  ursprünglichen Auftrags-/Angebotswert entspricht — rein additiv aus
  den vorhandenen Rechnungen berechnet, keine Rückvalidierung gegen
  `erp_orders`/`erp_order_positions`.

## Konsequenzen

- `InvoiceService::getFullInvoice()` und `renderHtml()` berechnen
  zusätzlich `finalSettlement` über eine neue private
  `sumCalculations()`-Hilfsfunktion (summiert mehrere `calculate()`-
  Ergebnisse je MwSt.-Satz).
- `DocumentHtmlBuilder` bekommt eine neue `finalSettlement()`-Methode
  für den PDF-Abschnitt.
- Web-UI (`RechnungDetailView.vue`) zeigt die Verrechnung als eigenen
  Abschnitt vor der bisherigen, weiterhin unveränderten
  `relatedInvoices`-Auflistung.

## Alternativen erwogen

- **`remainingDue` weglassen, da redundant mit `calculation`:**
  verworfen — die explizite Herleitung auf dem Beleg ("Gesamt − bereits
  berechnet = verbleibend") ist genau das, was § 14 Abs. 5 UStG
  verlangt; ein Leser soll nicht selbst nachrechnen müssen.
- **`recordPayment()` gegen `remainingDue` statt `calculation.grossTotal`
  prüfen lassen:** verworfen, weil rechnerisch identisch (siehe oben) —
  hätte nur Komplexität ohne fachlichen Unterschied hinzugefügt.
