# Übergreifende Tests / Fixtures

App-eigene PHPUnit-Tests liegen bei der App selbst unter
[`../nextcloud/erp/tests/`](../nextcloud/erp/tests/).

Dieser Ordner ist für Tests/Fixtures gedacht, die über eine einzelne App
hinausgehen — z. B. später:

- End-to-End-Szenarien gegen die laufende Docker-Testumgebung
- gemeinsame Seed-/Fixture-Daten für mehrere Testarten
- API-Vertragstests, sobald `docs/api/` existiert

Noch leer — wird ab Phase 2 (API-Grundlage) befüllt.
