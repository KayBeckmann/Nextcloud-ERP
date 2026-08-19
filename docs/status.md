# Baufortschritt

Kurzer, laufend aktualisierter Stand — Details/Begründungen stehen in
[`roadmap.md`](roadmap.md) und den [ADRs](adr/).

## 2026-08-19 — Phase 0 + Phase 1 (Skeleton)

**Erledigt:**

- Monorepo-Struktur (`nextcloud/erp/`, `docs/`, `docker/`, `tests/`, `client/flutter/`)
- MIT-Lizenz, README mit Ziel/Scope/Nicht-Zielen
- 7 ADRs für die aus der Roadmap offenen Kernentscheidungen (App-ID, Nextcloud-Version,
  Datenbank, Frontend-Stack, Docker/Tests, Monorepo, Lizenz)
- Docker-Compose-Testumgebung: Nextcloud + PostgreSQL, `.env.example`, dokumentierte
  Start-/Test-Befehle in `docker/README.md`
- Nextcloud-App-Skeleton `erp`:
  - `appinfo/info.xml`, `appinfo/routes.php` (Seiten-Route + erster API-v1-Endpunkt)
  - `lib/AppInfo/Application.php`, `PageController`, `ApiController` (`GET /api/v1/status`)
  - erste Migration (`erp_app_meta`-Tabelle) als Beweis des Migrationsmechanismus
  - `composer.json` (PSR-4 `OCA\ERP\`)
- Web-UI-Grundgerüst: Vue 3 + `@nextcloud/vue`, App-Navigation für alle 16
  Hauptbereiche aus der Roadmap, Dashboard-Platzhalter mit den geplanten Kacheln,
  generische Platzhalteransicht für alle noch nicht gebauten Module
  (jeweils mit Verweis auf die zuständige Roadmap-Phase)
  - `npm run build` erfolgreich (nur Bundle-Size-Warnung, keine Fehler)
- PHPUnit-Grundgerüst: `ApplicationTest`, `PageControllerTest`, Migrations-Test

**Noch offen aus Phase 1:**

- Verifikation im laufenden Docker-Container (App aktivieren, Web-UI aufrufen,
  Tests im Container ausführen) — Docker-Image-Download lief noch, als dieser
  Stand geschrieben wurde
- CI-Workflow (GitHub Actions)

**Nicht begonnen:** Phase 2–14 (Rechte/API-Ausbau, Contacts/Calendar/Files-Integration,
Projektkern, Angebote/Artikel, Zeitwirtschaft, Rechnungen, Lager, Fuhrpark, Kosten,
Auswertungen, Web-Stabilisierung, Flutter).

## 2026-08-19 — Phase 2 (Identität, Rechte, API-Grundlage)

**Erledigt:**

- ADR-0008: Rechte-Modell (eigene `erp_permissions`-Tabelle, User+Gruppen,
  höchste Stufe gewinnt, NC-Admin-Fallback)
- Migration `erp_permissions` (principal_type/id, resource_type, permission,
  unique index)
- `PermissionResolver` — reine Auflösungslogik, DB-frei, 9 Unit-Tests
- `PermissionService` + `PermissionMapper`/`PermissionEntry` (QBMapper/Entity
  nach Nextcloud-Konvention), liest Nextcloud-User/Gruppen live aus
- API v1: `GET /permissions/principals`, `GET`+`PUT /permissions/matrix`
  (NC-Admin), `GET /permissions/me` (jeder eingeloggte User) — dokumentiert in
  `docs/api/v1.md`
- Web-UI: Rechte-Matrix ersetzt den Platzhalter unter "Berechtigungen & Sätze"
  (User-/Gruppenliste links, editierbare Matrix rechts)
- 19 neue Tests (Resolver, Service gegen echte DB, Controller-Attribut-
  Regressionstest, Migrationsstruktur) — alle grün, App-Gesamtstand: 23 Tests

**Verifiziert (nicht nur behauptet):**

- `curl` als NC-Admin (`kay`): principals/matrix lesen und schreiben
  funktioniert, Eintrag persistiert korrekt (upsert, "none" löscht den Eintrag)
- `curl` als extra angelegter Nicht-Admin-Testuser: `principals`/`matrix` → HTTP
  403, `me` → HTTP 200 mit korrekt aufgelösten Rechten (direkter User-Eintrag
  wirkt, Gruppenmitgliedschaft wirkt, beides zusammen: höchste Stufe gewinnt)
- Playwright-Klicktest: Rechte-Matrix-Seite rendert, Principal-Auswahl zeigt
  korrekte Matrix, keine Konsolenfehler

**Gotcha für lokale Entwicklung:** Nach `occ app:disable`/`app:enable` kann
Nextclouds Routen-Cache neue OCS-Routen ignorieren (Symptom: `statuscode 998
"Invalid query"` trotz korrektem `routes.php`) — hilft: Container neu starten
(`docker compose restart nextcloud`).

**Phase 2 laut Roadmap-Prüfkriterien vollständig**, nächster Schritt: Phase 3
(Contacts/Calendar/Files-Integration).

## 2026-08-19 — Phase 3 (Contacts, Calendar, Files)

**Erledigt:**

- ADR-0009: Contacts über `OCP\Contacts\IManager::search()`, Calendar über die
  seit Nextcloud 31 offizielle Schreib-API (`ICalendarEventBuilder`/
  `ICreateFromString`), Files über `IRootFolder`/`Folder::newFolder()` — alle
  drei gegen die tatsächlich installierte API verifiziert, nicht angenommen
- Migration `erp_contact_links` (Kunde/Lieferant ↔ Contact-UID + ERP-Metadaten)
  und `erp_calendar_links` (generisch: resourceType/resourceId ↔ Kalendertermin)
- Contacts: Suche, Link-CRUD, **Rechte-Matrix aus Phase 2 greift bereits**
  (Lesen ab `read` auf `Kunden`/`Lieferanten`, Schreiben ab `write`)
- Calendar: Kalenderliste, echten Termin anlegen (kein ICS-Handbau), generische
  Verknüpfung, ebenfalls rechte-gegated über den übergebenen `resourceType`
- Files: idempotente ERP-Ordnerstruktur (`ERP/Projekte`, `.../Artikel`, …) im
  User-Home
- Web-UI: "Kunden"/"Lieferanten" sind echte Ansichten (Contact-Suche + Matrix
  aus verknüpften Kontakten), "Einstellungen" hat einen echten
  "Dateien & Ordner"-Abschnitt
- 17 neue Tests (Services gegen echte DB/gemockte Nextcloud-APIs,
  Controller-Rechte-Gate-Logik) — App-Gesamtstand: 40 Tests

**Verifiziert (nicht nur behauptet):**

- Contacts: Link anlegen/lesen/ändern/löschen per `curl`; Rechte-Gate
  end-to-end mit eigens angelegtem Nicht-Admin-Testuser durchgespielt (403 ohne
  Recht → 200 nach `read`-Vergabe → weiterhin 403 bei Schreibversuch ohne
  `write`)
- Calendar: echten Termin über die API angelegt und visuell in Nextclouds
  eigener Calendar-App bestätigt (Termin erscheint am korrekten Datum)
- Contacts: per Playwright bestätigt, dass die verlinkten Kontakte identisch
  mit denen in Nextclouds eigener Contacts-App sind (kein Schatten-Datensatz)
- Files: echte Ordnerstruktur mit Datei-IDs angelegt, per Playwright über die
  Einstellungen-Seite ausgelöst und Ergebnis geprüft
- Playwright-Klicktest: Kunden-Suche, Verknüpfen, Einstellungen-Ordnercheck —
  keine Konsolenfehler

**Ein Umgebungs-Gotcha gefunden (nicht unser Code):** `files_external` war in
der frischen Docker-Instanz aktiviert, aber seine eigene Migration war nie
gelaufen (`oc_external_mounts` fehlte) — hat einen Testlauf mit frisch
angelegtem Testuser zum Absturz gebracht, weil das Anlegen eines neuen Users
alle Mount-Provider durchprobiert. Fix: `occ app:disable files_external &&
occ app:enable files_external` (löst die Migration erneut aus).

**Ehrliche Einschränkung zum Prüfkriterium "Projekt kann verknüpft werden":**
Es gibt in Phase 3 noch keine Projekt-Entität (kommt erst in Phase 4). Die
Verknüpfungsmechanik (Contacts-Link-Tabelle, generische
resourceType/resourceId-Kalenderverknüpfung) ist bewusst so gebaut, dass
Phase 4 sie ohne Schemaänderung direkt für echte Projekte mitnutzen kann —
end-to-end bewiesen wurde sie hier mit `kunden` als Platzhalter-Ressourcentyp.

## Bekannte Einschränkungen dieses Stands

- Kein echtes fachliches ERP-Datenmodell (Projekte, Angebote, ...) — nur die
  Rechte-Matrix und die alte Platzhaltertabelle.
- Rechteprüfung existiert (Phase 2), aber nur für die Rechteverwaltung selbst
  durchgesetzt — fachliche Endpunkte (Projekte, Angebote, ...) prüfen die
  Matrix erst, wenn sie in späteren Phasen entstehen.
- Contacts-/Calendar-/Files-Integration steht (Phase 3), aber ohne echte
  Projekt-Entität dahinter — Verknüpfung bisher nur gegen `kunden` bewiesen.
- Frontend-Bundle ist noch nicht auf Komponentenebene tree-geshaked (Warnung beim
  Build) — für den Skeleton-Stand nicht kritisch, sollte vor Phase 12
  (Web-Reifegrad) angegangen werden.
- Alle offenen Punkte aus der Roadmap ("Offene Klärungen vor Implementierung")
  sind über ADRs entschieden, mit Ausnahme von Themen, die erst in späteren
  Phasen konkret werden (Standard-MwSt.-Sätze, initiale Rollen, Angebotsschema,
  Rechnungsumfang) — die bleiben bewusst bis zur jeweiligen Phase offen.
