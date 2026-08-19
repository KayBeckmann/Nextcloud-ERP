import { createRouter, createWebHistory } from 'vue-router'
import { generateUrl } from '@nextcloud/router'

import DashboardView from '../views/DashboardView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'
import BerechtigungenView from '../views/BerechtigungenView.vue'

// Module, die schon eine echte View statt des generischen Platzhalters haben.
const dedicatedViews = {
	'berechtigungen-saetze': BerechtigungenView,
}

// Hauptbereiche aus Roadmap Phase 1. Module sind bewusst Platzhalter — die
// fachliche Umsetzung folgt in den späteren Roadmap-Phasen (siehe
// docs/roadmap.md im Repo-Root). Exportiert, damit andere Views (z. B. die
// Rechte-Matrix) dieselben Titel/Slugs nutzen können, statt sie zu duplizieren.
export const modules = [
	{
		path: 'projekte',
		title: 'Projekte',
		description: 'Projektliste, Projektdetail, Aufträge — verknüpft mit Kunde, Terminen und Projektordner.',
		phase: 'Phase 4',
	},
	{
		path: 'kalender-personal',
		title: 'Kalender & Personal',
		description: 'Projekttermine, Personalplanung und Abwesenheiten auf Basis von Nextcloud Calendar.',
		phase: 'Phase 3',
	},
	{
		path: 'kunden',
		title: 'Kunden',
		description: 'Kunden auf Basis von Nextcloud Contacts, ergänzt um ERP-Metadaten (Kundennummer, Zahlungsziel, Vertrag).',
		phase: 'Phase 3',
	},
	{
		path: 'lieferanten',
		title: 'Lieferanten',
		description: 'Lieferanten auf Basis von Nextcloud Contacts, verknüpft mit Artikelpreisen.',
		phase: 'Phase 3',
	},
	{
		path: 'artikel',
		title: 'Artikel',
		description: 'Artikelstamm mit Hersteller-Art.-Nr. und Lieferantenpreisen je Lieferant.',
		phase: 'Phase 5',
	},
	{
		path: 'produkte',
		title: 'Produkte',
		description: 'Produkte/Bundles aus Artikeln und Arbeitsleistungen.',
		phase: 'Phase 5',
	},
	{
		path: 'angebote',
		title: 'Angebote',
		description: 'Angebote mit Positionsgruppen, Netto-Gruppensummen und MwSt.-Abschlussblock.',
		phase: 'Phase 5',
	},
	{
		path: 'auftraege',
		title: 'Aufträge',
		description: 'Auftragsabwicklung mit Material- und Stundenbuchung.',
		phase: 'Phase 4',
	},
	{
		path: 'rechnungen',
		title: 'Rechnungen',
		description: 'Rechnungen, Gutschriften und Zahlungsstatus.',
		phase: 'Phase 7',
	},
	{
		path: 'lager',
		title: 'Lager',
		description: 'Lagerorte, Soll-/Ist-Mengen, Inventur und Bestellvorschläge.',
		phase: 'Phase 8',
	},
	{
		path: 'fuhrpark',
		title: 'Fuhrpark',
		description: 'Fahrzeugstamm, TÜV-/Werkstatttermine und Tankbelege.',
		phase: 'Phase 9',
	},
	{
		path: 'kosten-kalkulation',
		title: 'Kosten & Kalkulation',
		description: 'Betriebliche Kosten, Gemeinkostenrate und Aufschlagskalkulation.',
		phase: 'Phase 10',
	},
	{
		path: 'stunden-zeitkonto',
		title: 'Stunden & Zeitkonto',
		description: 'Zeiterfassung, Zeitkonten, Urlaub/Abwesenheiten und Überstunden.',
		phase: 'Phase 6',
	},
	{
		path: 'berechtigungen-saetze',
		title: 'Berechtigungen & Sätze',
		description: 'ERP-Rechte-Matrix und Verrechnungssätze pro User/Gruppe — technisch getrennte Systeme.',
		phase: 'Phase 2 / 6',
	},
	{
		path: 'api-sync',
		title: 'API & Sync',
		description: 'Status der API v1 und Sync-Vorbereitung für den späteren Flutter-Client.',
		phase: 'Phase 2',
	},
	{
		path: 'einstellungen',
		title: 'Einstellungen',
		description: 'MwSt.-Sätze, Nextcloud-Integrationsstatus, Test-/Docker-Umgebung, Lizenz.',
		phase: 'Phase 2 / 5',
	},
]

const routes = [
	{ path: '/', name: 'dashboard', component: DashboardView, meta: { title: 'Dashboard' } },
	...modules.map((m) => {
		const dedicated = dedicatedViews[m.path]
		return {
			path: `/${m.path}`,
			name: m.path,
			component: dedicated ?? PlaceholderView,
			// props nur für den generischen Platzhalter relevant; die dedizierte
			// View holt sich ihre Daten selbst über die API.
			props: dedicated ? false : { title: m.title, description: m.description, phase: m.phase },
			meta: { title: m.title },
		}
	}),
]

export default createRouter({
	// Bewusst die von @nextcloud/router generierte "pretty" URL als Basis:
	// Nextclouds eigene App-Navigation verlinkt ebenfalls immer die pretty
	// Form (ohne /index.php/), solange mod_rewrite aktiv ist (Standardfall).
	history: createWebHistory(generateUrl('/apps/erp')),
	routes,
})
