#!/usr/bin/env bash
# Reproduzierbarer Vorlauf für den manuellen P4-Browser-Smoke.
# Er erzeugt bewusst keine Geschäfts-, Kontakt- oder Belegdaten.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT/nextcloud/erp"
DOCKER_DIR="$ROOT/docker"

if ! command -v docker >/dev/null; then
	echo "Docker fehlt. Siehe docker/README.md." >&2
	exit 1
fi
if ! docker compose version >/dev/null; then
	echo "Docker Compose v2 fehlt. Siehe docker/README.md." >&2
	exit 1
fi
if ! command -v npm >/dev/null; then
	echo "npm fehlt (Node 20+ erforderlich)." >&2
	exit 1
fi

# Docker Compose liest seine lokale, gitignorierte .env selbst. Sie darf hier
# nicht als Shell-Datei geladen werden: Werte wie NEXTCLOUD_TRUSTED_DOMAINS
# enthalten Leerzeichen und sind gültige Compose-, aber keine Shell-Syntax.
(
	cd "$APP_DIR"
	npm install
	npm run build
)
(
	cd "$DOCKER_DIR"
	docker compose up -d
	docker compose exec -T -u www-data nextcloud php occ app:enable erp
	docker compose exec -T -u www-data nextcloud php occ status >/dev/null
)

# status.php ist absichtlich öffentlich; so benötigt der Vorlauf keine
# Zugangsdaten und gibt keine Kennwörter an curl oder die Prozessliste weiter.
PORT="${NEXTCLOUD_HTTP_PORT:-8080}"
STATUS="$(curl --fail --silent --show-error --output /dev/null --write-out '%{http_code}' \
	"http://localhost:${PORT}/status.php")"
if [[ "$STATUS" != "200" ]]; then
	echo "Nextcloud-Status liefert HTTP $STATUS statt 200." >&2
	exit 1
fi

echo "P4-Vorlauf bereit: http://localhost:${PORT}/index.php/apps/erp/"
echo "Nächste Schritte und Fixture-Plan: docs/p4-guided-workflow-smoke.md"
