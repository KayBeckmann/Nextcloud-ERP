# Übergreifende Tests / Fixtures

App-eigene PHPUnit-Tests liegen bei der App selbst unter
[`../nextcloud/erp/tests/`](../nextcloud/erp/tests/) — inklusive der
strukturellen Rollen-/Rechte-Testmatrix
(`Unit/Permissions/ControllerRightsGateTest.php`, Roadmap Phase 14), die
über alle Controller hinweg prüft, dass jede öffentliche Action einen
ERP-Rechte-Check hat.

Dieser Ordner ist für Tests/Fixtures gedacht, die über eine einzelne App
hinausgehen:

## `seed-monteur-projektleiter-szenario.sh`

Testdaten-/Fixture-Skript (Roadmap Phase 14, "Testdaten für Monteur-/
Projektleiter-Testszenarien"). Erzeugt über die laufende API v1 (nicht per
Rohzugriff auf die DB, damit dieselben Business-Regeln/Validierungen wie im
Betrieb greifen):

1. Rechte-Matrix für zwei Rollen: **Monteur** (nur `write` auf
   Stunden/Zeitkonto, `read` auf Projekte) und **Projektleiter** (`write`
   auf Projekte/Angebote/Aufträge/Kunden/Stunden).
2. Firmenprofil (für PDF-Belegköpfe).
3. Eine Arbeitsart ("Monteur", 45 €/h).
4. Ein Projekt, angelegt vom Projektleiter.
5. Ein Angebot mit einer Arbeitsstunden-Position.
6. Eine Zeiterfassung des Monteurs auf das Projekt.

Voraussetzung: laufende Docker-Testumgebung (siehe
[`../docker/README.md`](../docker/README.md)) mit zwei dedizierten
Fixture-Usern (Default: `projektleiter-fixture`/`monteur-fixture`, bewusst
getrennt von etwaigen manuellen Test-Usern):

```bash
docker compose exec -u www-data nextcloud php occ user:add \
  --password-from-env --display-name="Projektleiter (Fixture)" projektleiter-fixture
docker compose exec -u www-data nextcloud php occ user:add \
  --password-from-env --display-name="Monteur (Fixture)" monteur-fixture
```

Danach:

```bash
NC_PROJEKTLEITER_PASS=... NC_MONTEUR_PASS=... ./tests/seed-monteur-projektleiter-szenario.sh
```

Alle Parameter (Basis-URL, Admin-/User-Zugangsdaten) sind per Umgebungsvariable
konfigurierbar, siehe Kopfkommentar im Skript. Nicht idempotent im strengen
Sinn — mehrfaches Ausführen legt weitere Projekte/Angebote an (unschädlich
für ein Testsystem).

## Weitere geplante Inhalte

- End-to-End-Szenarien gegen die laufende Docker-Testumgebung
- API-Vertragstests
