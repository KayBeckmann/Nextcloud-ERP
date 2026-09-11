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
import { NcAppContent, NcAppNavigation, NcAppNavigationItem, NcContent } from '@nextcloud/vue'
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
	overflow-x: hidden;
	overflow-y: auto;
}
</style>
