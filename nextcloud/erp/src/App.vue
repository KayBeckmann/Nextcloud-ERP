<template>
	<NcContent app-name="erp">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem
					v-for="item in navItems"
					:key="item.to"
					:name="item.name"
					:to="item.to"
					:exact="item.to === '/'" />
			</template>
		</NcAppNavigation>
		<NcAppContent class="erp-app-content">
			<router-view />
		</NcAppContent>
	</NcContent>
</template>

<script>
// Direkte Deep-Imports statt des Pakets-Barrels (ADR-0036) — @nextcloud/vue
// stellt dafür offizielle Subpath-Exports bereit (`"./components/*"` in
// package.json), damit Webpack jede Komponente für sich tree-shaken kann,
// statt den gesamten Komponenten-Index (inkl. transitiver Abhängigkeiten
// ungenutzter Komponenten wie Emoji-Picker/Markdown-Highlighting) zu laden.
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import router from './router/index.js'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppNavigation,
		NcAppNavigationItem,
		NcAppContent,
	},
	data() {
		return {
			// Navigation wird aus den Router-Routen abgeleitet, statt die
			// Modulliste ein zweites Mal zu pflegen.
			navItems: router.getRoutes()
				.filter((route) => !route.meta?.hideFromNav)
				.map((route) => ({
					to: route.path,
					name: route.meta?.title ?? route.path,
				})),
		}
	},
}
</script>

<style>
/*
 * NcContent hält den App-Rahmen auf Viewport-Höhe. Ohne einen expliziten
 * Flex-Scrollcontainer kann ein hoher Router-Inhalt hinter dem sichtbaren
 * Frame verschwinden, statt innerhalb der ERP-App scrollbar zu sein.
 */
.erp-app-content {
	min-width: 0;
	min-height: 0;
	height: 100%;
	box-sizing: border-box;
	overflow-x: hidden;
	overflow-y: auto;
	padding-bottom: 32px;
}
</style>
