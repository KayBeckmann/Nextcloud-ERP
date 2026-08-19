# ADR-0005: Docker-Hosting und reproduzierbare Tests

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

Vorgabe aus der Roadmap: Nextcloud wird in Docker gehostet, Entwicklungs- und
Testumgebung müssen auf unterschiedlichen Maschinen reproduzierbar sein, ohne
lokale Sonderkonfiguration oder Secrets im Repo.

## Entscheidung

- `docker/docker-compose.yml` startet Nextcloud + PostgreSQL vollständig über
  Environment-Variablen aus `.env` (Vorlage: `.env.example`, ohne echte Secrets).
- Die App wird per Bind-Mount aus `nextcloud/erp/` nach
  `/var/www/html/custom_apps/erp` im Nextcloud-Container eingebunden — kein
  Kopierschritt, Änderungen sind sofort sichtbar.
- Alle Setup-/Test-Befehle werden als dokumentierte `docker compose exec …`-Aufrufe
  in `docker/README.md` festgehalten, nicht als Klick-Anleitung.
- Keine hartkodierten Hostpfade, Ports oder Zugangsdaten in Compose-Datei oder Code.

## Konsequenzen

- Jede Maschine mit Docker + Docker Compose kann die Umgebung mit denselben
  Befehlen starten.
- PHP-/Composer-/PHPUnit-Aufrufe laufen im Container (kein lokales PHP nötig).
- Erstinstallation/Migration muss bei jedem frischen Start explizit über `occ`
  ausgelöst werden (kein "magisches" Auto-Setup, das reproduzierbares Testen
  erschwert).

## Alternativen erwogen

- Native lokale PHP-/Nextcloud-Installation: widerspricht "reproduzierbar auf
  unterschiedlichen Maschinen" und würde Systempakete/PHP-Version-Drift riskieren.
- Vorgefertigtes Nextcloud-Image mit bereits einkompilierter App: erschwert
  schnelle Iteration während der Entwicklung.
