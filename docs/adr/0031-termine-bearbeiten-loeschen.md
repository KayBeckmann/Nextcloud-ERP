# ADR-0031: Bearbeiten/Löschen bereits angelegter ERP-Termine

**Status:** accepted
**Datum:** 2026-10-01

## Kontext

ADR-0020 und schon vorher ADR-0009 hatten "Ändern/Verschieben/Löschen
eines bereits angelegten Termins" explizit als nicht umgesetzt vermerkt.
`status.md` führte das seitdem als offene Einschränkung.

**Technischer Befund beim Umsetzen:** Nextclouds öffentliche
`OCP\Calendar`-API (`IManager`, `ICalendar`, `ICreateFromString`, Stand
Nextcloud 34.0.3) bietet dafür keinen unterstützten Weg:

- Es gibt **keine** Methode zum Löschen eines Termins.
- `ICreateFromString::createFromString()` ist entgegen der ersten
  Vermutung ("gleiche `event_uri` erneut schreiben überschreibt den
  Termin") **kein Update-Mechanismus** — die Implementierung
  (`OCA\DAV\CalDAV\CalendarImpl::createFromString()`) ruft intern
  Sabres `$server->createFile()` auf, das bei einer bereits
  existierenden Datei mit `Sabre\DAV\Exception\Conflict` abbricht; der
  Aufrufer fängt das ab und wirft `CalendarException('Could not create
  new calendar event: ...')`. Ein zweiter Aufruf mit derselben URI
  schlägt also **immer** fehl.

Damit ist "Bearbeiten/Löschen" über die öffentliche API in dieser
Nextcloud-Version nicht realisierbar — nicht nur eine Aufwandsfrage,
sondern eine echte Plattformlücke. Das wurde mit Kay abgestimmt (siehe
"Alternativen erwogen" für die verworfenen Optionen); Entscheidung: auf
`OCA\DAV\CalDAV\CalDavBackend` ausweichen, die interne Klasse, die auch
der reguläre CalDAV-PUT/DELETE-Handler von Nextcloud selbst beim
Bearbeiten/Löschen eines Termins im Kalender-Client verwendet.

## Entscheidung

### `CalDavBackend::updateCalendarObject()`/`deleteCalendarObject()` statt OCP

`CalendarService` bekommt `OCA\DAV\CalDAV\CalDavBackend` als neue
Konstruktor-Abhängigkeit (über Nextclouds Autowiring auflösbar, kein
manuelles Service-Registration nötig — verifiziert via
`\OC::$server->get(CalDavBackend::class)`).

- **Bearbeiten/Verschieben** (`updateEvent()`): baut über den weiterhin
  öffentlichen `ICalendarEventBuilder` (`toIcs()` statt
  `createInCalendar()`) eine neue ICS-Nachricht und schreibt sie per
  `CalDavBackend::updateCalendarObject($calendarId, $eventUri,
  $icsString)` — derselbe interne Pfad, den auch ein echter
  CalDAV-Client-PUT durchläuft (Sync-Token-Bump, ETag, etc. inklusive).
- **Löschen** (`deleteEvent()`): `CalDavBackend::deleteCalendarObject($calendarId,
  $eventUri)`, anschließend wird die `erp_calendar_links`-Zeile entfernt.

Beide Methoden brauchen die interne numerische `calendarId` (nicht nur
die URI) — `CalDavBackend::getCalendarByUri($principalUri,
$calendarUri)` liefert sie.

**Bekannte Einschränkung durch den Builder-Umweg:** `toIcs()` erzeugt
intern eine **neue** VEVENT-`UID`, es gibt keinen öffentlichen
UID-Setter auf `ICalendarEventBuilder`. Die Datei-URI (und damit die
ERP-Verknüpfung) bleibt beim Bearbeiten stabil, nur die `UID` innerhalb
der ICS-Daten ändert sich mit. Für dieses Projekt unschädlich (keine
Einladungen/Wiederholungen, die über UID referenzieren), aber ein
Caldav-Client, der strikt über UID statt URI abgleicht, könnte den
bearbeiteten Termin theoretisch als neuen Termin interpretieren. Nicht
beobachtet, aber dokumentiert.

### Besitzer-Principal nachträglich bestimmen: neue Spalte `created_by_user_id`

Ein Termin ohne `assigned_user_id` (Default-Fall: landet im eigenen
Kalender des anlegenden Users) speicherte bisher **nirgends**, welcher
User ihn angelegt hat — `createEvent()` kannte den anlegenden User nur
über die aktive Session im Moment des Aufrufs, nie persistiert. Für
`updateEvent()`/`deleteEvent()` muss aber nachträglich wieder bestimmt
werden können, in wessen Kalender der Termin liegt.

Migration `Version0023Date20261001160000` fügt `erp_calendar_links.
created_by_user_id` (nullable) hinzu; `createEvent()` setzt sie ab
sofort. Besitzer-Principal-Auflösung (`ownerUserId()`):
`assignedUserId ?? createdByUserId`.

**Bestehende Zeilen aus der Zeit vor diesem ADR** haben
`created_by_user_id = null` und — sofern auch nie zugewiesen — keinen
bestimmbaren Besitzer mehr. `updateEvent()`/`deleteEvent()` werfen für
solche Zeilen eine `\OutOfBoundsException` mit erklärender Meldung,
statt zu raten oder in den falschen Kalender zu schreiben.

### Kollisionserkennung beim Verschieben

`CalendarLinkMapper::findOverlapping()` bekommt einen optionalen
`$excludeId`-Parameter — beim Verschieben eines bereits zugewiesenen
Termins muss er sich nicht selbst als Kollision gegen seine eigene
(noch nicht aktualisierte) Zeile zählen.

### Rechteprüfung

`CalendarController::updateEvent()`/`deleteEvent()` leiten den
`resourceType` für die Rechteprüfung aus der **gespeicherten**
Verknüpfung ab (`calendarService->getLink($id)->getResourceType()`),
nicht aus einem vom Aufrufer frei wählbaren Request-Parameter wie bei
`createEvent()` — ein Aufrufer soll nicht durch einen falschen
`resourceType`-Parameter eine schwächere Rechteprüfung erzwingen
können.

### Web-UI

`ProjektDetailView` (Tab "Termine") und `VehicleDetailView`
(TÜV-/Werkstatttermine) bekommen je einen "Bearbeiten"-Button
(Inline-Formular: Titel/Start/Ende) und einen Lösch-Button pro
Termin-Zeile.

## Nicht Teil dieser Phase

- **Vollständige Frei/Belegt-Prüfung über alle Kalender eines Users**
  (private Termine, Termine aus anderen Apps) bleibt außerhalb des
  Scopes — unverändert zu ADR-0020, und bewusst nicht in diesem ADR mit
  angegangen: das würde Lesezugriff auf fremde, nicht ERP-verwaltete
  Kalenderinhalte voraussetzen, eine eigene Datenschutz-Abwägung wert.
- **Automatisches Anlegen eines Kalender-Termins beim Zuweisen eines
  Auftrags** bleibt wie in ADR-0020 festgelegt ein bewusst getrennter,
  expliziter Schritt — keine offene Frage, sondern eine getroffene
  Entscheidung.
- **Keine UID-Stabilität der ICS-Daten** beim Bearbeiten (siehe oben) —
  akzeptierter Nebeneffekt des Builder-basierten Ansatzes.
- **Keine Bearbeitung/Löschung von Terminen ohne bestimmbaren Besitzer**
  (Zeilen von vor diesem ADR ohne `assigned_user_id` und ohne
  `created_by_user_id`) — wirft `OutOfBoundsException`, kein
  automatischer Rückfall auf z. B. den aktuell anfragenden User (wäre
  im Zweifel falsch).
- **Massenzuweisung mehrerer Mitarbeiter pro Termin** — weiterhin wie in
  ADR-0020, unverändert.

## Konsequenzen

- `CalendarService` hängt jetzt von einer App-internen Klasse
  (`OCA\DAV\CalDAV\CalDavBackend`) statt ausschließlich von `OCP`-
  Interfaces ab — bricht mit dem bisherigen Clean-OCP-Prinzip dieses
  Projekts (bewusste Abwägung, siehe Kontext/Alternativen). Ein
  Nextcloud-Major-Upgrade könnte diese Klasse oder ihre Methodensignaturen
  ändern, ohne dass das von Nextclouds BC-Garantien gedeckt wäre — anders
  als bei jeder anderen Service-Integration in diesem Projekt. Bei
  zukünftigen Nextcloud-Upgrades diesen Service gezielt gegenprüfen.
- Alle manuellen Testaufrufstellen von `CalendarService`
  (`CalendarServiceTest`, `AbsenceRequestServiceTest`,
  `ReportingServiceTest`) mussten um den neuen Parameter ergänzt werden.
- Neue Routen `PUT`/`DELETE /api/v1/calendar/events/{id}`.

## Alternativen erwogen

- **Nur Bearbeiten über `createFromString()` mit derselben `event_uri`,
  kein Löschen:** ursprünglich mit Kay abgestimmte erste Variante —
  verworfen, nachdem die Gegenprüfung der tatsächlichen
  `CalendarImpl::createFromString()`-Implementierung ergab, dass das
  **immer** mit einem Conflict fehlschlägt (siehe Kontext). Die Prämisse
  war falsch, daher zurück an Kay zur erneuten Entscheidung — Ergebnis:
  interne API nutzen (diese ADR).
- **"Alter Termin bleibt bestehen, neuer wird zusätzlich angelegt, alte
  ERP-Verknüpfung als 'ersetzt' markiert"-Workaround:** verworfen — der
  alte Kalendereintrag bliebe für den betroffenen User sichtbar und
  verwirrend, ohne jede Lösch-Möglichkeit (dasselbe Grundproblem bleibt
  bestehen, nur kosmetisch im ERP versteckt).
- **Direkter SQL-Zugriff auf `oc_calendarobjects`** statt über
  `CalDavBackend`: verworfen — `CalDavBackend::updateCalendarObject()`/
  `deleteCalendarObject()` kümmern sich zusätzlich um Sync-Token,
  `firstoccurence`/`lastoccurence`, Event-Dispatching
  (`CalendarObjectDeletedEvent` etc.) und (bei `deleteCalendarObject()`)
  die Aufbewahrungsfrist/Papierkorb-Logik — all das bei direktem
  SQL-Zugriff selbst nachzubauen wäre fehleranfälliger als die ohnehin
  schon interne Methode zu nutzen, die genau dafür existiert.
