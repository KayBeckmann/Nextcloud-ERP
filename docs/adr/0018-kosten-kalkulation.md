# ADR-0018: Betriebliche Kosten und Kalkulation

**Status:** accepted
**Datum:** 2026-08-21

## Kontext

Roadmap Phase 10 verlangt Gemeinkosten und Zuschläge nachvollziehbar zu
machen: Kostenarten (Miete, Telefon/Internet, Software, Gehälter,
Lohnnebenkosten, Versicherungen, Berufsgenossenschaft, Steuerberater,
Fahrzeuge, Werkzeuge, Energie, Finanzierung/Leasing, Marketing, sonstige
Gemeinkosten), Gemeinkosten pro Monat/Jahr, produktive Stunden als
Bezugsgröße, interner Stundensatz, Materialaufschläge, Produktaufschläge,
Auswertungen. Prüfkriterium: die Kalkulation muss nachvollziehbar sein
("nicht nur ein fixer Aufschlag") und intern von den externen
Verrechnungssätzen aus ADR-0012 getrennt bleiben.

## Entscheidung

### Kostenarten als feste Enum, echte Einzelposten statt Schätzwert

`erp_cost_entries` (category — feste Enum mit den 14 Roadmap-Kostenarten;
title; monthly_amount; year; month 1–12; notes; created_at/updated_at).
Jeder Eintrag ist ein echter, für einen konkreten Monat gebuchter
Kostenposten (z. B. "Miete Januar 2026: 800 €"), keine geschätzte
Jahrespauschale — das erfüllt das Prüfkriterium "nachvollziehbar, nicht
nur ein fixer Aufschlag" direkt im Datenmodell: die Jahressumme ergibt
sich aus der Summe der tatsächlich erfassten Monatsposten, nicht aus
einem einzelnen Freitextfeld.

`CostCategory`-Enum (analog `OrderStatus`, ADR-0010): `rent`,
`phone_internet`, `software`, `salaries`, `payroll_costs`, `insurance`,
`professional_association`, `tax_advisor`, `vehicles`, `tools`, `energy`,
`financing_leasing`, `marketing`, `other`.

### Kalkulationseinstellungen pro Jahr

`erp_cost_settings` (year — unique; productive_hours_per_year;
material_surcharge_percent; product_surcharge_percent; created_at/
updated_at). Produktive Stunden sind bewusst ein manuell gepflegter
Erfahrungswert pro Jahr, keine automatische Ableitung aus den echten
Zeiterfassungsdaten aus ADR-0012 — eine Verrechnung "produktive Stunden
= Summe aller `erp_time_entries` mit `billable=true`" wäre zwar
technisch möglich, hätte aber das laufende Geschäftsjahr immer nur
rückblickend abgebildet, während die Kalkulation typischerweise
vorausschauend mit einem Planwert arbeitet (z. B. "1.600 Std./Jahr
geplant"). Die Verbindung zu echten Ist-Stunden bleibt für eine spätere
Auswertung (Plan/Ist-Vergleich, Phase 11) vorbereitet, aber nicht Teil
dieser Phase.

### Interner Stundensatz und Aufschläge: reine Berechnung, keine Anwendung

`CostCalculationService` (statisch, DB-frei — analog
`QuoteCalculationService`/`RateResolutionService`) berechnet:

- `internalHourlyRate = Jahressumme aller Kostenposten / productive_hours_per_year`
- `surchargedPrice = baseCost * (1 + surchargePercent / 100)`

Beide Ergebnisse sind **rein informativ** — sie fließen nicht automatisch
in Verrechnungssätze (ADR-0012, `erp_rates`) oder in
Artikel-/Produktpreise ein. Das erfüllt "interne Kosten und externe
Verrechnungssätze bleiben getrennt" wörtlich: der interne Stundensatz
landet nirgends automatisch in einem Angebot, er ist eine
Entscheidungsgrundlage, die der Nutzer selbst in seine
Verrechnungssätze/Preise einträgt. "Ergebnisse können in Angebote/
Produkte einfließen" wird durch einen Kalkulationsrechner im Web-UI
erfüllt (Eingabe eines Einkaufspreises + gewünschter Aufschlag-Kategorie
→ Anzeige des empfohlenen Verkaufspreises), nicht durch eine automatische
Schreiboperation auf Artikel/Produkte.

### Rechte

Neuer Controller `CostController`, gated über das bereits in ADR-0008
vorgesehene `ResourceType::KostenKalkulation` (bisher nur im Enum
reserviert, jetzt erstmals tatsächlich verwendet — identisches Muster
wie `ResourceType::Fuhrpark` in ADR-0017).

## Nicht Teil dieser Phase

- **Keine automatische Verknüpfung mit Zeiterfassung** — produktive
  Stunden sind ein manueller Planwert, kein Ist-Wert aus ADR-0012.
- **Keine automatische Anwendung der Aufschläge auf Artikel/Produkte**
  — der Kalkulationsrechner zeigt nur einen empfohlenen Preis an, ändert
  keine bestehenden Datensätze.
- **Kein Plan/Ist-Vergleich** (geplante vs. tatsächliche Kosten/Stunden)
  — reine Erfassung und Berechnung, Auswertungen über die Zeit sind
  Phase 11.
- **Keine Verteilung der Gemeinkosten auf einzelne Projekte/Aufträge**
  (Kostenstellenrechnung) — der interne Stundensatz ist ein
  unternehmensweiter Wert, keine Projekt-Zuordnung.

## Konsequenzen

- Die Jahreskostensumme ist jederzeit auf die einzelnen erfassten Posten
  zurückführbar (Prüfkriterium "nachvollziehbar" erfüllt).
- Interner Stundensatz und externe Verrechnungssätze (ADR-0012) sind zwei
  getrennte Systeme ohne automatischen Datenfluss zwischeneinander.
- Der Kalkulationsrechner ist ein reines Werkzeug — Nutzer entscheiden
  selbst, ob und wie sie das Ergebnis in ihre Preise übernehmen.

## Alternativen erwogen

- **Ein einziges Freitext-"Gemeinkosten pro Jahr"-Feld** statt
  Einzelposten je Kostenart/Monat: einfacher, hätte aber das
  Prüfkriterium "nachvollziehbar, nicht nur ein fixer Aufschlag" verletzt.
- **Produktive Stunden automatisch aus `erp_time_entries` berechnen**:
  exakter für die Vergangenheit, aber ungeeignet für eine
  vorausschauende Kalkulation im laufenden Jahr — zurückgestellt.
- **Automatische Übernahme des internen Stundensatzes in
  `erp_rates`**: hätte die geforderte Trennung von interner Kalkulation
  und externen Verrechnungssätzen aufgeweicht — bewusst nicht gebaut.
