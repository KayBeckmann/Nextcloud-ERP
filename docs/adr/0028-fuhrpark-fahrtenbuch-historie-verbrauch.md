# ADR-0028: Fuhrpark-Erweiterungen — Fahrtenbuch, TÜV-Erinnerung, Zuweisungs-Historie, Kraftstoffverbrauch

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

ADR-0017 (Fuhrpark-Grundfunktionen: Fahrzeugstammdaten, Tankbelege) hatte
vier Punkte explizit zurückgestellt, die seitdem in `status.md` als offen
dokumentiert waren:

- Fahrtenbuch (einzelne Fahrten mit Zweck, Start/Ziel, Kilometerstand)
- Automatische TÜV-Erinnerung
- Fahrer-Zuweisungs-Historie (wer war wann welchem Fahrzeug zugeordnet)
- Kraftstoffverbrauchsstatistik

Alle vier gehören zum selben Domänenmodell (`Vehicle`) und berühren
dieselben Dateien (`VehicleService`, `VehicleController`,
`VehicleDetailView.vue`), daher ein gemeinsamer Branch/ADR statt vier
einzelner.

**Gesetzlicher Hintergrund Fahrtenbuch (§ 6 Abs. 1 Nr. 4 Satz 3 EStG):**
Wer die private Nutzung eines Firmenfahrzeugs nicht pauschal über die
1-%-Regelung versteuern will, muss ein ordnungsgemäßes Fahrtenbuch
führen — mit Datum, Fahrtzweck (dienstlich/privat), Start, Ziel und
Kilometerstand je Fahrt. Das `purpose`-Feld (`business`/`private`)
bildet genau diese Unterscheidung ab; das System erzeugt daraus aber
keine Steuerberechnung (siehe "Nicht Teil dieser Phase").

## Entscheidung

### Fahrtenbuch

Neue Tabelle `erp_vehicle_trips` (Migration `Version0022`), Entity
`VehicleTrip`, Mapper `VehicleTripMapper`. Felder: `vehicleId`,
`tripDate`, `driverUserId` (nullable — Fahrer kann abweichen von
`assignedUserId`), `purpose` (`business`|`private`), `startLocation`,
`destination`, `startMileageKm`, `endMileageKm`, `notes`, `createdBy`,
`createdAt`.

`distanceKm` ist **kein eigenes DB-Feld**, sondern wird in
`jsonSerialize()` als `endMileageKm - startMileageKm` berechnet — selbes
Prinzip wie `remainingDue` in ADR-0027: eine abgeleitete Zahl wird nicht
redundant gespeichert, um Drift zwischen Spalte und Herleitung
auszuschließen.

`VehicleService::recordTrip()` validiert `purpose` gegen eine feste
Liste, verlangt nicht-leere `startLocation`/`destination` und
`endMileageKm >= startMileageKm`. Jede erfasste Fahrt mit
`endMileageKm > Vehicle::currentMileageKm` hebt den gespeicherten
Kilometerstand an (dieselbe "nur-wenn-höher"-Regel wie bei Tankbelegen,
ADR-0017) — niedrigere/nachträglich erfasste Fahrten werden akzeptiert,
regressieren den Stand aber nicht.

### TÜV-Erinnerung

**Kein eigenes Benachrichtigungssystem** (kein Push/E-Mail/Cron) — das
Projekt hat an keiner Stelle Infrastruktur dafür, und sie für genau
diesen einen Anwendungsfall neu einzuführen wäre unverhältnismäßig.
Stattdessen Erweiterung des bereits bestehenden
`ReportingService::dashboardSummary()`-Musters (`vehiclesDueSoon`
existierte schon seit ADR-0017/19):

- `vehiclesOverdue` (int) — Fahrzeuge, deren `nextInspectionDate` in der
  Vergangenheit liegt.
- `vehicleInspections` — nach Datum sortierte, auf fällige/überfällige
  Fahrzeuge **begrenzte** Liste (`{id, licensePlate, nextInspectionDate,
  overdue}`), damit die Dashboard-Kachel kurz bleibt. Enthält nicht alle
  Fahrzeuge, nur die relevanten.

`vehiclesDueSoon` bleibt unverändert (Abwärtskompatibilität bestehender
Frontend-Nutzung).

### Fahrer-Zuweisungs-Historie

Neue Tabelle `erp_vehicle_assignments` (dieselbe Migration), Entity
`VehicleAssignment`, Mapper `VehicleAssignmentMapper`. Offenes/
geschlossenes Zeitraum-Muster: `unassignedAt IS NULL` bedeutet "aktuell
aktiv"; `findOpen()` findet die gerade offene Zuweisung eines
Fahrzeugs.

`VehicleService::create()` öffnet bei Angabe eines `assignedUserId`
sofort eine Zuweisung. `update()` vergleicht den neuen mit dem
bisherigen `assignedUserId`:

- **unverändert** → keine neue Historie-Zeile (verhindert
  Zuweisungs-Spam bei jedem `update()`, der aus anderem Grund erfolgt,
  z. B. Statusänderung).
- **geändert auf einen anderen User** → bisherige offene Zuweisung wird
  geschlossen (`unassignedAt = jetzt`), neue geöffnet.
- **geändert auf `null`** → bisherige offene Zuweisung wird geschlossen,
  keine neue geöffnet.

### Kraftstoffverbrauchsstatistik

`VehicleService::fuelConsumptionStats()` liest die bestehenden
`VehicleFuelLog`-Einträge eines Fahrzeugs (chronologisch), berechnet je
Beleg `distanceKm` zum vorangegangenen Beleg und daraus
`consumptionL100km = liters / distanceKm * 100`. Der erste (oder
einzige) Beleg hat `consumptionL100km = null` — ohne Vorgänger keine
Distanz, keine Berechnung möglich. `averageL100km` ist der Durchschnitt
aller berechenbaren Einträge (`null`, wenn keiner berechenbar ist).

**Bekannte Einschränkung, bewusst in Kauf genommen:** Die Berechnung
unterstellt, dass bei jedem Tankvorgang vollgetankt wird (nur dann ist
"Liter seit letztem Beleg" gleich "Verbrauch seit letztem Beleg"). Das
System kann das nicht validieren — es gibt kein Tankfüllstand-Feld. Für
eine grobe betriebliche Übersicht (nicht für steuerliche/rechtliche
Zwecke) ausreichend.

`VehicleService::getFull()` liefert `trips`, `assignmentHistory` und
`fuelConsumption` zusätzlich zu den bisherigen Feldern.

## Nicht Teil dieser Phase

- **Keine Push-/E-Mail-Benachrichtigung** für TÜV-Fälligkeit — nur
  Dashboard-Sichtbarkeit (siehe oben). Bei Bedarf eigene ADR, sobald das
  Projekt eine allgemeine Notification-Infrastruktur hat.
- **Keine Fahrtenbuch-Auswertung für die 1-%-/Fahrtenbuchmethode** —
  `purpose` wird erfasst und angezeigt, aber keine automatische
  Steuerberechnung (zu versteuernder privater Nutzungsanteil o. Ä.).
  **Vor produktivem Einsatz für die tatsächliche Fahrtenbuchmethode
  nach §6 EStG mit dem Steuerberater abstimmen** — insbesondere, ob die
  erfassten Felder den formalen Anforderungen eines "ordnungsgemäßen"
  Fahrtenbuchs genügen (z. B. Lückenlosigkeit, Nachträglichkeits-Schutz
  vor Manipulation — dieses System erlaubt Bearbeiten/Löschen von
  Fahrten ohne Revisionssicherheit).
- **Kraftstoffverbrauch unterstellt Volltanken** (siehe oben) — keine
  Validierung, keine Korrektur für Teilbetankungen.
- **Keine Validierung der Zuweisungs-Historie gegen Kalender/Abwesenheit**
  — ein Fahrer kann theoretisch einem Fahrzeug zugewiesen sein, während
  er laut `AbsenceRequestService` abwesend ist; keine Prüfung.

## Konsequenzen

- Migration `Version0022Date20261001150000` legt `erp_vehicle_trips`
  und `erp_vehicle_assignments` an.
- `VehicleService`-Konstruktor erweitert um `VehicleTripMapper` und
  `VehicleAssignmentMapper` — alle bestehenden Aufrufstellen
  (`VehicleServiceTest`, `ReportingServiceTest`) mussten angepasst
  werden.
- `ReportingService::dashboardSummary()` liest zusätzlich
  `VehicleFuelLogMapper`-Daten für `fuelCostsThisMonth` (bereits
  vorhanden) sowie die neuen Inspektions-Felder.
- Neue Routen `POST`/`DELETE /vehicles/{vehicleId}/trips...`.
- **Migrations-Fallstrick (siehe auch ADR-0025/0026):** Die
  Entity-Setter von Nextcloud (`OCP\AppFramework\Db\Entity::setter()`)
  markieren ein Feld nur dann als "geändert" (und damit für `INSERT`
  relevant), wenn der neue Wert **vom PHP-Property-Default abweicht**.
  `VehicleTrip::$purpose` hatte den Default `'business'` — der
  häufigste reale Wert. Ein Trip mit `purpose = 'business'` wurde daher
  beim `INSERT` komplett ausgelassen, was gegen den `NOT NULL`
  Constraint lief. **Fix:** DB-Spalten-Default muss immer den
  PHP-Property-Default spiegeln (wie es `Vehicle::$vehicleType`/
  `$status` bereits vorgemacht hatten) — `purpose`, `startMileageKm`
  und `endMileageKm` haben jetzt passende `'default' => ...` in der
  Migration. Generelle Lehre für künftige Entities: **jedes
  NOT-NULL-Feld mit einem nicht-trivialen PHP-Default braucht denselben
  Wert als DB-Default**, sonst bricht der häufigste/Standardfall.

## Alternativen erwogen

- **Fahrtenbuch-Einträge unveränderlich machen (wie Rechnungen,
  ADR-0013):** verworfen für diese Phase — ohne Revisionssicherheits-
  Infrastruktur (Signatur, Sperrfrist) wäre "unveränderlich in der App"
  nur Kosmetik, würde aber Korrekturen von Erfassungsfehlern
  unnötig erschweren. Stattdessen der Hinweis in "Nicht Teil dieser
  Phase", dies vor echtem steuerlichem Einsatz zu klären.
- **Eigene `distance_km`-Spalte statt Berechnung:** verworfen, gleiches
  Drift-Argument wie ADR-0027.
- **TÜV-Erinnerung als eigener Cron-Job mit E-Mail:** verworfen, da
  unverhältnismäßig für ein System ohne bestehende
  Notification-Pipeline; Dashboard-Sichtbarkeit deckt den Bedarf
  "proaktiv erinnert werden, wenn man sich einloggt" ab.
