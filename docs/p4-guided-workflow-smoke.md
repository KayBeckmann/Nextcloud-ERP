# P4 — Geführter Projektablauf: Rechte und Browser-Smoke

Der Tab **Ablauf** im Projektdetail macht den tatsächlichen Stand vorhandener
ERP-Datensätze sichtbar. Er legt keine Daten an, ändert keine Statuswerte und
führt weder Versand, Wareneingang noch Zahlung automatisch aus. Die Buttons
öffnen ausschließlich vorhandene Projekt-Tabs bzw. das Lager.

## Pragmatische Rechte-Matrix (Variante 2)

Die Matrix beschreibt die Bedienhilfe, nicht eine neue Freigaberegel. Die
bestehende effektive Berechtigung aus `GET /permissions/me` bleibt maßgeblich.
`write` reicht bewusst weiterhin für alle nachstehend genannten Fachaktionen;
`approve` wird **nicht** neu als Grenze eingeführt.

| Rolle | Sinnvolle Rechte (mindestens) | Im Ablauf möglich / erklärt |
|---|---|---|
| Projektleitung | `write`: Projekte, Angebote, Aufträge, Lieferscheine, Lager; `read` oder `write`: Rechnungen | Kunde/Projekt pflegen, Angebot und Auftrag erstellen/bestätigen, projektbezogene Bestellung und Wareneingang bearbeiten, Lieferschein erstellen. Ohne Rechnungs-Schreibrecht zeigt der Tab verständlich die Einschränkung. |
| Buchhaltung | `read`: Projekte, Angebote, Aufträge, Lieferscheine, Lager; `write`: Rechnungen | Belegkette nachsehen, Teil-/Schlussrechnung erstellen und ausstellen, Zahlung erfassen. **Ausstellen und Zahlung bleiben bei `write`**, wie vor P4. |
| Monteur | `read`: Projekte, Aufträge, Lieferscheine; optional `write`: Lager | Status und nächste Voraussetzung sehen. Ohne Schreibrecht werden keine irreführenden Aktionsbuttons gezeigt. Mit bestehendem Lager-`write` ist der bewusste Wareneingang möglich. |

Die Oberfläche unterscheidet klar zwischen **„Voraussetzung fehlt“** (fachlich
noch nicht möglich) und **„keine Schreibberechtigung“** (Rechteproblem). Ein
Schreibrecht auf einer Ressource ist dabei ausreichend; `approve`/`admin` sind
nur stärkere vorhandene Stufen. Diese P4-Hilfe verändert weder Controller-Gates
noch Statusübergänge oder Persistenz.

## Lokaler Browser-Smoke

Voraussetzungen: Docker Compose gemäß [`../docker/README.md`](../docker/README.md),
Node 20+ und ein lokaler Browser. Die Instanz bleibt auf `127.0.0.1:8080`.

1. Von der Repo-Wurzel aus ausführen:

   ```bash
   ./scripts/p4-browser-smoke.sh
   ```

   Das Script baut das Frontend, startet/prüft den lokalen Compose-Stack,
   aktiviert die App und prüft den öffentlichen Nextcloud-Status. Es benötigt
   keine Zugangsdaten, erzeugt keine Fixtures und bleibt auf der lokalen
   Compose-Konfiguration.
2. Im Browser bei `http://localhost:8080/index.php/apps/erp/` anmelden und die
   ERP-App öffnen. Bei einem frischen Stack zuerst die in `docker/README.md`
   dokumentierte App-Aktivierung abwarten.
3. Die unten stehenden Minimal-Fixtures anlegen. IDs/Belegnummern absichtlich
   nicht fest verdrahten; der Smoke ist auf einen frischen oder zurückgesetzten
   Docker-Stack reproduzierbar.
4. Das Projekt öffnen, Tab **Ablauf** wählen und nach jeder bewussten Aktion
   **Aktualisieren** klicken. Prüfen, dass sich nur der zugehörige Schritt
   ändert und keine Aktion automatisch ausgelöst wird.

### Minimal-Fixture und Prüfschritte

| Schritt | Manuell über vorhandene UI/API anlegen | Erwartung im Ablauf |
|---|---|---|
| 1 | In Nextcloud Contacts einen Kontakt anlegen; im Projekt unter **Übersicht** als Kunde verknüpfen. | „Kunde verknüpfen“ erledigt; Angebot wird als nächster Schritt angeboten. |
| 2 | Im Tab **Angebote** ein Angebot erstellen, Position ergänzen und im Detail auf `accepted` setzen. | Angebot erledigt; Auftrag ist möglich. |
| 3 | Im Tab **Aufträge** Auftrag aus dem Angebot erzeugen bzw. erstellen und auf `confirmed` setzen. | Auftrag erledigt; Bestellung/Lieferschein werden erklärt. |
| 4 | Optional Materialpfad: im **Lager** eine Bestellung mit einer Position mit diesem `projectId` erstellen, `approved` → `sent`; dann echten Wareneingang buchen. | Bestellung und anschließend Wareneingang werden erledigt. Entwurf/`approved` zeigen verständlich die nächste Statusaktion. |
| 5 | Im Tab **Lieferscheine** Lieferschein aus Auftrag erstellen und ausstellen. | Lieferschein erledigt; Teil-/Schlussrechnung wird angeboten. |
| 6 | Im Tab **Rechnungen** eine Teilrechnung (`partial`) aus Auftrag/Lieferschein erzeugen, ausstellen und Zahlung erfassen; danach Schlussrechnung (`final` oder `invoice`) ebenso ausstellen und bezahlen. | Ausgestellte, unbezahlte Rechnung zeigt „wartet auf nächsten Schritt“; nach Zahlung ist der Zahlungs-Schritt erledigt. |

### Rechte-Smoke

Für jede Rolle aus der Matrix mit einem Testuser anmelden und denselben
Projekt-Tab öffnen. Prüfen: `read` ohne `write` zeigt für die Ressource die
Meldung „kein Schreibrecht“ und keinen Aktionsbutton; `write` zeigt den Button.
Bei Rechnung-`write` müssen „Ausstellen“ und „Zahlung erfassen“ weiterhin über
die bestehenden Details funktionieren — P4 fügt keine Approve-Grenze hinzu.

### Reset

Für einen komplett frischen Lauf aus `docker/`:

```bash
docker compose down -v
rm -rf ../nextcloud/erp/js
```

Danach erneut `./scripts/p4-browser-smoke.sh` ausführen. Falls vorher PHPUnit
lief, den in `docker/README.md` beschriebenen Teamfolder-Reprovisionierungsschritt
vor dem Browserlauf ausführen.
