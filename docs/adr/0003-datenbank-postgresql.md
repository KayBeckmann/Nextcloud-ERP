# ADR-0003: PostgreSQL als Datenbank für die Testumgebung

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

Die Roadmap nennt PostgreSQL oder MariaDB als mögliche Docker-Testbasis, ohne sich
festzulegen. Nextcloud unterstützt offiziell beide.

## Entscheidung

PostgreSQL wird als primäre Datenbank für Entwicklung, Docker-Testumgebung und CI
verwendet.

Gründe:

- Das ERP-Datenmodell (Angebotsgruppen, Verrechnungssatz-Priorisierung,
  Lagerbestand Soll/Ist, Snapshot-Konsistenz) profitiert von PostgreSQLs stärkeren
  Constraint-/Transaktions-/JSON-Funktionen gegenüber MariaDB.
- Die offizielle Nextcloud-Dokumentation empfiehlt PostgreSQL für neue,
  anspruchsvollere Installationen.
- Ein Datenbanksystem für alle Umgebungen (statt MariaDB-Doku parallel zu pflegen)
  hält die Dokumentation und CI einfacher.

## Konsequenzen

- `docker/docker-compose.yml` nutzt `postgres` als DB-Service.
- SQL-Migrationen werden primär gegen PostgreSQL getestet. Nextclouds
  `IMigrationStep`/QueryBuilder-Abstraktion soll trotzdem DB-agnostisch bleiben,
  reine PostgreSQL-Spezifika werden vermieden, falls ein Nutzer später MariaDB
  einsetzen möchte — das ist aber keine getestete/garantierte Kompatibilität.

## Alternativen erwogen

- MariaDB: weiter verbreitet in bestehenden Nextcloud-Installationen, aber ohne
  fachlichen Vorteil für dieses Projekt. Bleibt als möglicher Community-Beitrag
  offen, wird aber nicht selbst gepflegt/getestet.
