# Docker-Testumgebung

Reproduzierbare lokale Nextcloud-Instanz mit der ERP-App und PostgreSQL.
Kein lokales PHP/Nextcloud nötig — alles läuft im Container. Composer wird
einmalig im Container nachinstalliert (siehe unten), da das offizielle Image
keinen Composer mitbringt.

## Mindestversionen

| Komponente | Version |
|---|---|
| Docker | ≥ 24 |
| Docker Compose | ≥ 2.20 (Compose-Spec v2) |
| Nextcloud (Image) | 34 (siehe [ADR-0002](../docs/adr/0002-nextcloud-mindestversion.md)) |
| PostgreSQL | 16 (siehe [ADR-0003](../docs/adr/0003-datenbank-postgresql.md)) |
| Node (nur für Frontend-Build außerhalb des Containers) | ^20/^22/^24 |

## 1. Frontend bauen

Vor dem ersten Start (und nach jeder Frontend-Änderung):

```bash
cd nextcloud/erp
npm install
npm run build
```

Ergebnis landet in `nextcloud/erp/js/` (gitignored, wird nicht committet).

## 2. Nextcloud-Testinstanz starten

```bash
cd docker
cp .env.example .env   # bei Bedarf Werte anpassen — .env wird nicht committet
docker compose up -d
```

Die Erstinstallation läuft automatisch über die `NEXTCLOUD_ADMIN_*`/`POSTGRES_*`-
Umgebungsvariablen des offiziellen Images (dauert beim ersten Start ca. 1 Minute).
Nextcloud ist danach unter `http://localhost:8080` erreichbar
(Standard-Login aus `.env`: `admin` / `admin`).

Die App liegt per Bind-Mount unter `/var/www/html/custom_apps/erp` im Container —
PHP-Änderungen an `nextcloud/erp/` sind ohne Neustart sofort wirksam.

## 3. App aktivieren

```bash
docker compose exec -u www-data nextcloud php occ app:enable erp
```

Das führt automatisch auch die App-Migration aus (prüfbar über
`docker compose exec db psql -U nextcloud -d nextcloud -c "\d oc_erp_app_meta"`).

Verifizieren:

```bash
docker compose exec -u www-data nextcloud php occ app:list | grep erp
curl -s -o /dev/null -w '%{http_code}\n' -u admin:admin -H "OCS-APIRequest: true" \
  http://localhost:8080/ocs/v2.php/apps/erp/api/v1/status   # erwartet: 200
```

Das Web-UI selbst braucht eine Session (nicht nur Basic Auth) — im Browser unter
`http://localhost:8080/index.php/apps/erp/` einloggen und prüfen.

## 4. Tests ausführen

Composer ist im offiziellen `nextcloud`-Image nicht vorinstalliert, einmalig
nachinstallieren:

```bash
docker compose exec -u root nextcloud bash -c \
  "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"
```

App-Dependencies installieren (als `root`, da der Bind-Mount vom Host-User
gehalten wird und `www-data` im Container sonst keine Schreibrechte hat):

```bash
docker compose exec -u root nextcloud bash -c \
  "cd /var/www/html/custom_apps/erp && composer install --no-interaction --no-progress"
```

**Wichtige Besonderheit:** Die offiziellen Nextcloud-Docker-Images sind
Produktiv-Images und enthalten **kein** `tests/`-Verzeichnis — der volle
Server-Testbootstrap (`Test\TestCase`, DB-Testhelfer) fehlt deshalb standardmäßig.
Für lokale PHPUnit-Läufe wird er einmalig per Sparse-Checkout aus
`nextcloud/server` (passend zur Zielversion, `stable34`) nachgezogen und
read-only in den Container gemountet (`docker-compose.yml`,
Volume `./.nc-server-tests/src/tests`):

```bash
mkdir -p docker/.nc-server-tests
git clone --depth 1 --branch stable34 --filter=blob:none --sparse \
  https://github.com/nextcloud/server.git docker/.nc-server-tests/src
git -C docker/.nc-server-tests/src sparse-checkout set tests
docker compose up -d   # Container mit dem neuen Mount neu erstellen
```

Danach Tests ausführen:

```bash
docker compose exec -u www-data nextcloud bash -c \
  "cd /var/www/html/custom_apps/erp && php vendor/bin/phpunit --configuration tests/phpunit.xml"
```

Erwartung: alle Tests grün (Stand 2026-08-19: 4 Tests, 8 Assertions).

In CI (`.github/workflows/ci.yml`) läuft derselbe Testlauf ohnehin gegen einen
vollständigen `nextcloud/server`-Checkout, unabhängig von dieser lokalen
Sparse-Checkout-Krücke — die ist ausschließlich eine Erleichterung für
schnelles lokales Testen gegen das sonst identische Docker-Setup.

## Optional: zusätzliche Store-Apps installieren (z. B. contacts, calendar)

Das offizielle Image markiert `/var/www/html/apps` standardmäßig als
schreibgeschützt (`config/apps.config.php`, `writable => false`) — `occ
app:install` scheitert dadurch mit "Cannot write into apps directory", obwohl
die Dateisystemrechte selbst passen. Für lokale Tests, bei denen man z. B. die
Contacts- oder Calendar-Web-App zusätzlich sehen will (unsere ERP-Integration
läuft unabhängig davon direkt über `OCP\Contacts\IManager`/
`OCP\Calendar\IManager`, siehe ADR-0009):

```bash
docker compose exec -u www-data nextcloud \
  sed -i "s/'writable' => false,/'writable' => true,/" /var/www/html/config/apps.config.php
docker compose exec -u www-data nextcloud php occ app:install contacts
docker compose exec -u www-data nextcloud php occ app:install calendar
```

Nur für die lokale Testinstanz, keine Repo-relevante Änderung.

## Stoppen / zurücksetzen

```bash
docker compose down          # Container stoppen, Volumes bleiben erhalten
docker compose down -v       # Container + Volumes löschen (kompletter Reset)
```

## Reproduzierbarkeit

- Keine hardcodierten Hostpfade oder Ports außerhalb von `.env`.
- Keine Secrets im Repo — `.env` ist gitignored, `.env.example` enthält nur
  unkritische Entwicklungs-Defaults.
- `docker/.nc-server-tests/` ist gitignored (wird bei Bedarf lokal nachgezogen,
  kein Teil des Repos).
- Frisches `docker compose up -d` auf einer neuen Maschine ergibt ohne weitere
  manuelle Schritte eine lauffähige, leere Nextcloud-Instanz.
