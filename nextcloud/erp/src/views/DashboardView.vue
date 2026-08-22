<template>
	<div class="erp-dashboard">
		<h2>Dashboard</h2>
		<p v-if="loadError" class="erp-dashboard__error">{{ loadError }}</p>

		<div v-if="summary" class="erp-dashboard__grid">
			<div class="erp-tile">
				<h3>Offene Angebote</h3>
				<p class="erp-tile__value">{{ summary.openQuotes.count }}</p>
				<p>{{ formatCurrency(summary.openQuotes.netTotal) }} netto</p>
			</div>
			<div class="erp-tile">
				<h3>Projekte in Bearbeitung</h3>
				<p class="erp-tile__value">{{ summary.projectsInProgress }}</p>
			</div>
			<div class="erp-tile">
				<h3>Anstehende Termine</h3>
				<p>Gespiegelt aus Nextcloud Calendar.</p>
				<span class="erp-tile__phase">Phase 3</span>
			</div>
			<div class="erp-tile" :class="{ 'is-warning': summary.openInvoices.overdueCount > 0 }">
				<h3>Offene Rechnungen</h3>
				<p class="erp-tile__value">{{ summary.openInvoices.count }}</p>
				<p>{{ formatCurrency(summary.openInvoices.grossTotal) }} brutto</p>
				<p v-if="summary.openInvoices.overdueCount > 0" class="erp-tile__warning">
					davon {{ summary.openInvoices.overdueCount }} überfällig ({{ formatCurrency(summary.openInvoices.overdueGrossTotal) }})
				</p>
			</div>
			<div class="erp-tile" :class="{ 'is-warning': summary.lowStockCount > 0 }">
				<h3>Mindestbestand</h3>
				<p class="erp-tile__value">{{ summary.lowStockCount }}</p>
				<p>Artikel/Lagerort-Kombinationen unter Mindestbestand.</p>
			</div>
			<div class="erp-tile">
				<h3>Bestellvorschläge</h3>
				<p class="erp-tile__value">{{ summary.purchaseSuggestionCount }}</p>
				<router-link :to="{ name: 'lager' }">Zum Lager →</router-link>
			</div>
			<div class="erp-tile" :class="{ 'is-warning': summary.vehiclesDueSoon > 0 }">
				<h3>Fällige TÜV/Werkstatt</h3>
				<p class="erp-tile__value">{{ summary.vehiclesDueSoon }}</p>
				<p>Fahrzeuge mit anstehendem oder überfälligem Termin (30 Tage).</p>
			</div>
			<div class="erp-tile">
				<h3>Fuhrparkkosten Monat</h3>
				<p class="erp-tile__value">{{ formatCurrency(summary.fuelCostsThisMonth) }}</p>
			</div>
			<div class="erp-tile">
				<h3>Gemeinkostenrate</h3>
				<p class="erp-tile__value">{{ formatCurrency(summary.internalHourlyRate) }}/h</p>
				<router-link :to="{ name: 'kosten-kalkulation' }">Zur Kalkulation →</router-link>
			</div>
			<div class="erp-tile">
				<h3>Mein Zeitkonto (Monat)</h3>
				<p class="erp-tile__value" :class="{ 'is-warning-text': summary.timeAccount.balanceHours < 0 }">
					{{ formatHours(summary.timeAccount.balanceHours) }} h
				</p>
				<p>Soll {{ formatHours(summary.timeAccount.sollHours) }} h · Ist {{ formatHours(summary.timeAccount.istHours) }} h</p>
				<p v-if="summary.ownPendingRequests > 0">{{ summary.ownPendingRequests }} eigene Anträge offen.</p>
			</div>
			<div class="erp-tile">
				<h3>API & Sync</h3>
				<p>API v1 aktiv, Flutter-Sync noch nicht gebaut.</p>
				<span class="erp-tile__phase">Phase 2</span>
			</div>
		</div>

		<section class="erp-dashboard__export">
			<h3>Export für Steuerberater/Buchhaltung</h3>
			<p>CSV aller ausgestellten Rechnungen (Rechnungsnummer, Datum, Netto, MwSt., Brutto, Status, bezahlter Betrag).</p>
			<form class="erp-dashboard__export-form" @submit.prevent>
				<label>Von <input v-model="exportFrom" type="date"></label>
				<label>Bis <input v-model="exportTo" type="date"></label>
				<a :href="exportUrl" target="_blank" rel="noopener" class="erp-dashboard__export-link">CSV herunterladen</a>
			</form>
		</section>
	</div>
</template>

<script>
import { fetchDashboardSummary, invoicesCsvExportUrl } from '../services/reportingApi.js'

export default {
	name: 'DashboardView',
	data() {
		return {
			summary: null,
			loadError: null,
			exportFrom: '',
			exportTo: '',
		}
	},
	computed: {
		exportUrl() {
			return invoicesCsvExportUrl(this.exportFrom || null, this.exportTo || null, null)
		},
	},
	async mounted() {
		try {
			this.summary = await fetchDashboardSummary()
		} catch (e) {
			this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		}
	},
	methods: {
		formatCurrency(value) {
			return `${Number(value ?? 0).toFixed(2)} €`
		},
		formatHours(value) {
			return Number(value ?? 0).toFixed(2)
		},
	},
}
</script>

<style scoped>
.erp-dashboard {
	padding: 20px;
}
.erp-dashboard__error {
	color: var(--color-error-text, #c00);
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
.erp-tile.is-warning {
	border-color: var(--color-error, #c00);
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
.erp-tile__value {
	font-size: 22px;
	font-weight: bold;
	color: var(--color-main-text);
}
.erp-tile__warning {
	color: var(--color-error-text, #c00);
}
.is-warning-text {
	color: var(--color-error-text, #c00);
}
.erp-tile__phase {
	display: inline-block;
	font-size: 11px;
	padding: 2px 8px;
	border-radius: 10px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-text, inherit);
}
.erp-dashboard__export {
	margin-top: 24px;
	max-width: 600px;
}
.erp-dashboard__export-form {
	display: flex;
	gap: 12px;
	align-items: end;
	flex-wrap: wrap;
}
.erp-dashboard__export-form label {
	display: flex;
	flex-direction: column;
	font-size: 12px;
}
.erp-dashboard__export-link {
	padding: 8px 12px;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text, #fff);
	border-radius: var(--border-radius, 4px);
	text-decoration: none;
	height: fit-content;
}
</style>
