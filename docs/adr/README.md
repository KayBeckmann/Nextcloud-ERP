# Architecture Decision Records

| ADR | Titel | Status |
|-----|-------|--------|
| [0001](0001-app-id-erp.md) | App-ID `erp` | accepted |
| [0002](0002-nextcloud-mindestversion.md) | Ziel-Nextcloud-Version | accepted |
| [0003](0003-datenbank-postgresql.md) | PostgreSQL als Datenbank | accepted |
| [0004](0004-frontend-stack-vue.md) | Frontend-Stack — Vue.js mit `@nextcloud/vue` | accepted |
| [0005](0005-docker-reproduzierbare-tests.md) | Docker-Hosting und reproduzierbare Tests | accepted |
| [0006](0006-monorepo-struktur.md) | Monorepo-Struktur | accepted |
| [0007](0007-mit-lizenz.md) | MIT-Lizenz | superseded-by [0023](0023-agpl-lizenzwechsel.md) |
| [0008](0008-rechte-modell.md) | ERP-Rechte-Modell | accepted |
| [0009](0009-contacts-calendar-files-integration.md) | Contacts-/Calendar-/Files-Integration | accepted |
| [0010](0010-projektkern-datenmodell.md) | Projektkern-Datenmodell | accepted |
| [0011](0011-artikel-produkte-angebote-datenmodell.md) | Datenmodell Artikel/Produkte/Angebote | accepted |
| [0012](0012-zeitwirtschaft-verrechnungssaetze.md) | Zeitwirtschaft und Verrechnungssätze | accepted |
| [0013](0013-rechnungen-gutschriften-zahlungsstatus.md) | Rechnungen, Gutschriften und Zahlungsstatus | accepted |
| [0014](0014-lager-inventur-bestellvorschlaege.md) | Lager, Inventur und Bestellvorschläge | accepted |
| [0015](0015-projektpflicht-lieferscheine-picker.md) | Projektpflicht, Lieferscheine, Kontakt-/User-Picker | accepted |
| [0016](0016-belegkette-teilrechnungen.md) | Belegkette Angebot→Auftrag→Lieferschein/Rechnung, Teilrechnungen | accepted |
| [0017](0017-fuhrpark.md) | Fuhrpark | accepted |
| [0018](0018-kosten-kalkulation.md) | Betriebliche Kosten und Kalkulation | accepted |
| [0019](0019-auswertungen-dashboard-exporte.md) | Auswertungen, Dashboard, Exporte | accepted |
| [0020](0020-mitarbeiter-zuweisung-kalender.md) | Mitarbeiter-Zuweisung für Termine + Kollisionserkennung + Auftrags-Zuweisung | accepted |
| [0021](0021-beleg-pdf-export.md) | PDF-Export für Belege + Empfehlung zur unveränderlichen Ablage | accepted |
| [0022](0022-belegqualitaet-firmenprofil-rabatte.md) | Belegqualität: Firmenprofil, Gruppen im PDF, Positionspflege, Rabatte | accepted |
| [0023](0023-agpl-lizenzwechsel.md) | Lizenzwechsel von MIT auf AGPL-3.0-or-later | accepted |

Format: siehe jede ADR-Datei selbst (Kontext / Entscheidung / Konsequenzen / Alternativen erwogen).
Einmal `accepted` werden ADRs nicht mehr editiert; eine neue Entscheidung ersetzt eine alte per
`superseded-by ADR-XXXX` im Status-Feld.
