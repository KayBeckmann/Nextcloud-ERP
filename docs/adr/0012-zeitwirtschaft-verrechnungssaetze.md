# ADR-0012: Zeitwirtschaft und Verrechnungssätze

**Status:** accepted
**Datum:** 2026-08-20

## Kontext

Roadmap Phase 6 verlangt: derselbe User kann je Arbeitsart unterschiedlich
abgerechnet werden, ein Kundenvertrag kann den Standardsatz überschreiben,
alte Stunden ändern sich nicht durch spätere Satzänderungen, und
Urlaub/Abwesenheit ist sichtbar mit dem Kalender verknüpft. Das
Brainstorming definiert eine 6-stufige Prioritätsregel für die
Satzermittlung.

## Entscheidung

### Verrechnungssätze — technisch getrennt von Rechten (ADR-0008 bleibt gültig)

`erp_standard_rates` (work_type_id, principal_type nullable [`user`/`group`],
principal_id nullable, rate): eine Zeile ohne `principal_type`/`principal_id`
ist der globale Satz für eine Arbeitsart — parallel zu, aber unabhängig von,
`erp_work_types.hourly_rate` aus Phase 5. Nur eine globale Zeile pro
Arbeitsart wird an der Anwendungsschicht erzwungen (kein DB-Constraint,
da NULL-Werte in Unique-Indizes je nach DB-Engine als "immer verschieden"
gelten).

`erp_customer_contracts` (customer_contact_uid, title, valid_from/until,
notes) + `erp_customer_contract_rates` (contract_id, work_type_id,
principal_type nullable, principal_id nullable, rate) — vertragliche
Sätze, die einen bestimmten Kunden betreffen.

### Priorisierung (`RateResolutionService`, reine Logik ohne DB)

Analog zu `PermissionResolver` (ADR-0008) und `QuoteCalculationService`
(ADR-0011): eine reine, DB-freie Klasse bekommt vorgeladene Listen und
liefert den zutreffenden Satz. Reihenfolge (erste Übereinstimmung gewinnt),
exakt wie im Brainstorming festgelegt:

1. Kundenvertragssatz für Arbeitsart + konkreten User
2. Kundenvertragssatz für Arbeitsart + Gruppe
3. Standardsatz für Arbeitsart + konkreten User
4. Standardsatz für Arbeitsart + Gruppe
5. Globaler Standardsatz für die Arbeitsart (`erp_standard_rates` ohne
   Principal, Fallback: `erp_work_types.hourly_rate`)
6. `0.0` als harter Fallback, falls nicht einmal die Arbeitsart einen Satz hat

### Snapshot-Prinzip (wie bei Angeboten, ADR-0011)

`erp_time_entries` speichert den zum Buchungszeitpunkt aufgelösten Satz
direkt in `rate_snapshot` — keine Live-Neuberechnung beim Anzeigen alter
Buchungen. Spätere Änderungen an Sätzen/Verträgen wirken nur auf neue
Buchungen.

### Zeitkonto — reiner Lesevorgang, keine gespeicherte Bilanz

`erp_work_schedules` (user_id, weekly_hours) hält nur das Wochensoll.
Ist-Stunden werden aus `erp_time_entries` für den angefragten Zeitraum
summiert; Soll wird aus `weekly_hours` und der Zeitraumlänge abgeleitet.
Plus-/Minusstunden = Ist − Soll, live berechnet — vermeidet
Inkonsistenzen zwischen gespeicherter Bilanz und tatsächlichen Buchungen
(gleiche Begründung wie bei den Angebotssummen in ADR-0011).

### Abwesenheiten nutzen die bestehende Calendar-Verknüpfung (ADR-0009)

Keine neue Verknüpfungstabelle: ein genehmigter Abwesenheitsantrag legt
optional einen Kalendertermin über den bestehenden `CalendarService` an und
verknüpft ihn über `erp_calendar_links` mit `resourceType = 'absence'`,
`resourceId = <request_id>` — derselbe Mechanismus, der in Phase 3 für genau
diesen Zweck vorbereitet wurde.

### Überstunden

`erp_overtime_actions` (user_id, hours, action_type [`compensate`/`payout`],
status [`requested`/`approved`/`done`], notes) — einfacher
Freigabe-Workflow ohne automatische Herleitung der Überstunden aus dem
Zeitkonto (das bliebe manuelle Eingabe/Entscheidung des Users/der
Freigabeperson, siehe "Nicht Teil dieser Phase").

### Rechte

- Sätze/Kundenverträge: `ResourceType::BerechtigungenSaetze` (der Name des
  Rechtebereichs passt wörtlich: "Berechtigungen **und Sätze**").
- Zeiterfassung/Zeitkonto/Abwesenheiten/Überstunden:
  `ResourceType::StundenZeitkonto`.

## Nicht Teil dieser Phase

- Automatische Ableitung von Überstunden aus dem Zeitkonto-Saldo (Soll/Ist
  bleibt Anzeige, keine automatische Buchung).
- Pausenregeln nach Arbeitszeitgesetz (§4 ArbZG) — reine Pausen-Erfassung als
  Freitextfeld auf der Zeitbuchung, keine automatische Berechnung/Prüfung.
- Verschiedene Arbeitszeitmodelle (Teilzeit-Staffelungen, Schichtpläne) über
  ein einzelnes `weekly_hours`-Feld hinaus.
- Lohnabrechnungs-Export (explizit spätere Roadmap-Phase).

## Alternativen erwogen

- Zeitkonto-Saldo als gespeicherte, bei jeder Buchung aktualisierte Zahl:
  mehr Komplexität (Neuberechnung bei nachträglicher Korrektur einer alten
  Buchung), kein Vorteil gegenüber Live-Berechnung bei den hier realistischen
  Datenmengen.
- Eigene `erp_absence_calendar_links`-Tabelle: unnötig, `erp_calendar_links`
  ist bereits generisch genug (ADR-0009).
