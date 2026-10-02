# ADR-0036: Deep-Imports für @nextcloud/vue statt Paket-Barrel

**Status:** accepted
**Datum:** 2026-10-02

## Kontext

`status.md` führte seit dem Phase-1-Skeleton als bekannte Einschränkung:
"Frontend-Bundle ist noch nicht auf Komponentenebene tree-geshaked
(Warnung beim Build)."

**Befund beim Umsetzen:** Genau eine Stelle im gesamten Frontend
importiert aus `@nextcloud/vue` — `App.vue`:

```js
import { NcAppContent, NcAppNavigation, NcAppNavigationItem, NcContent } from '@nextcloud/vue'
```

Das importiert aus dem **Paket-Barrel** (`./dist/index.mjs`), nicht aus
den einzelnen Komponenten. Trotz ESM/`sideEffects: false` konnte
Webpack daraus nicht zuverlässig nur die vier tatsächlich genutzten
Komponenten herausschälen — im produktiven Build landeten zusätzlich
eigene Chunks für `NcDateTimePicker`, `NcColorPicker`, `NcSelect`
(ungenutzt) sowie die kompletten transitiven Abhängigkeiten `emoji-mart-
vue-fast` und `rehype-highlight` (Markdown-/Emoji-Funktionalität anderer
`@nextcloud/vue`-Komponenten, die dieses Projekt nirgends verwendet) im
Bundle.

## Entscheidung

**Deep-Imports über die von `@nextcloud/vue` offiziell bereitgestellten
Subpath-Exports** (`package.json`: `"./components/*": {"import":
"./dist/components/*/index.mjs"}`, Teil der öffentlichen Paket-API seit
Version 9):

```js
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
```

Jede Komponente wird jetzt direkt aus ihrem eigenen Entry-Point
importiert, der ausschließlich ihre eigenen Abhängigkeiten lädt —
keine Konfigurationsänderung an Webpack nötig, reine Import-Pfad-
Änderung an der einzigen betroffenen Stelle.

**Messbares Ergebnis:** `erp-main.js` von 3,55 MiB auf 1,06 MiB
(≈ 70 % kleiner); die separaten `NcDateTimePicker`/`NcColorPicker`/
`NcSelect`-Chunks sowie `emoji-mart-vue-fast`/`rehype-highlight`
verschwinden komplett aus dem Build.

## Nicht Teil dieser Phase

- **Keine routenbasierte Code-Splitting** (`component: () =>
  import('./views/XView.vue')` statt statischem Import im Router) —
  das verbleibende ≈1 MiB ist größtenteils die eigene Anwendungslogik
  (30+ View-Dateien in einem Bundle), kein Library-Ballast mehr. Lazy
  Route-Loading wäre der nächste, deutlich invasivere Schritt (Async-
  Komponenten, Ladezustände) mit eigenem Risiko/Aufwand — separate
  Entscheidung, falls die Bundle-Größe weiterhin stört.
- **Keine Prüfung weiterer Pakete** auf denselben Barrel-Import-Effekt —
  `@nextcloud/vue` war die einzige Bibliothek im Projekt mit diesem
  Muster (`vue`/`vue-router`/`@nextcloud/axios`/`@nextcloud/router`
  werden bereits granular genutzt).

## Konsequenzen

- Einzige geänderte Datei: `src/App.vue` (vier Import-Zeilen). Keine
  Verhaltensänderung — dieselben vier Komponenten, derselbe Default-
  Export je Subpath.
- Bei einem künftigen `@nextcloud/vue`-Upgrade muss geprüft werden, ob
  die Subpath-Export-Struktur unverändert bleibt (bisher stabil seit
  Version 9, Teil der dokumentierten Paket-API, keine interne Implementierungs­-
  annahme wie bei ADR-0031).

## Alternativen erwogen

- **Webpack-Konfiguration verschärfen** (z. B. `optimization.
  sideEffects`/`providedExports` explizit erzwingen): verworfen — das
  Problem lag nicht an der Webpack-Konfiguration (die geteilte
  `@nextcloud/webpack-vue-config`-Basis war bereits korrekt
  eingestellt), sondern am Barrel-Import selbst. Die Import-Pfad-
  Änderung behebt die Ursache direkter und ohne Risiko für andere
  Module.
