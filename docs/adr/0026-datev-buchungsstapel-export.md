# ADR-0026: DATEV-Buchungsstapel-Export für Rechnungen

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

`status.md` dokumentierte seit Phase 7 offen: "Kein
Steuerberater-Exportformat (z. B. DATEV) implementiert." Der bestehende
`GET /export/invoices.csv` (ADR-0019) liefert eine lesbare, aber
generische Tabelle (Rechnungsnummer, Datum, Kunde, Netto/MwSt/Brutto,
Status, Bezahlt) — kein Format, das ein Steuerberater direkt in eine
Finanzbuchführung importieren kann.

DATEVs Standard-Austauschformat für Buchungsdaten ist das
**EXTF-Buchungsstapel-Format** (CSV, Formatversion 700, Datenkategorie
21): eine Metadaten-Kopfzeile, eine 125-spaltige Spaltennamen-Zeile,
danach eine Buchungszeile je Geschäftsvorfall mit Pflichtfeldern wie
Umsatz, Soll/Haben-Kennzeichen, Konto, Gegenkonto, Belegdatum,
Belegnummer und Buchungstext.

**Zentrales Problem:** Ein korrekter DATEV-Buchungssatz braucht
Soll-/Gegenkonto-Nummern aus einem echten Kontenrahmen (SKR03/SKR04).
Dieses ERP hat **keinen eigenen Kontenplan** — keine individuellen
Debitorenkonten je Kunde, keine konfigurierbaren Erlöskonten je
MwSt.-Satz. Eine vollständige, für jeden Anwendungsfall korrekte
Buchführungs-Integration wäre ein eigenes, deutlich größeres
Architekturvorhaben (Kontenplan-Verwaltung, Kunden↔Debitorenkonto-
Zuordnung, Kontenrahmen-Auswahl SKR03 vs. SKR04 usw.).

## Entscheidung

**Pragmatisches V1 statt vollständiger Buchführungs-Integration:**
Export der ausgestellten Rechnungen als DATEV-EXTF-Buchungsstapel unter
Verwendung der SKR03-**Standardkonten mit automatischer
USt.-Verbuchung** — diese Konten tragen den Steuersatz implizit im
Kontenrahmen, ein Konto allein genügt DATEV zur korrekten
USt.-Berechnung, ohne dass dieses ERP selbst einen BU-Schlüssel oder
Kontenplan verwalten müsste:

| MwSt.-Satz | SKR03-Gegenkonto |
|---|---|
| 19 % | 8400 (Erlöse 19 % USt) |
| 7 % | 8300 (Erlöse 7 % USt) |
| 0 % | 8120 (steuerfreie Umsätze) |
| andere | 8400 (Platzhalter) + `PRÜFEN <Satz>%:` im Buchungstext |

Gegenbuchung läuft über ein **generisches Debitoren-Sammelkonto**
(Default `10000`, per Query-Parameter änderbar) statt individueller
Kundenkonten — Standardkonvention für kleine Betriebe ohne
Einzeldebitoren-Buchhaltung.

Pro Rechnung eine Buchungszeile je MwSt.-Satz-Block (eine Rechnung mit
gemischten Sätzen erzeugt entsprechend mehrere Zeilen). Datenquelle ist
`InvoiceService::getFullInvoice()['calculation']['vatBreakdown']` —
dieselbe Berechnung, die auch für die PDF-Ausgabe (Phase 12/13) genutzt
wird, keine zweite Berechnungslogik.

**Beraternummer/Mandantennummer sind Pflicht-Query-Parameter ohne
Default** — beides DATEV-Pflichtangaben ohne sinnvollen Platzhalterwert;
ein stiller Default-Wert hätte das Risiko, unbemerkt in einer echten
Kanzlei-Mandantenakte zu landen.

**Spaltenstruktur nicht aus der Erinnerung rekonstruiert:** Kopfzeile
und alle 125 Spaltennamen stammen 1:1 aus einer echten, öffentlich
dokumentierten DATEV-EXTF-Beispieldatei (siehe `DatevExportService`-
Klassenkommentar für die Quelle), um das Risiko einer leicht
"plausibel aussehenden", aber tatsächlich falschen Spaltenstruktur zu
vermeiden.

**Zeichensatz:** UTF-8 mit BOM (DATEV-Rechnungswesen akzeptiert das laut
Dokumentation ab Formatversion 700) statt des bei älteren
DATEV-Systemen klassischen Windows-1252 — siehe "Nicht Teil dieser
Phase" unten.

## Nicht Teil dieser Phase

- **Kein eigener Kontenplan/Kontenrahmen** — SKR03-Konten sind
  hartkodiert, nicht konfigurierbar außer dem Debitorenkonto. Eine
  SKR04-Variante oder frei wählbare Erlöskonten wären ein eigenes
  Vorhaben.
- **Keine Zahlungsjournal-Buchungen** (Bankkonto/Zahlungseingang) —
  dieser Export bildet nur die Rechnungsstellung (Forderung + Erlös)
  ab, nicht die aus ADR-0025 neu vorhandenen Einzelzahlungen. Eine
  zweite Buchungsart dafür wäre eine naheliegende Erweiterung.
- **Keine Einkaufs-/Lieferantenrechnungen** (nur Debitoren-/
  Erlösseite, keine Kreditoren-Buchungen aus `PurchaseOrder`).
- **Keine Gutschriften-Buchungen** — Vollstornos werden aktuell nur
  implizit durch Ausschluss stornierter Rechnungen "neutralisiert",
  nicht als eigene Stornobuchung exportiert.
- **Keine automatische Verifikation gegen eine echte DATEV-Installation**
  — der Export wurde gegen eine öffentlich dokumentierte Beispieldatei
  entwickelt und getestet, aber nie tatsächlich in DATEV importiert.

## Konsequenzen

**Vor dem ersten produktiven Einsatz zwingend mit dem Steuerberater
abstimmen:**

1. Ob die SKR03-Standardkonten (8400/8300/8120, Debitor 10000) zum
   tatsächlich verwendeten Kontenrahmen der Kanzlei passen, oder
   individuelle Konten nötig sind.
2. Ob UTF-8-mit-BOM von der konkreten DATEV-Version akzeptiert wird,
   oder Windows-1252 nötig ist.
3. Ob Rechnungen mit MwSt.-Sätzen außerhalb von 19 %/7 %/0 % vorkommen
   und wie die `PRÜFEN`-markierten Zeilen zu behandeln sind.

Neue Service-Klasse `DatevExportService`, ein neuer Endpunkt
`GET /export/datev-buchungsstapel.csv` (derselbe Nicht-OCS-Download-
Mechanismus wie `invoices.csv`, ADR-0019).

## Alternativen erwogen

- **BU-Schlüssel statt automatischer SKR03-Konten verwenden** (ein
  einziges generisches Erlöskonto + expliziter Steuerschlüssel je
  Satz): hätte denselben Informationsgehalt, wäre aber vom Grundkonto
  stärker abhängig von der tatsächlichen Kontenplan-Variante der
  Kanzlei. Automatische Konten sind der in Praxisbeispielen häufiger
  beobachtete Standardfall für kleine Betriebe und wurden deshalb
  bevorzugt.
- **Export verweigern, bis ein eigener Kontenplan existiert:** hätte
  den offenen Punkt unbegrenzt verschoben, obwohl ein pragmatisches,
  klar als vorläufig markiertes V1 bereits einen echten Mehrwert bringt
  (siehe Projektkonvention: andere Phasen wie PDF-Export, ADR-0021,
  wurden ebenfalls bewusst mit expliziten "vor Produktiveinsatz
  gegenprüfen"-Hinweisen statt perfekter Vollständigkeit ausgeliefert).
