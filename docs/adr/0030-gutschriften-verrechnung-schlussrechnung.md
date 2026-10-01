# ADR-0030: Gutschriften mindern die Teilrechnungs-Verrechnung in der Schlussrechnung

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

ADR-0027 hatte `finalSettlement` eingeführt (§ 14 Abs. 5 Satz 2 UStG:
Gesamtauftragswert, Summe der bereits ausgestellten Teilrechnungen,
verbleibender Betrag), aber explizit zurückgestellt: "Keine
Berücksichtigung von Gutschriften in der Verrechnung — eine
Teil-Gutschrift auf eine bereits gestellte Teilrechnung mindert
`previouslyInvoiced` in dieser Version nicht." `status.md` führte das
seitdem als offenen Punkt.

**Konkrete Lücke:** Wird auf eine bereits ausgestellte Teilrechnung eine
**Teil**-Gutschrift erteilt (z. B. Korrektur einer falsch berechneten
Menge, ohne die ganze Teilrechnung zu stornieren), bleibt der
Rechnungsstatus unverändert (`issued`/`partially_paid`/`paid`) — die
Teilrechnung zählt also weiterhin voll zu `previouslyInvoiced`, obwohl
der Kunde effektiv weniger berechnet bekam. Eine **Voll**-Storno-
Gutschrift (`cancelsInvoice = true`) war davon nie betroffen: sie setzt
die Rechnung bereits auf `status = 'cancelled'`, wodurch
`finalSettlement()`s Status-Filter (`issued`/`partially_paid`/`paid`)
sie ohnehin komplett aus der Verrechnung herausnimmt.

## Entscheidung

**`InvoiceService::finalSettlement()` nettet jede vorige Teilrechnung
gegen die Summe ihrer tatsächlich ausgestellten (nicht Entwurf)
Teil-Gutschriften**, bevor sie in `previouslyInvoiced`/`priorInvoices`
einfließt:

```
netCalc(Teilrechnung) = calculation(Teilrechnung) − Σ calculation(ausgestellte Gutschriften zu dieser Rechnung)
```

Neue private Methode `creditedAmountForInvoice(int $invoiceId): array`
liest `CreditNoteMapper::findByInvoice()`, filtert auf
`status === 'issued'` und berechnet die Summe über
`QuoteCalculationService::calculate()` (dieselbe reine
Berechnungsklasse, die auch `CreditNoteService::getFull()` nutzt).
`negateCalculation()` kehrt Vorzeichen um, damit die bestehende
`sumCalculations()`-Hilfsfunktion (ADR-0027) für die Subtraktion
wiederverwendet werden kann — kein eigener Subtraktionscode, keine neue
Rundungslogik.

**Vollstorno-Gutschriften brauchen keine Sonderbehandlung** in dieser
Methode — sie sind bereits vorher aus `$priorIssued` herausgefiltert
(siehe Kontext). `creditedAmountForInvoice()` würde eine solche
Gutschrift zwar mitzählen, wenn sie aufgerufen würde, aber die Methode
wird für eine so stornierte Rechnung gar nicht erst aufgerufen.

**`remainingDue` bleibt unverändert `$calculation`** (die eigene Summe
der Schlussrechnung) — die in ADR-0027 festgestellte algebraische
Identität (`totalOrderValue − previouslyInvoiced === calculation`) gilt
nach dem Netting unverändert, da beide Seiten um denselben
Gutschrift-Betrag verschoben werden.

## Nicht Teil dieser Phase

- **Keine Berücksichtigung von Gutschriften auf die Schlussrechnung
  selbst** — nur Gutschriften auf *vorige* Teilrechnungen fließen in
  `previouslyInvoiced` ein. Eine Gutschrift auf die Schlussrechnung
  selbst erscheint weiterhin nur über die normale
  `relatedInvoices`-Auflistung (ADR-0016), nicht in `finalSettlement`.
- **Keine Unterscheidung von Gutschriftgründen** — jede ausgestellte
  Teil-Gutschrift mindert gleich, unabhängig davon, ob sie eine
  Preiskorrektur, eine Mängelrüge oder etwas anderes ist.
- **Weiterhin keine Korrektur der `vatBreakdown`-Zuordnung bei
  abweichenden MwSt.-Sätzen zwischen Rechnung und Gutschrift** über den
  bereits bestehenden `sumCalculations()`-Mechanismus hinaus — eine
  Gutschrift mit einem in der Originalrechnung nicht vorkommenden
  Steuersatz erzeugt einen neuen (ggf. negativen) Bucket in
  `vatBreakdown`, was rechnerisch korrekt, aber auf dem Beleg
  erklärungsbedürftig wäre. Seltener Grenzfall, nicht gesondert
  behandelt.

## Konsequenzen

- `InvoiceService` bekommt zwei neue Konstruktor-Abhängigkeiten
  (`CreditNoteMapper`, `CreditNotePositionMapper`) — reine Mapper, keine
  zirkuläre Abhängigkeit zu `CreditNoteService` (das selbst von
  `InvoiceService` abhängt).
- Alle manuellen Testaufrufstellen (`InvoiceServiceTest`,
  `DatevExportServiceTest`, `CreditNoteServiceTest`,
  `ReportingServiceTest`) mussten um die zwei neuen Parameter ergänzt
  werden; Controller-Instanziierung läuft über Nextclouds Autowiring und
  war nicht betroffen.
- `finalSettlement['previouslyInvoiced']` und
  `finalSettlement['priorInvoices'][n]` zeigen jetzt den um
  Teil-Gutschriften bereinigten Betrag.

## Alternativen erwogen

- **`recordPayment()`/Zahlungsstatus der Teilrechnung anpassen**, statt
  nur die Anzeige zu netten: verworfen — identische Begründung wie
  ADR-0027 ("Zentrale Erkenntnis"): die gesetzliche Pflicht ist eine
  Darstellungspflicht, kein Eingriff in die tatsächliche
  Zahlungs-/Fälligkeitslogik der Teilrechnung selbst.
- **Gutschriften-Summe direkt in `CreditNoteService` berechnen und von
  dort an `InvoiceService` übergeben lassen**: verworfen — hätte eine
  Abhängigkeit `InvoiceService → CreditNoteService` erzeugt, während
  `CreditNoteService` bereits von `InvoiceService` abhängt (zirkulär).
  Der direkte Zugriff auf die Mapper umgeht das, ohne Fachlogik zu
  duplizieren (`QuoteCalculationService::calculate()` bleibt die einzige
  Berechnungsquelle).
