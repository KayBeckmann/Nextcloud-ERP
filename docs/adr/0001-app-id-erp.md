# ADR-0001: App-ID `erp`

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

Die Roadmap listet den App-Namen/App-ID als offene Klärung vor der Implementierung
(Kandidaten u. a. `erp`, `skaldify_erp`, `nc_erp`). Alle bisherigen Mockups (Google
Stitch Prompts, Look-&-Feel-Prototyp) verwenden bereits durchgängig den Namen/Titel
"ERP" bzw. die App-ID `erp`.

## Entscheidung

Die Nextcloud-App-ID lautet `erp`, Verzeichnis `nextcloud/erp/`. Der sichtbare
Anzeigename in der Navigation ist "ERP".

## Konsequenzen

- Konsistent mit allen bestehenden Mockups/Prompts, kein Nacharbeiten der Screens nötig.
- `erp` ist ein sehr generischer App-Store-Name. Falls die App später im offiziellen
  Nextcloud App Store veröffentlicht wird, kann eine Umbenennung nötig werden
  (App-ID-Änderungen nach Veröffentlichung sind bei Nextcloud-Apps schwierig/unüblich).
  Für die aktuelle Entwicklungsphase (privates/eigenes Repo, noch nicht im App Store)
  ist das Risiko akzeptabel.

## Alternativen erwogen

- `skaldify_erp` — eindeutiger, aber bindet den Namen an die Skaldify-Marke, obwohl
  das ERP branchenneutral und nicht Skaldify-spezifisch sein soll.
- `nc_erp` — unüblich, "nc"-Präfix ist bei Nextcloud-Apps nicht Konvention (die
  Plattform-Zugehörigkeit ergibt sich bereits aus dem Kontext).
