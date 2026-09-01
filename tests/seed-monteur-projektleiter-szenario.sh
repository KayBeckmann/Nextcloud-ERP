#!/usr/bin/env bash
#
# Testdaten-/Fixture-Skript für ein Monteur-/Projektleiter-Szenario
# (Roadmap Phase 14, "Testdaten für Monteur-/Projektleiter-Szenarien").
#
# Erzeugt über die laufende API v1 (nicht per Rohzugriff auf die DB, damit
# sämtliche Business-Regeln/Validierungen genauso greifen wie im echten
# Betrieb) einen realistischen Ablauf:
#
#   1. Rechte: "monteur" bekommt nur Schreibrechte auf Stunden/Zeitkonto und
#      Leserechte auf Projekte — "projektleiter" bekommt Schreibrechte auf
#      Projekte/Angebote/Aufträge/Kunden/Stunden.
#   2. Firmenprofil (für PDF-Belegköpfe).
#   3. Eine Arbeitsart ("Monteur", 45 EUR/h).
#   4. Ein Projekt, angelegt vom Projektleiter.
#   5. Ein Angebot mit einer Arbeitsstunden-Position.
#   6. Eine Zeiterfassung des Monteurs auf das Projekt.
#
# Voraussetzung: laufende Docker-Testumgebung (siehe docker/README.md) mit
# zwei dedizierten Fixture-Usern, die vorher per `occ user:add` angelegt
# wurden (Default-Namen unten: "projektleiter-fixture"/"monteur-fixture" —
# bewusst NICHT die bestehenden manuellen Test-User wiederverwenden, um
# deren Zustand nicht zu beeinflussen). Admin-Zugang wird für die
# Rechte-Matrix-Vergabe benötigt (nur NC-Instanz-Admins dürfen
# PUT /api/v1/permissions/matrix, siehe ADR-0008).
#
# Nicht idempotent im strengen Sinn — mehrfaches Ausführen legt weitere
# Projekte/Angebote an (unschädlich für ein Testsystem), vergibt Rechte aber
# gefahrlos erneut (Upsert).
#
# Nutzung:
#   NC_BASE_URL=http://localhost:8080 \
#   NC_ADMIN=admin NC_ADMIN_PASS=admin \
#   NC_PROJEKTLEITER=projektleiter-fixture NC_PROJEKTLEITER_PASS=... \
#   NC_MONTEUR=monteur-fixture NC_MONTEUR_PASS=... \
#   ./tests/seed-monteur-projektleiter-szenario.sh

set -euo pipefail

BASE_URL="${NC_BASE_URL:-http://localhost:8080}"
ADMIN="${NC_ADMIN:-admin}:${NC_ADMIN_PASS:-admin}"
PL_USER="${NC_PROJEKTLEITER:-projektleiter-fixture}"
PL_AUTH="${PL_USER}:${NC_PROJEKTLEITER_PASS:-projektleiter-fixture-pw}"
MO_USER="${NC_MONTEUR:-monteur-fixture}"
MO_AUTH="${MO_USER}:${NC_MONTEUR_PASS:-monteur-fixture-pw}"

OCS_HEADER=(-H "OCS-APIRequest: true" -H "Accept: application/json")

api() {
	# api <auth> <method> <path> [form-data...]
	local auth="$1" method="$2" path="$3"
	shift 3
	local args=(-s -u "$auth" -X "$method" "${OCS_HEADER[@]}")
	for kv in "$@"; do
		args+=(--data-urlencode "$kv")
	done
	if [ "$method" = "GET" ]; then
		curl "${args[@]}" -G "${BASE_URL}/ocs/v2.php/apps/erp${path}?format=json"
	else
		curl "${args[@]}" "${BASE_URL}/ocs/v2.php/apps/erp${path}?format=json"
	fi
}

json_get() {
	# json_get <json> <jq-filter>
	python3 -c "import json,sys; d=json.load(sys.stdin); print(d['ocs']['data']$1)" 2>/dev/null
}

echo "== 1/6: Rechte-Matrix — Monteur ==" >&2
api "$ADMIN" PUT /api/v1/permissions/matrix principalType=user principalId="$MO_USER" resourceType=stunden-zeitkonto permission=write >/dev/null
api "$ADMIN" PUT /api/v1/permissions/matrix principalType=user principalId="$MO_USER" resourceType=projekte permission=read >/dev/null

echo "== 2/6: Rechte-Matrix — Projektleiter ==" >&2
for resource in projekte angebote auftraege kunden stunden-zeitkonto; do
	api "$ADMIN" PUT /api/v1/permissions/matrix principalType=user principalId="$PL_USER" resourceType="$resource" permission=write >/dev/null
done

echo "== 3/6: Firmenprofil ==" >&2
api "$PL_AUTH" PUT /api/v1/company-profile \
	name="Beispiel Elektrotechnik GmbH" addressLine="Musterstraße 1" \
	postalCode="12345" city="Musterstadt" country="DE" \
	taxId="DE123456789" email="info@beispiel-elektrotechnik.example" \
	footerText="Beispielhafte Testdaten — kein echtes Unternehmen." >/dev/null

echo "== 4/6: Arbeitsart 'Monteur' ==" >&2
# Arbeitsarten sind Stammdaten unter ResourceType::Einstellungen (ADR-0011)
# — realistisch von einem Admin/der Verwaltung angelegt, nicht vom
# einzelnen Projektleiter (der bräuchte sonst zusätzlich write auf
# einstellungen, was hier bewusst nicht Teil des Rollenprofils ist).
WORK_TYPE_JSON=$(api "$ADMIN" POST /api/v1/work-types name="Monteur" hourlyRate=45.00)
WORK_TYPE_ID=$(echo "$WORK_TYPE_JSON" | json_get "['id']")
echo "   work_type_id=$WORK_TYPE_ID" >&2

echo "== 5/6: Projekt + Angebot (Projektleiter) ==" >&2
PROJECT_JSON=$(api "$PL_AUTH" POST /api/v1/projects title="Musterinstallation Lager Nord" notes="Fixture-Szenario, Roadmap Phase 14")
PROJECT_ID=$(echo "$PROJECT_JSON" | json_get "['id']")
echo "   project_id=$PROJECT_ID" >&2

QUOTE_JSON=$(api "$PL_AUTH" POST /api/v1/quotes projectId="$PROJECT_ID" title="Angebot Musterinstallation")
QUOTE_ID=$(echo "$QUOTE_JSON" | json_get "['id']")
api "$PL_AUTH" POST "/api/v1/quotes/${QUOTE_ID}/positions" \
	positionType=labor description="Installation Verteilerschrank" \
	quantity=8 unitPriceNet=45.00 vatRatePercent=19 >/dev/null
echo "   quote_id=$QUOTE_ID (1 Arbeitsstunden-Position, 8h)" >&2

echo "== 6/6: Zeiterfassung Monteur ==" >&2
TODAY=$(date +%F)
TIME_ENTRY_JSON=$(api "$MO_AUTH" POST /api/v1/time-entries \
	workTypeId="$WORK_TYPE_ID" entryDate="$TODAY" durationMinutes=240 \
	projectId="$PROJECT_ID")
TIME_ENTRY_ID=$(echo "$TIME_ENTRY_JSON" | json_get "['id']")
echo "   time_entry_id=$TIME_ENTRY_ID (4h am $TODAY)" >&2

echo >&2
echo "Fertig. Szenario:" >&2
echo "  Projektleiter '$PL_USER': Projekt $PROJECT_ID, Angebot $QUOTE_ID" >&2
echo "  Monteur '$MO_USER': Zeiterfassung $TIME_ENTRY_ID auf Projekt $PROJECT_ID" >&2
