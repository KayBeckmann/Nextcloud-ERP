# ADR-0023: Lizenzwechsel von MIT auf AGPL-3.0-or-later

**Status:** accepted
**Datum:** 2026-09-01
**Ersetzt:** [ADR-0007](0007-mit-lizenz.md)

## Kontext

Im Rahmen des Lizenz-/Dependency-Reviews aus Roadmap-Phase 14 wurden die
direkten Produktions-Abhängigkeiten geprüft:

| Paket | Lizenz |
|---|---|
| `dompdf/dompdf` | LGPL-2.1 |
| `vue`, `vue-router` | MIT |
| `@nextcloud/vue` | **AGPL-3.0-or-later** |
| `@nextcloud/axios` | **GPL-3.0-or-later** |
| `@nextcloud/router` | **GPL-3.0-or-later** |

Webpack baut die eigenen Vue-Komponenten zusammen mit `@nextcloud/vue`,
`@nextcloud/axios` und `@nextcloud/router` zu einer einzigen JS-Datei
(`dist/erp-main.js`). Das ist keine lose Laufzeit-Verknüpfung, sondern ein
echtes Kombinieren von Quellcode in einem gemeinsamen Build-Artefakt — nach
üblichem Verständnis von Copyleft-Lizenzen entsteht dadurch ein "derivative
work", das als Ganzes unter den Bedingungen der jeweils strengsten
beteiligten Lizenz weitergegeben werden muss. Da AGPL-3.0 strenger ist als
GPL-3.0 (zusätzliche Netzwerk-Klausel, siehe unten) und MIT keine
Copyleft-Pflichten kennt, war die bisherige Deklaration "ganzes Repository
MIT" für das ausgelieferte Frontend-Bundle nicht korrekt.

ADR-0007 hatte diesen Fall vorausgesehen ("künftig gegenprüfen") aber noch
nicht abschließend geklärt.

## Entscheidung

Das gesamte Repository wird auf **AGPL-3.0-or-later** umgestellt:

- `LICENSE` enthält den vollständigen AGPL-3.0-Text (Quelle:
  gnu.org/licenses/agpl-3.0.txt).
- `nextcloud/erp/composer.json` und `nextcloud/erp/package.json`:
  `"license": "AGPL-3.0-or-later"`.
- `nextcloud/erp/appinfo/info.xml`: `<licence>agpl</licence>` (Nextcloud-
  App-Store-Konvention, wie bei praktisch jeder Nextcloud-App, die
  `@nextcloud/vue` einbindet).
- `README.md`, `docs/roadmap.md`: Lizenzangaben aktualisiert.

**Kein Split-Licensing** (Backend MIT / Frontend AGPL) — verworfen, siehe
"Alternativen erwogen".

## Konsequenzen

- **Copyleft:** Wer das Repository (oder eine modifizierte Fassung)
  weitergibt, muss den vollständigen korrespondierenden Quellcode unter
  AGPL-3.0-or-later mitliefern. Für ein von Anfang an öffentliches
  Git-Repository ändert das faktisch nichts.
- **Netzwerk-Klausel (der eigentliche Unterschied zu GPL/MIT):** Wird die
  App als gehostete Multi-Tenant-/SaaS-Instanz für fremde Nutzer
  betrieben, haben diese Nutzer Anspruch auf den vollständigen Quellcode
  inkl. aller eigenen Anpassungen — auch ohne dass irgendetwas "verteilt"
  wird. Relevant, falls dieses Add-on je als Hosting-Angebot für Dritte
  betrieben werden sollte (aktuell nicht geplant; das bestehende Modell
  ist "Installation auf der eigenen Nextcloud-Instanz").
- **Kommerzielle Optionalität eingeschränkt:** Eine spätere Closed-Source-
  Verwertung des Backends (analog z. B. zu saasERP) ist mit AGPL für
  *dieses* Repository nicht mehr möglich, ohne den betroffenen Code neu
  zu lizenzieren oder auszulagern.
- ADR-0007 gilt als **superseded-by ADR-0023** (Status-Feld dort
  entsprechend aktualisiert, Inhalt unverändert gelassen).
- Künftige Abhängigkeitswahl (Composer/npm) muss AGPL-3.0-Kompatibilität
  prüfen statt MIT-Kompatibilität — de facto eine Erleichterung, da AGPL/
  GPL-Pakete jetzt unproblematisch sind, während zusätzliche restriktive
  proprietäre Lizenzen weiterhin ausgeschlossen bleiben.

## Alternativen erwogen

- **Split-Licensing** (Backend `lib/`/`appinfo/` MIT, nur das
  Frontend-Bundle AGPL-3.0): würde die PHP-Businesslogik unter einer
  freieren Lizenz halten. Verworfen, weil (a) das Projekt laut Roadmap
  explizit auf tiefe, native Nextcloud-Integration setzt und `@nextcloud/
  vue` als Style-Guide-Grundlage dauerhaft gesetzt bleibt — die
  Split-Situation also nicht vorübergehend ist, sondern der Normalfall;
  (b) ein sauberer Split rechtlich unscharf bleibt, solange Backend und
  Frontend gemeinsam als eine App ausgeliefert werden; (c) praktisch jede
  vergleichbare Nextcloud-App im Ökosystem einheitlich AGPL-3.0 nutzt —
  Konsistenz mit der Ökosystem-Konvention wurde höher gewichtet als die
  theoretische spätere Wiederverwertbarkeit des Backends.
- **MIT beibehalten, `@nextcloud/vue` ersetzen:** verworfen — würde
  bedeuten, natives Nextcloud-UI-Verhalten (Navigation, Modals, Buttons,
  Barrierefreiheit) selbst nachzubauen, ohne belastbaren Vorteil
  gegenüber der Ökosystem-Standardlösung.
