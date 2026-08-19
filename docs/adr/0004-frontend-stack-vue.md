# ADR-0004: Frontend-Stack — Vue.js mit `@nextcloud/vue`

**Status:** accepted
**Datum:** 2026-08-19

## Kontext

Die Roadmap fragt nach dem Frontend-Stack: "Vue/Nextcloud UI oder anderer
offizieller Weg". Die offizielle Nextcloud Developer Documentation dokumentiert
Vue.js mit den `@nextcloud/*`-Paketen (`@nextcloud/vue`, `@nextcloud/router`,
`@nextcloud/axios`, `@nextcloud/webpack-vue-config`) als den von Nextcloud selbst
verwendeten und empfohlenen Weg für neue Apps mit reichhaltigem UI.

## Entscheidung

Das Web-UI wird mit Vue 2/3 (je nach aktuell unterstützter Version in
`@nextcloud/vue`) und den offiziellen `@nextcloud/*`-Paketen gebaut. Build über
`@nextcloud/webpack-vue-config`, damit App-Framework-Konventionen (Asset-Pfade,
CSP, Ausgabeverzeichnis) automatisch eingehalten werden.

## Konsequenzen

- UI-Komponenten (Navigation, Tabellen, Buttons, Badges) nutzen `@nextcloud/vue`
  statt eigene Nachbauten — spart Aufwand und bleibt visuell nativ zu Nextcloud,
  wie in den Mockup-Prompts gefordert ("Follow Nextcloud's own design system").
- Bindung an das Vue-/Webpack-Ökosystem von Nextcloud; Versions-Updates von
  `@nextcloud/vue` müssen mitgezogen werden.
- Innerhalb der Vue-App wird clientseitiges Routing (vue-router) für die
  Hauptbereiche (Dashboard, Projekte, …) verwendet, serverseitig liefert eine
  einzelne PHP-Controller-Route die SPA-Hülle aus.

## Alternativen erwogen

- React: kein offizieller/empfohlener Weg für Nextcloud-Apps, mehr
  Eigenintegrationsaufwand für Theming/CSP.
- Reines serverseitiges PHP-Templating ohne SPA: würde der Anforderung
  "dichtes, interaktives Business-Tool" (Tabellen, Drawer, Live-Summen bei
  Angeboten) schlechter gerecht.
