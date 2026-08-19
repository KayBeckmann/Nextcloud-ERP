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

## Bekannte Einschränkungen dieses Stands

- Kein echtes ERP-Datenmodell — nur eine Platzhaltertabelle.
- Keine Rechteprüfung, keine Contacts-/Calendar-/Files-Integration.
- Frontend-Bundle ist noch nicht auf Komponentenebene tree-geshaked (Warnung beim
  Build) — für den Skeleton-Stand nicht kritisch, sollte vor Phase 12
  (Web-Reifegrad) angegangen werden.
- Alle offenen Punkte aus der Roadmap ("Offene Klärungen vor Implementierung")
  sind über ADRs entschieden, mit Ausnahme von Themen, die erst in späteren
  Phasen konkret werden (Standard-MwSt.-Sätze, initiale Rollen, Angebotsschema,
  Rechnungsumfang) — die bleiben bewusst bis zur jeweiligen Phase offen.
