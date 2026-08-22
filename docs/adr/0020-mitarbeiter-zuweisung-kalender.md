# ADR-0020: Mitarbeiter-Zuweisung für Termine + Kollisionserkennung + Auftrags-Zuweisung

**Status:** accepted
**Datum:** 2026-08-22

## Kontext

Nutzeranforderung (2026-08-22): Im Projekt angelegte Termine sollen einem
Mitarbeiter zugewiesen werden können und dann in dessen eigenem
Nextcloud-Kalender erscheinen — nicht nur im Kalender des anlegenden
Users. Dabei soll eine Kollisionserkennung verhindern, dass ein
Mitarbeiter zeitgleich zwei Baustellen/Terminen zugewiesen wird. Ebenso
soll ein Auftrag innerhalb eines Projekts einem Mitarbeiter zugewiesen
werden können.

ADR-0009 hatte "Verknüpfung mit fremden Benutzerkalendern (Monteur
einplanen)" bereits als offene Frage benannt und bewusst zurückgestellt —
diese ADR löst sie auf.

## Entscheidung

### Termin im Kalender des zugewiesenen Users statt im eigenen

`CalendarService::createEvent()` bekommt einen neuen optionalen Parameter
`?string $assignedUserId`. Verhalten:

- **Nicht gesetzt** (Default): unverändertes Verhalten aus ADR-0009 — der
  Termin wird im selbst gewählten Kalender (`calendarUri`) des anlegenden
  Users angelegt.
- **Gesetzt**: der Termin wird im Kalender des **zugewiesenen** Users
  angelegt, nicht im Kalender des anlegenden Users. `OCP\Calendar\IManager`
  arbeitet rein über Principal-URIs (`principals/users/<uid>`) und ist
  nicht an die aktuelle HTTP-Session gebunden — ein Nextcloud-App-Backend
  kann damit Kalender jedes Users adressieren, ohne dessen Session zu
  brauchen. Der anlegende User (z. B. Projektleiter) kennt die
  Kalenderliste des Zielusers nicht und soll sie auch nicht auswählen
  müssen — es wird deshalb automatisch der Standardkalender mit der URI
  `personal` verwendet, den Nextcloud für jeden User beim ersten Zugriff
  auf die Calendar-App anlegt. Existiert er ausnahmsweise nicht oder ist
  nicht beschreibbar, wird der erste beschreibbare Kalender des Zielusers
  verwendet; existiert gar keiner, wirft der Service eine
  `OutOfBoundsException`.

### Kollisionserkennung nur über ERP-Termine, mit bewusster Zeitraum-Schattenkopie

`erp_calendar_links` bekommt drei neue, nullable Spalten:
`assigned_user_id`, `start_at`, `end_at` (beide als Unix-Timestamp, `int`).

Start/Ende eines Termins hier zusätzlich zum eigentlichen Kalender-Event
zu speichern ist eine bewusste Ausnahme von der Projekt-Leitplanke "keine
Schattenkopie ohne fachlichen Grund" (vgl. ADR-0009): Der fachliche Grund
ist die Kollisionserkennung selbst — sie braucht einen schnellen
Bereichs-Query ("welche Termine eines Users überschneiden sich mit
diesem Zeitraum") direkt in der eigenen DB. Ein Re-Query gegen das
Kalender-Backend jedes Zielusers wäre pro Anfrage teurer und würde
zusätzlich voraussetzen, dass der anlegende User Lesezugriff auf fremde
Kalender hat, was im Nextcloud-Rechtemodell nicht gegeben ist.

Vor dem Anlegen eines Termins mit `assignedUserId` prüft
`CalendarService`, ob für denselben `assigned_user_id` bereits ein
ERP-Termin mit überschneidendem Zeitraum existiert
(`start_at < neues Ende AND end_at > neuer Start`, offene Intervalle —
ein Termin, der exakt endet, wenn der nächste beginnt, gilt nicht als
Kollision). Bei einem Treffer wirft der Service eine `\DomainException`
mit Titel und Zeitraum des kollidierenden Termins in der Meldung; der
Controller mappt das auf `OCSPreconditionFailedException` (HTTP 412) —
dasselbe Muster, das dieses Projekt bereits für andere
Geschäftsregel-Ablehnungen verwendet (z. B.
`DeliveryNoteController`/`CreditNoteController`).

**Bewusste Einschränkung:** Die Kollisionserkennung deckt nur Termine ab,
die über die ERP-API angelegt wurden (also mit `assigned_user_id`
gefüllte `erp_calendar_links`-Zeilen) — nicht private oder sonstige
Termine des Users in Nextcloud Calendar. Eine vollständige
Frei/Belegt-Prüfung über `ICalendarManager::checkAvailability()` bleibt
weiterhin außerhalb des Scopes (bereits in ADR-0009 als "nicht
umgesetzt" vermerkt).

### Auftrag: assigned_user_id analog zu Project::responsibleUserId

`erp_orders` bekommt eine neue, nullable Spalte `assigned_user_id`.
`OrderService::createOrder()`/`updateOrder()` bekommen den Parameter,
`OrderController` reicht ihn durch. Kein technischer Zusammenhang zur
Kalender-Zuweisung — ein zugewiesener Auftrag legt nicht automatisch
einen Kalender-Termin an, das bleibt ein bewusst getrennter, expliziter
Schritt über den Termine-Tab (siehe "Nicht Teil dieser Phase").

### Web-UI

- Termine-Formular im Projekt (`ProjektDetailView`, Tab "Termine")
  bekommt einen `UserPicker` "Mitarbeiter zuweisen" (optional — leer
  lassen bedeutet weiterhin: Termin im eigenen Kalender wie bisher).
  Eine Kollision wird als Fehlermeldung mit der Server-Meldung (Titel +
  Zeitraum des kollidierenden Termins) angezeigt.
- `AuftragDetailView` bekommt im Übersicht-Formular einen `UserPicker`
  "Zugewiesener Mitarbeiter", analog zum bestehenden Muster bei
  `ProjektDetailView`/`VehicleService` (Fahrer-Zuweisung, ADR-0017).

## Nicht Teil dieser Phase

- Automatisches Anlegen eines Kalender-Termins beim Zuweisen eines
  Auftrags — bleibt ein separater, expliziter Schritt.
- Vollständige Frei/Belegt-Prüfung über alle Kalender eines Users
  (private Termine, Termine aus anderen Apps) — nur ERP-Termine werden
  auf Kollision geprüft.
- Ändern/Verschieben/Löschen eines bereits angelegten (und ggf.
  zugewiesenen) Termins — wie in ADR-0009 bereits vermerkt, weiterhin
  nicht umgesetzt.
- Massenzuweisung mehrerer Mitarbeiter zu einem Termin (Team-Einsatz) —
  genau ein `assigned_user_id` pro Termin.
- Benachrichtigung des zugewiesenen Mitarbeiters außerhalb des
  Kalender-Eintrags selbst (z. B. Push/E-Mail) — der Kalendereintrag im
  eigenen Kalender ist die Benachrichtigung.

## Konsequenzen

- `erp_calendar_links` verliert seinen bisher rein generischen Charakter
  (resourceType/resourceId + Kalender-Referenz) und bekommt mit
  `assigned_user_id`/`start_at`/`end_at` erstmals fachliche ERP-Logik
  (Kollisionserkennung) direkt auf der Verknüpfungstabelle — bewusst so
  entschieden, weil eine separate Tabelle nur für diese drei Spalten
  keinen zusätzlichen Nutzen hätte und die 1:1-Beziehung zum Termin
  ohnehin gegeben ist.
- Bestehende Zeilen in `erp_calendar_links` (aus Phase 3/4) haben
  `assigned_user_id`/`start_at`/`end_at` = `null` — sie zählen nicht in
  der Kollisionserkennung mit, was korrekt ist (sie waren nie einem
  Mitarbeiter zugewiesen).
