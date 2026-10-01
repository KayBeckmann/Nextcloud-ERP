# ADR-0033: Resturlaub-Zähler

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

`status.md` führte seit ADR-0012 als bekannte Einschränkung: "Kein
Resturlaub-Zähler — `AbsenceType::affectsVacationBalance` ist aktuell
nur ein Flag ohne eigene Berechnung/Anzeige des verbleibenden
Kontingents."

Das Flag existierte bereits (ADR-0012), wurde aber nirgends
ausgewertet — ein Abwesenheitsantrag mit einem urlaubswirksamen Typ
hatte keine Auswirkung auf irgendeine Anzeige.

## Entscheidung

### Jahresanspruch je User: `VacationEntitlement`

Neue Tabelle `erp_vacation_entitlements` (Migration `Version0025`),
Entity `VacationEntitlement`, Mapper `VacationEntitlementMapper`,
Service `VacationEntitlementService` — **1:1 das Muster von
`WorkSchedule`/`WorkScheduleService`** (ADR-0012): ein Wert je User,
kein Jahresverlauf, `getForUser()` liefert immer ein Ergebnis (Fallback
auf den Default, wenn nichts hinterlegt ist).

**Default 20 Tage** = gesetzlicher Mindesturlaub bei einer 5-Tage-Woche
(§ 3 BUrlG) — ein neutraler, rechtlich begründbarer Startwert, **keine
betriebliche Empfehlung**. Vor produktivem Einsatz muss je User der
tatsächliche vertragliche Anspruch gepflegt werden
(`PUT /vacation-entitlement`).

### Verbrauch wird live berechnet, kein gespeicherter Zähler

Analog zu `TimeAccountService` (ADR-0012: "kein gespeicherter Saldo")
gibt es keinen mitgeführten "bereits verbrauchte Tage"-Zähler. Neue
reine Berechnungsklasse `OCA\ERP\Absence\VacationBalanceCalculator`
(kein DB-Zugriff, wie `TimeAccountCalculator`) nimmt den Jahresanspruch
und eine Liste bereits gefilterter `{startDate, endDate}`-Zeiträume und
liefert `{entitlementDaysPerYear, usedDays, remainingDays}`.

**Werktage-Zählung wiederverwendet, nicht dupliziert:**
`TimeAccountCalculator::countWorkdays()` wurde `public` gemacht und wird
direkt von `VacationBalanceCalculator` genutzt — dieselbe Mo–Fr-
Definition ohne Feiertagskalender gilt für Zeitkonto und
Urlaubsverbrauch gleichermaßen (ADR-0012 "Nicht Teil dieser Phase" gilt
hier unverändert mit).

Neuer orchestrierender Service `VacationBalanceService::getForUser(string
$userId, int $year)`:

1. Liest alle `AbsenceType`s mit `affectsVacationBalance = true`.
2. Liest alle `AbsenceRequest`s des Users mit `status = 'approved'` und
   einem dieser Typen.
3. Filtert auf Anträge, deren `startDate` im angefragten Jahr liegt.
4. Übergibt die gefilterten Zeiträume an `VacationBalanceCalculator`.

**Nur `status = 'approved'` zählt** — ein offener (`requested`) Antrag
reduziert den Resturlaub nicht, erst die Genehmigung. Das ist eine
bewusste Vereinfachung: keine separate "vorgemerkt/reserviert"-Spalte
wie z. B. bei Lagerbeständen (`quantityReserved`); wer wissen will, wie
viele Tage bei Genehmigung aller offenen Anträge übrig blieben, muss das
derzeit selbst gegenrechnen (siehe "Nicht Teil dieser Phase").

### API + Web-UI

`AbsenceController` bekommt `vacationBalance()`/`vacationEntitlement()`/
`setVacationEntitlement()` — Rechte-Gate exakt wie
`TimeAccountController::schedule()`/`setSchedule()` ("eigene Daten ab
Read, fremde ab Approve"). Neue Routen `GET /vacation-balance`,
`GET`/`PUT /vacation-entitlement`.

`StundenZeitkontoView` zeigt im Tab "Urlaub & Abwesenheit" eine
Resturlaub-Kachel (Anspruch/Genommen/Rest fürs laufende Jahr) oberhalb
des bestehenden Antragsformulars. **Kein Web-UI-Formular zum Setzen des
Jahresanspruchs** — `work-schedule` (das strukturell identische,
bereits länger bestehende Feature) hat ebenfalls keines, nur die API;
dieselbe Scope-Entscheidung wird hier übernommen, um keine Inkonsistenz
zwischen zwei eng verwandten Features einzuführen.

## Nicht Teil dieser Phase

- **Keine Berücksichtigung offener (`requested`) Anträge** im Zähler —
  nur genehmigte zählen. Keine "reservierter Resturlaub bei Annahme
  aller offenen Anträge"-Vorschau.
- **Kein anteiliges Splitten von Anträgen über den Jahreswechsel** — ein
  Antrag zählt komplett zu dem Jahr, in dem er beginnt. Ein Antrag z. B.
  vom 29.12. bis 03.01. würde alle Werktage dem Startjahr zuschlagen.
  Seltener Grenzfall, nicht gesondert behandelt.
- **Kein Übertrag von Resturlaub ins Folgejahr** — jedes Jahr wird für
  sich betrachtet (`entitlementDaysPerYear` ist ein fester Jahreswert,
  keine Verrechnung mit dem Vorjahressaldo).
- **Kein Feiertagskalender** (unverändert ADR-0012) — Werktage bleiben
  Mo–Fr.
- **Kein Web-UI-Formular zum Setzen des Jahresanspruchs** (siehe oben) —
  nur über die API, wie bei `work-schedule`.
- **Keine Pro-Rata-Berechnung** bei unterjährigem Eintritt/Austritt —
  `daysPerYear` ist der volle Jahreswert, unabhängig vom tatsächlichen
  Beschäftigungszeitraum.

## Konsequenzen

- `TimeAccountCalculator::countWorkdays()` ist jetzt `public` (vorher
  `private`) — einzige Signaturänderung an einer bestehenden Klasse,
  rein erweiternd (keine bestehenden Aufrufer betroffen).
- Neue, vom Rest des Projekts unabhängige Dateigruppe
  (`VacationEntitlement*`, `VacationBalance*`) — keine bestehenden
  Services mussten für dieses Feature geändert werden außer der
  Sichtbarkeits-Änderung oben und der Erweiterung von
  `AbsenceController`.

## Alternativen erwogen

- **Gespeicherter Zähler, der bei jeder Genehmigung dekrementiert
  wird:** verworfen — widerspricht dem im Projekt etablierten Prinzip
  "aus vorhandenen Daten live ableiten, nicht redundant mitführen"
  (`TimeAccountService`, `isOverdue()` bei Rechnungen, `remainingDue`
  bei ADR-0027). Ein gespeicherter Zähler könnte durch nachträgliches
  Bearbeiten/Löschen von Anträgen aus dem Takt geraten.
- **Jahresanspruch als Teil von `AbsenceType` statt eigener
  User-Tabelle:** verworfen — der Anspruch ist eine Eigenschaft des
  Mitarbeiters (Vertrag), nicht des Abwesenheitstyps; mehrere User mit
  demselben Abwesenheitstyp "Urlaub" haben typischerweise
  unterschiedliche Jahresansprüche.
