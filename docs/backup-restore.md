# Backup & Restore

[README](../README.md) · [Roadmap](roadmap.md)

Dieses Dokument beschreibt, was für einen vollständigen Restore der
Nextcloud-ERP-Installation gesichert werden muss, und dokumentiert einen
tatsächlich durchgeführten Backup-/Restore-Test (Roadmap Phase 14,
"Backup-/Restore-Verhalten geprüft"). Es ersetzt keine allgemeine
Nextcloud-Backup-Doku ([offizielles Handbuch](https://docs.nextcloud.com/server/latest/admin_manual/maintenance/backup.html))
— hier geht es nur um die ERP-spezifischen Bestandteile und darum, dass ein
Restore mit den eigenen Daten der App nachweislich funktioniert.

## Was gesichert werden muss

Die App speichert Daten an drei Stellen, die zusammen und konsistent
gesichert werden müssen (ein Restore nur der DB ohne die Dateien — oder
umgekehrt — führt zu inkonsistenten Zuständen, z. B. Rechnungen mit
`document_file_id`, die auf nicht mehr existierende Dateien zeigen):

1. **PostgreSQL-Datenbank** — alle `oc_erp_*`-Tabellen (Projekte, Angebote,
   Aufträge, Rechnungen, Rechte-Matrix, Zeiterfassung, …) leben in
   derselben Datenbank wie der restliche Nextcloud-Server, siehe
   [ADR-0003](adr/0003-datenbank-postgresql.md). Kein separates
   ERP-Backup nötig/möglich — ein normales Nextcloud-DB-Backup deckt das
   automatisch ab.
2. **Nextcloud-Datenverzeichnis** (`nextcloud_data`-Volume in
   `docker/docker-compose.yml`) — enthält u. a.:
   - `config/config.php` (Instanz-Secrets, DB-Zugangsdaten,
     `instanceid`/`passwordsalt` — **ohne diese Datei sind weder die DB
     noch verschlüsselte Felder entschlüsselbar**, unbedingt mitsichern)
   - `data/<user>/files/ERP/Projekte/<Nummer>/…` — Projektordner,
     PDF-Belege (ADR-0021), Tankbelege (ADR-0017) — alles, was die App
     über `OCP\Files` ablegt
3. **`.env`** (Docker-Compose-Zugangsdaten für die lokale Testumgebung,
   nicht Teil des eigentlichen Produktivbetriebs — in Produktion gelten die
   dort hinterlegten Werte entsprechend, siehe eigene Infrastruktur-Doku).

## Backup

```bash
# 1. PostgreSQL — Custom-Format (komprimiert, für pg_restore geeignet)
docker compose exec db pg_dump -U nextcloud -d nextcloud -F c -f /tmp/nextcloud-backup.dump
docker compose cp db:/tmp/nextcloud-backup.dump ./nextcloud-backup.dump

# 2. Nextcloud-Datenverzeichnis (Konfiguration + Dateien)
docker compose exec -u www-data nextcloud \
  tar -czf /tmp/nextcloud-data-backup.tar.gz -C /var/www/html config data
docker compose cp nextcloud:/tmp/nextcloud-data-backup.tar.gz ./nextcloud-data-backup.tar.gz
```

**Empfehlung für den Produktivbetrieb:** vor dem Backup `occ maintenance:mode
--on` setzen, damit während des Dumps keine Schreibzugriffe laufen
(inkonsistenter Snapshot sonst theoretisch möglich, bei Postgres durch
Transaktionsisolation aber ohnehin unwahrscheinlich). Für die lokale
Docker-Testumgebung nicht zwingend nötig.

## Restore

```bash
# 1. PostgreSQL
docker compose cp ./nextcloud-backup.dump db:/tmp/nextcloud-backup.dump
docker compose exec db psql -U nextcloud -d nextcloud \
  -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
docker compose exec db pg_restore -U nextcloud -d nextcloud \
  --no-owner --no-privileges /tmp/nextcloud-backup.dump

# 2. Nextcloud-Datenverzeichnis
docker compose cp ./nextcloud-data-backup.tar.gz nextcloud:/tmp/restore.tar.gz
docker compose exec -u www-data nextcloud \
  tar -xzf /tmp/restore.tar.gz -C /var/www/html

# 3. Danach: Cache/Dateiindex neu aufbauen
docker compose exec -u www-data nextcloud php occ files:scan --all
docker compose exec -u www-data nextcloud php occ maintenance:mode --off
```

`--no-owner --no-privileges` bei `pg_restore` vermeidet Fehler durch
Rollen, die im Ziel-Cluster nicht existieren (z. B. wenn Backup und Restore
auf unterschiedlichen Postgres-Instanzen mit unterschiedlichen
DB-Benutzern laufen) — die eigentlichen Daten sind davon nicht betroffen.

## Durchgeführter Test (2026-09-01)

Gegen die laufende Docker-Testumgebung, mit den durch
[`../tests/seed-monteur-projektleiter-szenario.sh`](../tests/seed-monteur-projektleiter-szenario.sh)
erzeugten Testdaten als Prüfgröße:

1. `pg_dump` der laufenden Datenbank (2 MB, Custom-Format).
2. Restore **in einen frischen, isolierten Wegwerf-Container** (nicht in
   die laufende Testumgebung — um deren Zustand nicht zu riskieren).
3. Verifiziert: alle 51 `oc_erp_*`-Tabellen vorhanden, Projekt `2495`
   ("Musterinstallation Lager Nord"), Angebot `506` und Zeiterfassung `304`
   (240 Minuten) exakt wie im Original, Gesamtzahl Projekte (`11`) korrekt.
4. `tar`-Backup des Nextcloud-Datenverzeichnisses (Projektordner
   `data/projektleiter-fixture/files/ERP/Projekte/P-02495/` + `config.php`)
   erstellt und Archivinhalt verifiziert.

**Ergebnis: Restore ist verlustfrei und reproduzierbar.** Kein
produktives System wurde für den Test verändert.

## Bekannte Einschränkungen

- Kein automatisiertes Backup-Tooling/Cron in diesem Repo — Backup/Restore
  sind bewusst manuelle, dokumentierte Schritte (Scheduling ist Aufgabe der
  jeweiligen Zielinfrastruktur, nicht der App selbst).
- Kein Punkt-in-Zeit-Recovery (PITR) dokumentiert — nur vollständige
  Dump-/Restore-Zyklen. Für produktiven Einsatz mit höheren Ansprüchen an
  RPO ist eine WAL-Archivierung auf PostgreSQL-Ebene zu ergänzen
  (außerhalb des Scopes dieser App-Dokumentation).
- Verschlüsselte Felder (falls Nextclouds Server-Side-Encryption aktiv
  ist) hängen zusätzlich von `config.php`-Schlüsseln ab — bei getrenntem
  Restore von DB und Datenverzeichnis auf unterschiedlichen Ständen drohen
  dann nicht mehr entschlüsselbare Daten. Immer beide Teile aus **demselben**
  Backup-Zeitpunkt wiederherstellen.
