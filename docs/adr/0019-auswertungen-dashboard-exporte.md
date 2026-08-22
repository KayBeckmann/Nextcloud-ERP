# ADR-0019: Auswertungen, Dashboard, Exporte

**Status:** accepted
**Datum:** 2026-08-22

## Kontext

Roadmap Phase 11 verlangt: ein ERP-Dashboard mit handlungsrelevanten
Daten, Projekt-Gewinn/Verlust, einen Soll/Ist-Vergleich, offene
Angebote/Rechnungen, Mindestbestand/Bestellvorschläge, Fuhrpark- und
Kostenübersichten, Zeitkonto-/Überstundenübersichten, einen Export für
Steuerberater/Buchhaltung, optional XRechnung/ZUGFeRD.
Prüfkriterien: das Dashboard zeigt handlungsrelevante Daten, Auswertungen
berücksichtigen Rechte, der Export ist reproduzierbar und dokumentiert.

Alle für diese Auswertungen nötigen Rohdaten existieren bereits aus den
Phasen 4–10 (Projekte, Angebote, Aufträge, Rechnungen, Lager,
Bestellvorschläge, Fuhrpark, Kosten & Kalkulation, Zeitkonto). Phase 11
fügt bewusst **keine neuen persistenten Datenmodelle** hinzu — sie liest
und aggregiert nur, analog zum bereits bestehenden `PurchaseSuggestionService`
(Phase 8), das ebenfalls rein aus Lagerdaten berechnet statt zu speichern.
Deshalb ist für diese Phase **keine Migration nötig**.

## Entscheidung

### Kein neues Datenmodell — reine Aggregation bestehender Services

`ReportingService` lädt Rohdaten aus den bestehenden Mappern/Services
(`QuoteMapper`, `OrderMapper`, `InvoiceMapper`, `StockService`,
`PurchaseSuggestionService`, `VehicleMapper`, `VehicleFuelLogMapper`,
`CostService`, `TimeAccountService`, `TimeEntryMapper`,
`ArticleSupplierPriceMapper`) und delegiert die eigentliche
Gewinn/Verlust-Rechnung an eine reine, DB-freie
`ProjectProfitLossCalculationService` — derselbe Aufbau wie
`CostCalculationService` (ADR-0018) und `StockCalculator` (ADR-0014).

### Dashboard-Summary: eine Kachel-Endpoint, gate über `ResourceType::Dashboard`

`GET /api/v1/dashboard/summary` liefert in einem Aufruf alle Kacheln aus
`DashboardView.vue` (dort bereits seit Phase 1 als Platzhalter
spezifiziert): offene Angebote (Anzahl + Nettosumme), offene/überfällige
Rechnungen (Anzahl + Summe), Projekte in Bearbeitung, Artikel unter
Mindestbestand, Bestellvorschläge, fällige TÜV/Werkstatttermine,
Tankkosten laufender Monat, interner Stundensatz (aktuelles Jahr aus
ADR-0018), eigenes Zeitkonto (Monat).

`ResourceType::Dashboard` ist seit Phase 1 im Rechte-Enum reserviert und
wird hier zum ersten Mal tatsächlich als Gate verwendet (derselbe Ablauf
wie bei jeder vorherigen Phase, die eine zuvor reservierte
`ResourceType`-Ausprägung "aktiviert").

### Zeitkonto-Kachel zeigt bewusst nur die eigenen Daten des angemeldeten Users

Ein einzelnes `Dashboard`-Rechte-Gate (`Read`) würde bei einer
firmenweiten Zeitkonto-/Überstunden-Übersicht ungewollt fremde
Arbeitszeitdaten offenlegen — Zeitkonto-Daten einzelner Mitarbeiter sind
im bestehenden Rechte-Modell (ADR-0008) über `StundenZeitkonto` separat
gated und dort pro User beschränkt. Um dieses Rechte-Modell nicht zu
unterlaufen, zeigt die Dashboard-Kachel "Zeitkonto" ausschließlich den
Saldo des gerade angemeldeten Users für den laufenden Monat
(`TimeAccountService::getAccount($userId, ...)` mit der `IUser`-ID aus
der Session) sowie die Anzahl eigener offener Abwesenheits-/
Überstundenanträge. Eine firmenweite Zeitkonto-Übersicht für Admins ist
denkbar, aber **nicht Teil dieser Phase** (siehe unten).

### Projekt-Gewinn/Verlust: Soll aus Auftrag/Angebot, Ist aus Rechnungen + Zeiterfassung + Materialkosten-Approximation

`GET /api/v1/reports/projects/{id}/profit-loss` (Gate: `ResourceType::Projekte`,
Read — dieselbe Berechtigung, mit der auch das Projekt selbst sichtbar ist):

- **Soll:** Netto-Summe des (neuesten) Auftrags des Projekts; existiert
  noch kein Auftrag, ersatzweise die Netto-Summe des zuletzt versendeten
  Angebots. Fehlt beides, ist Soll `null` (kein Platzhalterwert wie `0`,
  um "kein Soll erfasst" von "Soll ist 0 €" zu unterscheiden).
- **Ist-Umsatz:** Netto-Summe aller ausgestellten (nicht: Entwurf-)
  Rechnungen des Projekts.
- **Ist-Kosten — Personal:** Summe der Zeiterfassungen des Projekts
  (`TimeEntryMapper::findByProject`) in Stunden, je nach Erfassungsjahr
  mit dem internen Stundensatz des jeweiligen Jahres (ADR-0018)
  multipliziert — nicht mit dem kundenseitigen `rateSnapshot` (ADR-0012),
  das ist der externe Verrechnungssatz, keine interne Kostengröße.
- **Ist-Kosten — Material:** Summe der Rechnungspositionen mit
  `positionType === 'article'`, bewertet mit dem **günstigsten aktuell
  hinterlegten Einkaufspreis** des jeweiligen Artikels
  (`ArticleSupplierPriceMapper::findByArticle`, `min(purchasePrice)`).
  Das ist eine **Approximation**, keine historische Preis-Momentaufnahme
  zum Rechnungsdatum — echte Einkaufspreis-Historisierung existiert im
  Datenmodell nicht und ist nicht Teil dieser Phase. Positionen vom Typ
  `product`/`labor`/`custom` fließen mit Materialkosten `0` ein (Bundles
  haben keine eigene Einkaufspreis-Beziehung, `labor`/`custom` sind keine
  Artikel) — dokumentierte Einschränkung, kein Rechenfehler.
- **Ergebnis:** Ist-Umsatz − (Personal- + Materialkosten).

Die reine Rechenlogik (Soll/Ist-Differenz, Ergebnis) sitzt in
`ProjectProfitLossCalculationService`, DB-frei und unit-testbar — die
Datenbeschaffung (welche Rechnungen, welche Zeiterfassungen, welche
Einkaufspreise) bleibt in `ReportingService`.

### CSV-Export für Steuerberater/Buchhaltung — eigener, nicht-OCS-Controller

`GET /export/invoices.csv?from=&to=&status=` (optional gefiltert nach
Ausstellungsdatum-Zeitraum und Status) liefert eine CSV-Datei
(Rechnungsnummer, Ausstellungsdatum, Kunde, Netto, MwSt., Brutto, Status,
bezahlter Betrag) aller **ausgestellten** Rechnungen (Entwürfe haben noch
keine Rechnungsnummer und gehören fachlich nicht in einen
Buchhaltungsexport).

Die bestehende API ist durchgehend OCS/JSON (`/ocs/v2.php/apps/erp/api/v1/...`),
das passt nicht zu einem rohen Datei-Download mit
`Content-Disposition: attachment`. Der Export läuft deshalb über einen
eigenen, schlanken `ReportExportController extends Controller` (wie
`PageController`, nicht `OCSController`) unter der normalen App-Route
`/apps/erp/export/invoices.csv`, mit demselben
`PermissionService`/`ResourceType::Dashboard`-Gate wie die
Dashboard-Summary, nur manuell statt über `AbstractResourceController`
(dessen `OCSForbiddenException` in einem Nicht-OCS-Controller nicht
passt). Reproduzierbarkeit/Dokumentation des Formats steht in
`docs/api/v1.md`.

### Explizit nicht Teil dieser Phase

- **XRechnung/ZUGFeRD** — von der Roadmap selbst als "optional"
  markiert. Strukturierte E-Rechnungsformate haben eigene rechtliche/
  fachliche Anforderungen (Pflichtfelder, Validierungsregeln,
  Leitweg-ID), die eine eigene Prüfung verdienen — bewusst zurückgestellt,
  analog zur in ADR-0016 zurückgestellten Abschlagsrechnungs-Arithmetik
  nach deutschem Steuerrecht.
- **Firmenweite Zeitkonto-/Überstundenübersicht für Admins** — nur die
  eigene Zeitkonto-Kachel ist Teil dieser Phase (siehe oben); eine
  Rollen-gesteuerte Firmenübersicht bräuchte eine bewusste Erweiterung
  des Rechte-Modells (wer darf wessen Zeitkonto sehen) und ist als
  eigenständige Entscheidung zurückgestellt.
- **Historisierte Einkaufspreise** für eine exakte
  Materialkosten-Zuordnung zum Rechnungsdatum — aktuell wird der
  günstigste *aktuelle* Einkaufspreis verwendet (siehe oben).
  Nachträgliche Preisänderungen wirken sich rückwirkend auf ältere
  Projekt-P&L-Abfragen aus; das ist eine bekannte Grenze, kein Bug.
- **Gespeicherte/planbare Exporte** (z. B. automatischer monatlicher
  Buchhaltungsexport per E-Mail) — der Export ist ein On-Demand-Abruf.
- **Dashboard-Kacheln, die reines Informationsmaterial ohne
  DB-Berechnung sind** ("Anstehende Termine" aus Nextcloud Calendar,
  "API & Sync"-Status) bleiben vorerst Platzhalter bzw. statischer Text —
  sie liefern keinen zusätzlichen Erkenntniswert gegenüber der
  bestehenden Calendar-Integration (ADR-0009) und dem ohnehin über die
  gesamte App laufenden API-v1-Status.

## Konsequenzen

- Kein neues DB-Schema, keine neue Migration — Phase 11 ist reine
  Lesequery-/Aggregationslogik auf bestehenden Tabellen.
- `ReportingService` bekommt zwangsläufig viele Konstruktor-Abhängigkeiten
  (ein Mapper/Service je aggregierter Datenquelle) — bewusst in Kauf
  genommen, statt eine künstliche Zwischenschicht einzuziehen, die nur
  diese eine Phase entlasten würde.
- Der CSV-Export ist der erste Nicht-OCS-API-Endpunkt des Projekts
  (abgesehen von `page#index`) — künftige Datei-Exporte (PDF-Sammel-
  export, ZIP-Export) folgen demselben Muster.
