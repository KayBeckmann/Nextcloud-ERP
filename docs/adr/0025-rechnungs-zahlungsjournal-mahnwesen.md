# ADR-0025: Rechnungs-Zahlungsjournal und Mahnwesen-Grundgerüst

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

`InvoiceService::recordPayment()` (Phase 7, ADR-0013) kannte seit Anfang
an nur eine einzige fortlaufende Summe (`Invoice.paidAmount`), die bei
jeder Zahlung nur hochgezählt wurde. `status.md` hielt das seitdem explizit
als offenen Punkt fest: "Kein Zahlungsjournal mit Einzelbuchungen
(Datum/Referenz je Teilzahlung, Mahnwesen) — nur ein laufender
`paid_amount`-Betrag." Praktische Folgen:

- Keine Nachvollziehbarkeit, WANN welcher Betrag mit welcher Referenz
  (Überweisungszweck, Belegnummer) tatsächlich einging.
- Keine Grundlage für ein Mahnwesen — ohne Historie lässt sich weder
  erkennen, ob bereits gemahnt wurde, noch auf welcher Stufe.

## Entscheidung

### Zahlungsjournal statt blinder Summenfortschreibung

Neue Entität `InvoicePayment` (Tabelle `erp_invoice_payments`): je
Zahlung ein Datensatz mit Betrag, vom Nutzer gewähltem Zahlungsdatum
(`paidAt`, ISO-Datum), optionaler Referenz/Notiz und technischem
Erfassungszeitpunkt (`recordedAt`, immer "jetzt") plus erfassendem
Nutzer. `paidAt` und `recordedAt` sind bewusst getrennte Felder —
Nachbuchungen (eine Zahlung vom Vortag wird erst heute im ERP erfasst)
sind der Normalfall, nicht die Ausnahme.

`Invoice.paidAmount` bleibt als Feld bestehen (keine Breaking Change für
bestehende API-Konsumenten), wird aber nicht mehr eigenständig
hochgezählt, sondern bei jeder neuen Zahlung aus `SUM(erp_invoice_payments.amount)`
neu berechnet (`InvoicePaymentMapper::sumByInvoice()`). Das schließt die
Möglichkeit aus, dass Journal und Summenfeld auseinanderdriften.

### Mahnwesen V1: manuelle Eskalationsstufen, kein Automatismus

Neue Entität `InvoiceDunningStep` (Tabelle `erp_invoice_dunning_steps`)
und ein neues Feld `Invoice.dunningLevel` (0 = keine Mahnstufe, 1 =
Zahlungserinnerung, 2 = erste Mahnung, 3 = zweite/letzte Mahnung).

Bewusste Einschränkungen für diese Phase:

- **Kein automatischer Versand**, keine automatisch generierte
  Mahn-PDF/E-Mail. `recordDunningStep()` ist ein rein manueller,
  auditierbarer Eintrag ("ich habe heute Stufe X ausgelöst"), analog zu
  `PurchaseOrderService::transitionStatus()`.
- **Eskalation muss lückenlos um genau 1 steigen** (0→1→2→3). Das
  verhindert, dass aus Versehen Stufen übersprungen werden, und macht die
  Historie zu einer nachvollziehbaren Kette statt einer beliebigen
  Zahlenfolge.
- **Nur für überfällige, unbezahlte Rechnungen** (`status` ∈
  `{issued, partially_paid}` UND Fälligkeitsdatum in der Vergangenheit —
  dieselbe `isOverdue()`-Logik, die bereits für das `isOverdue`-Flag in
  `getFullInvoice()` existierte).
- **Vollständige Zahlung setzt die Mahnstufe automatisch auf 0 zurück**
  (in `recordPayment()`) — eine beglichene Rechnung braucht keine aktive
  Mahnhistorie mehr im laufenden Status, die Historie selbst (die
  einzelnen `InvoiceDunningStep`-Einträge) bleibt aber erhalten.

### Nicht Teil dieser Phase

- Automatischer Mahnlauf (z. B. ein täglicher Cronjob, der überfällige
  Rechnungen erkennt und automatisch eskaliert) — bewusst manuell
  ausgelöst, bis sich ein Bedarf für Automatisierung zeigt.
- Mahngebühren/Verzugszinsen-Berechnung.
- Eigene Mahn-PDF-Vorlage (analog zu den fünf Belegtypen aus Phase 12) —
  die Dunning-Historie ist aktuell reiner Web-UI-/API-Nachweis, kein
  Dokument zum Versenden.
- Ein vollständiges Zahlungsjournal-Reporting (z. B. offene-Posten-Liste
  über alle Rechnungen) — das gehört eher zu Phase 11
  (Auswertungen/Dashboard) und wird dort nachgezogen, falls gewünscht.

## Konsequenzen

- `InvoiceService::recordPayment()` hat eine neue, erweiterte Signatur
  (zusätzlich `paidAt`, `recordedBy`, optional `reference`/`notes`) —
  bestehende Aufrufer (Controller, Tests) mussten angepasst werden.
- Zwei neue Tabellen, ein neues Spaltenfeld auf `erp_invoices`
  (`dunning_level`), vier neue API-Endpunkte
  (`GET`/`POST /invoices/{id}/payments`, `GET`/`POST
  /invoices/{id}/dunning-steps`).
- Web-UI (`RechnungDetailView.vue`) zeigt das Journal und die
  Mahnhistorie statt nur der nackten Summe.

## Alternativen erwogen

- **Zahlungsjournal als generische "Activity Log"-Tabelle** (eine Tabelle
  für alle Belegtypen statt `erp_invoice_payments` speziell für
  Rechnungen): verworfen — Zahlungen haben eigene Fachfelder (Betrag,
  Zahlungsdatum), die ein generisches Freitext-Log nicht sauber abbilden
  würde; das Projekt hat bereits mehrere beleg-spezifische
  Historientabellen (`erp_purchase_order_status_changes`,
  `erp_contact_history_snapshots`), dieses Muster wird hier fortgesetzt.
- **Mahnstufe automatisch aus Fälligkeitsdatum + Tagen-Offset ableiten**
  (kein eigenes `dunningLevel`-Feld, rein berechnet): verworfen — würde
  verschleiern, OB tatsächlich eine Mahnung versendet wurde, nur WANN sie
  fällig gewesen wäre. Ein manuell gesetzter Status mit Historie ist für
  ein Mahnwesen die fachlich richtige Abbildung.
