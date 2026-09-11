# Docker-Testumgebung

Reproduzierbare **ausschließlich lokale Entwicklungs-/Testinstanz** mit der
ERP-App und PostgreSQL. Diese Compose-Dateien sind nicht für Staging oder
Produktion vorgesehen und enthalten bewusst keinerlei Deployment- oder
Reverse-Proxy-Konfiguration.
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
Nextcloud ist danach ausschließlich unter `http://localhost:8080` erreichbar.
Der Port wird fest an `127.0.0.1` gebunden, ist also weder im LAN noch im
Internet erreichbar. Die Zugangsdaten stammen aus der lokalen, ignorierten
`.env`; die Beispielwerte müssen vor dem ersten Start ersetzt werden.

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
`nextcloud/server` (passend zur Zielversion, `stable34`) nachgezogen. Der
Testbootstrap wird **nur** über die separate Compose-Override-Datei eingebunden,
damit ein noch nicht angelegter Host-Pfad die reguläre Nextcloud-Initialisierung
nicht blockiert:

**Schritt 1 — Server-Testbootstrap (Sparse-Checkout):**

```bash
mkdir -p docker/.nc-server-tests
git clone --depth 1 --branch stable34 --filter=blob:none --sparse \
  https://github.com/nextcloud/server.git docker/.nc-server-tests/src
git -C docker/.nc-server-tests/src sparse-checkout set tests
```

**Schritt 2 — `groupfolders` bereitstellen:**

Die Teamfolder-App (`groupfolders`) ist eine Laufzeitabhängigkeit von ADR-0024
und **nicht** im schlanken offiziellen Image enthalten. `docker-compose.tests.yml`
mountet sie aus `docker/.nc-apps/`; dieser Pfad ist in `.gitignore` und muss
einmalig lokal befüllt werden. Nextcloud verteilt Apps nicht als GitHub-Release,
sondern signiert über die `nextcloud-releases`-Organisation:

```bash
mkdir -p docker/.nc-apps
curl -sL -o /tmp/groupfolders.tar.gz \
  https://github.com/nextcloud-releases/groupfolders/releases/download/v22.0.6/groupfolders-v22.0.6.tar.gz
tar xzf /tmp/groupfolders.tar.gz -C docker/.nc-apps/
rm /tmp/groupfolders.tar.gz
```

> Version bewusst gepinnt. Sie muss zur Nextcloud-Zielversion passen
> (`stable34` → groupfolders 22.x). Passende Version notfalls über
> `https://apps.nextcloud.com/apps/groupfolders` ermitteln.

**Schritt 3 — Stack mit Test-Override starten:**

```bash
docker compose -f docker-compose.yml -f docker-compose.tests.yml up -d
# Container wird mit den zusätzlichen read-only Mounts neu erstellt.
```

Danach Tests ausführen:

```bash
docker compose exec -u www-data nextcloud bash -c \
  "cd /var/www/html/custom_apps/erp && php vendor/bin/phpunit --configuration tests/phpunit.xml"
```

Erwartung: alle Tests grün (Stand 2026-09-11: 279 Tests, 1244 Assertions).

### Nach jedem Testlauf: Teamfolder neu provisionieren

`Test\TestCase` des Servers löscht nach **jeder** Testklasse alle Storage- und
Filecache-Einträge. `ErpIntegrationTestCase` legt den Teamfolder `ERP-Firma`
deshalb vor jeder betroffenen Testklasse neu an — das hält die *Tests* grün.

Am Ende des Laufs bleibt die Umgebung trotzdem kaputt zurück: Nur 8 der 24
Service-Testklassen erben von `ErpIntegrationTestCase`. Läuft eine der übrigen
zuletzt, räumt deren Teardown ab, ohne den Ordner wiederherzustellen. In
`oc_group_folders` steht danach eine Konfigzeile ohne Storage; `occ
groupfolders:list` meldet „No folders configured", und im Browser fehlt der
Ordner.

Wer die Umgebung nach einem Testlauf **manuell weiterbenutzt** (Klicktest,
curl), muss den Teamfolder daher neu anlegen. Die verwaiste Zeile blockiert
dabei `createFolder()` und muss zuerst weg:

```bash
docker compose exec -T db psql -U oc_admin -d nextcloud -q -c "
DELETE FROM oc_group_folders_groups WHERE folder_id IN (
  SELECT folder_id FROM oc_group_folders WHERE mount_point='ERP-Firma');
DELETE FROM oc_group_folders WHERE mount_point='ERP-Firma';"

FID=$(docker compose exec -T -u www-data nextcloud php occ groupfolders:create "ERP-Firma" 2>/dev/null | tail -1 | tr -d '\r')
docker compose exec -T -u www-data nextcloud php occ groupfolders:group "$FID" erp-projektleiter read write share delete
docker compose exec -T -u www-data nextcloud php occ groupfolders:group "$FID" erp-monteure read write
```

> `tail -1` ist nötig, weil `occ` ohne die `pcntl`-Extension eine Warnung auf
> stdout ausgibt, die sonst in `$FID` landet — dieselbe Ursache wie beim
> entsprechenden Schritt in `.github/workflows/ci.yml`.

Gegenprobe per echtem WebDAV (nicht nur `occ`, da der Mount erst im
Request-Kontext aufgelöst wird):

```bash
curl -s -u <user>:<pass> -X PROPFIND -H "Depth: 1" \
  http://localhost:8080/remote.php/dav/files/<user>/ | grep -o "ERP-Firma"
```

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

- Port-Bindung ausschließlich auf `127.0.0.1`; kein LAN-/Internet-Exposure.
- `restart: "no"`: Die Entwicklungscontainer starten nicht automatisch nach
  einem Host-Neustart.
- Keine hardcodierten Hostpfade oder Ports außerhalb von `.env`.
- Keine Secrets im Repo — `.env` ist gitignored, `.env.example` enthält nur
  unkritische Entwicklungs-Defaults.
- `docker/.nc-server-tests/` ist gitignored (wird bei Bedarf lokal nachgezogen,
  kein Teil des Repos).
- Frisches `docker compose up -d` auf einer neuen Maschine ergibt ohne weitere
  manuelle Schritte eine lauffähige, leere Nextcloud-Instanz.
