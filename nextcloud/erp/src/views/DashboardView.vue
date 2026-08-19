<template>
	<div class="erp-dashboard">
		<h2>Dashboard</h2>
		<p class="erp-dashboard__hint">
			Platzhalter-Dashboard (Roadmap Phase 1). Die Kacheln zeigen noch keine echten Daten —
			sie markieren, in welcher Roadmap-Phase die jeweilige Auswertung entsteht.
		</p>
		<div class="erp-dashboard__grid">
			<div v-for="tile in tiles" :key="tile.title" class="erp-tile">
				<h3>{{ tile.title }}</h3>
				<p>{{ tile.description }}</p>
				<span class="erp-tile__phase">{{ tile.phase }}</span>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'DashboardView',
	data() {
		return {
			tiles: [
				{ title: 'Offene Angebote', description: 'Anzahl und Summe offener Angebote.', phase: 'Phase 5' },
				{ title: 'Projekte in Bearbeitung', description: 'Projekte mit Fortschrittsanzeige.', phase: 'Phase 4' },
				{ title: 'Anstehende Termine', description: 'Gespiegelt aus Nextcloud Calendar.', phase: 'Phase 3' },
				{ title: 'Offene Rechnungen', description: 'Betrag und überfällige Posten.', phase: 'Phase 7' },
				{ title: 'Mindestbestand', description: 'Artikel unter Mindestbestand je Lagerort.', phase: 'Phase 8' },
				{ title: 'Bestellvorschläge', description: 'Editierbare Bestellvorschläge, nicht automatisch verbindlich.', phase: 'Phase 8' },
				{ title: 'Fällige TÜV/Werkstatt', description: 'Fahrzeuge mit anstehenden Terminen.', phase: 'Phase 9' },
				{ title: 'Fuhrparkkosten Monat', description: 'Tankkosten und Trend des laufenden Monats.', phase: 'Phase 9' },
				{ title: 'Gemeinkostenrate', description: 'Gemeinkosten je produktiver Stunde.', phase: 'Phase 10' },
				{ title: 'Zeitkonten', description: 'Überstunden-/Urlaubswarnungen.', phase: 'Phase 6' },
				{ title: 'API & Sync', description: 'Status der API v1 und spätere Flutter-Sync-Bereitschaft.', phase: 'Phase 2' },
			],
		}
	},
}
</script>

<style scoped>
.erp-dashboard {
	padding: 20px;
}
.erp-dashboard__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}
.erp-dashboard__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
	gap: 12px;
}
.erp-tile {
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
	padding: 14px;
}
.erp-tile h3 {
	margin: 0 0 6px;
	font-size: 15px;
}
.erp-tile p {
	margin: 0 0 8px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
.erp-tile__phase {
	display: inline-block;
	font-size: 11px;
	padding: 2px 8px;
	border-radius: 10px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-text, inherit);
}
</style>
