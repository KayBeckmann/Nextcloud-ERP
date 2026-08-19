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

## Bekannte Einschränkungen dieses Stands

- Kein echtes fachliches ERP-Datenmodell (Projekte, Angebote, ...) — nur die
  Rechte-Matrix und die alte Platzhaltertabelle.
- Rechteprüfung existiert (Phase 2), aber nur für die Rechteverwaltung selbst
  durchgesetzt — fachliche Endpunkte (Projekte, Angebote, ...) prüfen die
  Matrix erst, wenn sie in späteren Phasen entstehen.
- Keine Contacts-/Calendar-/Files-Integration (Phase 3).
- Frontend-Bundle ist noch nicht auf Komponentenebene tree-geshaked (Warnung beim
  Build) — für den Skeleton-Stand nicht kritisch, sollte vor Phase 12
  (Web-Reifegrad) angegangen werden.
- Alle offenen Punkte aus der Roadmap ("Offene Klärungen vor Implementierung")
  sind über ADRs entschieden, mit Ausnahme von Themen, die erst in späteren
  Phasen konkret werden (Standard-MwSt.-Sätze, initiale Rollen, Angebotsschema,
  Rechnungsumfang) — die bleiben bewusst bis zur jeweiligen Phase offen.
