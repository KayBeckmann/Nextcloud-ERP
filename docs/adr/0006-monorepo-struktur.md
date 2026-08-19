# ADR-0006: Monorepo-Struktur

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

App, API, Datenmodell, Tests, Docs und später der Flutter-Client hängen eng
zusammen, besonders solange die API-Verträge sich noch entwickeln.

## Entscheidung

Ein einziges Repository mit folgender Top-Level-Struktur:

```text
repo/
├── nextcloud/erp/     Nextcloud-App/Add-on (PHP + Vue)
├── docs/               Architektur, API, ADRs, Roadmap
├── docker/             Docker/Compose-Testumgebung
├── tests/              übergreifende Tests / Fixtures
├── client/flutter/     späterer Flutter-Client (aktuell nur Doku/Platzhalter)
├── LICENSE
└── README.md
```

## Konsequenzen

- Ein Commit kann App-Änderung, API-Doku und Testfixture zusammen versionieren.
- `client/flutter/` bleibt bis Phase 13/14 der Roadmap ein reiner
  Doku-/Platzhalterordner, kein Flutter-Code.
- Falls der Flutter-Client später einen komplett eigenen Release-/CI-Zyklus
  braucht, kann er in ein eigenes Repo ausgelagert werden — das ist zu diesem
  Zeitpunkt keine Sackgasse, da der Ordner heute keine harten Abhängigkeiten
  Richtung App-Code hat.

## Alternativen erwogen

- Separate Repos für App/API/Flutter von Anfang an: mehr Overhead für
  Versionsabgleich, unnötig, solange Flutter noch nicht existiert.
