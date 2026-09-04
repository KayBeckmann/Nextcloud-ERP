# ADR-0024: Gruppenbasierte Freigaben — Adressbuch, Kalender, Dateien

**Status:** accepted
**Datum:** 2026-09-04

## Kontext

Praxistest auf der Docker-Entwicklungsumgebung zeigte drei zusammenhängende
Lücken:

1. Ein neuer User (`jan`) hatte trotz angelegtem ERP-Account **keinerlei**
   Zugriff — nicht weil etwas kaputt war, sondern weil ERP-Rechte bisher nur
   ad-hoc pro Einzeluser vergeben wurden (siehe Fixture-Skript aus Phase 14)
   statt über eine Gruppenstruktur, die neue User automatisch mit Rechten
   versorgt.
2. Kontakte: `ContactsService::search()` nutzt `IManager::search()` und
   findet damit, was auch immer für den aufrufenden User sichtbar ist —
   ohne ein bewusst geteiltes Adressbuch ist das je nach Installation
   uneinheitlich (ADR-0009 hatte das bereits als offenen Punkt notiert).
3. Kalender: ADR-0009 dokumentierte bereits als bekannte Einschränkung,
   dass Termine im Kalender des jeweils anlegenden Users landen, ohne dass
   andere (z. B. ein Projektleiter für Personalplanung) sie automatisch
   sehen — "bleibt eine offene Frage", nie nachgezogen.
4. Dateien: ADR-0009 dokumentierte ebenfalls bereits als bekannte
   Einschränkung, dass die `ERP/`-Ordnerstruktur im **persönlichen
   Home-Verzeichnis** des jeweiligen Users liegt statt an einem
   gemeinsamen Ort — "vor Phase 4 zu klären", ebenfalls nie nachgezogen.

Alle vier Punkte hängen an derselben fehlenden Grundlage: einer
Gruppenstruktur, an die sich Rechte, Freigaben und Speicherorte anlehnen
können.

## Entscheidung

### Gruppen

Zwei Nextcloud-Gruppen, passend zum bereits bestehenden Rollen-Zuschnitt
aus den Testdaten-Fixtures (Roadmap Phase 14):

- `erp-projektleiter` — volle operative Schreibrechte (Projekte, Angebote,
  Aufträge, Rechnungen, Lieferscheine, Kunden, Lieferanten, Artikel,
  Produkte, Lager, Fuhrpark, Stunden/Zeitkonto, Kosten/Kalkulation).
- `erp-monteure` — eingeschränkt (Stunden/Zeitkonto schreiben, Projekte
  und Kunden nur lesen), analog zum Fixture-Skript-Profil.

Rechte werden ab jetzt **auf die Gruppe**, nicht auf den einzelnen User
vergeben (`erp_permissions.principal_type = 'group'`) — das Rechtemodell
unterstützt das bereits seit ADR-0008 unverändert, nur die Provisionierung
hat es bisher nicht genutzt. Ein neuer User bekommt automatisch Zugriff,
sobald er einer der beiden Gruppen zugeordnet wird — kein manueller
Rechte-Einzelschritt pro User mehr nötig.

Weitere Rollen (z. B. eine reine Buchhaltungsgruppe) sind über dasselbe
Muster jederzeit ergänzbar, ohne Codeänderung — reine Admin-Provisionierung
(`occ group:add`, `occ group:adduser`, `PUT /permissions/matrix`).

### Adressbuch

Ein einzelnes, dediziertes Adressbuch "ERP Kontakte" (angelegt unter dem
Systemnutzer `admin`, `occ dav:create-addressbook admin erp-kontakte`),
freigegeben (read-write) an beide Gruppen. `ContactsService` selbst bleibt
unverändert — `IManager::search()` durchsucht automatisch alle für den
User sichtbaren Adressbücher, sobald eines geteilt ist. Die eigentliche
Arbeit ist reine **Provisionierung**, kein neuer App-Code.

### Kalender

Pro User ein dedizierter Kalender "ERP" (nicht der private Standardkalender
"Personal"), automatisch angelegt und mit `erp-projektleiter` (read-write)
geteilt, sobald der User zum ersten Mal einen ERP-Termin anlegt oder das
Dashboard lädt. `CalendarService::createEvent()` schreibt künftig gezielt
in diesen Kalender des jeweiligen Zielusers (`assignedUserId` falls
gesetzt, sonst der anlegende User) statt in einen beliebigen Kalender.
Bewusst **nicht** mit `erp-monteure` geteilt — ein Monteur muss nicht die
Termine aller anderen Monteure sehen, nur die Projektleitung braucht die
Gesamtübersicht für die Personalplanung (ADR-0020).

**Technische Abweichung von der bisherigen OCP-only-Regel (ADR-0009):**
`OCP\Calendar\IManager` bietet **keine** öffentliche API zum Anlegen oder
Freigeben von Kalendern (nur `createEventBuilder()` für Termine in
bereits existierenden Kalendern). Gegen die echte Nextcloud-34-Installation
verifiziert (nicht angenommen): Anlegen läuft über das interne
`OCA\DAV\CalDAV\CalDavBackend`, Freigeben über
`OCA\DAV\CalDAV\Sharing\Service::shareWith(int $resourceId, string
$principal, int $access)` — dieselbe interne API, die auch die
Nextcloud-eigene Kalender-App für Sharing nutzt. Beides sind `OCA\DAV`-,
keine `OCP`-Klassen, also keine langfristig garantierte Stabilitätszusage
über Nextcloud-Versionen hinweg. Für Adressbücher gilt dieselbe
Einschränkung (`OCA\DAV\CardDAV\CardDavBackend` +
`OCA\DAV\CardDAV\Sharing\Service`), betrifft dort aber nur die
einmalige Provisionierung, nicht laufenden App-Code.

### Dateien

`groupfolders`-App installieren, ein Group Folder "ERP" anlegen, beiden
Gruppen zuweisen (read-write). `ErpFolderService` baut die `ERP/`-Struktur
künftig **im Group Folder** statt im Home-Verzeichnis des aufrufenden
Users — Group Folders mounten sich transparent als normaler Ordner in
`IRootFolder::getUserFolder()`, sobald die Gruppe Zugriff hat, d. h. die
bestehende `Folder::newFolder()`/`nodeExists()`-Logik bleibt unverändert,
nur der Startpunkt ändert sich von `getUserFolder()` auf
`getUserFolder()->get('ERP-Firma')` (Group-Folder-Mountname).

**Migration bestehender Daten:** Fixture-/Testdaten aus Phase 14 (Projekte
unter `projektleiter-fixture`s Home-Verzeichnis) werden auf der
Docker-Testumgebung **nicht** automatisch migriert — reiner Neuaufbau auf
Testdaten, kein Produktivbestand betroffen. Für einen echten
Produktiv-Umstieg wäre ein einmaliges Datei-Verschieben nötig (nicht Teil
dieser Entscheidung).

## Konsequenzen

- Neue User brauchen ab jetzt nur noch eine Gruppenzuordnung, keine
  manuelle Rechte-Einzelvergabe mehr für den Normalfall.
- Kalender-/Adressbuch-Freigabe hängt an internen `OCA\DAV`-Klassen — bei
  künftigen Nextcloud-Major-Upgrades explizit gegenprüfen (kein
  öffentlicher API-Vertrag). Dokumentiert als bewusste, begründete
  Abweichung von ADR-0009s Grundsatz.
- `ErpFolderService` funktioniert nur noch korrekt, wenn der Group Folder
  "ERP" für den aufrufenden User sichtbar ist (Gruppenmitgliedschaft
  vorausgesetzt) — ein User ganz ohne `erp-projektleiter`/`erp-monteure`-
  Mitgliedschaft sieht keine ERP-Dateiablage mehr. Für diesen Fall bereits
  durch die ERP-Rechtematrix ohnehin ausgeschlossen (kein Zugriff auf
  Ressourcen, die Dateien referenzieren).
- Termine, die vor dieser Änderung im persönlichen "Personal"-Kalender
  eines Users lagen, werden nicht rückwirkend in den neuen "ERP"-Kalender
  verschoben.

## Alternativen erwogen

- **Eine einzige Gruppe "erp-mitarbeiter" statt zwei Rollen-Gruppen:**
  einfacher, aber keine Möglichkeit, Kalender-Sichtbarkeit oder
  Datei-Schreibrechte nach Rolle zu differenzieren — explizit gegen
  granulare Rollentrennung entschieden (Kay-Vorgabe 2026-09-04).
- **Kalender-Sharing per WebDAV-HTTP-Request statt interner PHP-Klassen:**
  gleiches Ergebnis, aber ein zusätzlicher Netzwerk-Hop gegen den eigenen
  Server plus HTTP-Auth-Handling — kein Vorteil gegenüber direktem
  PHP-Methodenaufruf über Dependency Injection, nur mehr Fehlerquellen.
- **Group Folders für Kontakte/Kalender statt CardDAV-/CalDAV-Sharing:**
  nicht anwendbar — Group Folders sind ein reines Dateisystem-Konzept, DAV-
  Ressourcen brauchen ihren eigenen Sharing-Mechanismus.
