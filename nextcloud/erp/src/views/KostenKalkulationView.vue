<template>
	<div class="erp-costs">
		<div class="erp-costs__header">
			<h2>Kosten &amp; Kalkulation</h2>
			<label>Jahr
				<input v-model.number="year" type="number" @change="loadOverview">
			</label>
		</div>

		<p v-if="loadError" class="erp-costs__error">{{ loadError }}</p>

		<nav class="erp-costs__tabs">
			<button v-for="t in tabs" :key="t" :class="{ 'is-active': tab === t }" @click="tab = t">{{ t }}</button>
		</nav>

		<section v-if="tab === 'Kostenarten'" class="erp-costs__section">
			<form class="erp-costs__form" @submit.prevent="submitCreateEntry">
				<select v-model="newEntry.category" required>
					<option value="" disabled>Kostenart wählen</option>
					<option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
				</select>
				<input v-model="newEntry.title" placeholder="Bezeichnung" required>
				<input v-model.number="newEntry.monthlyAmount" type="number" step="0.01" min="0" placeholder="Betrag/Monat" required>
				<select v-model.number="newEntry.month">
					<option v-for="m in 12" :key="m" :value="m">{{ monthLabel(m) }}</option>
				</select>
				<input v-model="newEntry.notes" placeholder="Notiz">
				<button type="submit">Anlegen</button>
			</form>

			<table v-if="entries.length" class="erp-costs__table">
				<thead><tr><th>Kostenart</th><th>Bezeichnung</th><th>Monat</th><th>Betrag/Monat</th><th>Notiz</th><th></th></tr></thead>
				<tbody>
					<tr v-for="e in entries" :key="e.id">
						<td>{{ categoryLabel(e.category) }}</td>
						<td>{{ e.title }}</td>
						<td>{{ monthLabel(e.month) }}</td>
						<td>{{ formatCurrency(e.monthlyAmount) }}</td>
						<td>{{ e.notes || '—' }}</td>
						<td><button @click="submitRemoveEntry(e.id)">Löschen</button></td>
					</tr>
				</tbody>
			</table>
			<p v-else>Noch keine Kostenposten für {{ year }} erfasst.</p>

			<div v-if="costsByCategory && Object.keys(costsByCategory).length" class="erp-costs__summary">
				<h3>Summe je Kostenart (Jahr)</h3>
				<table class="erp-costs__table">
					<thead><tr><th>Kostenart</th><th>Summe/Jahr</th></tr></thead>
					<tbody>
						<tr v-for="(sum, cat) in costsByCategory" :key="cat">
							<td>{{ categoryLabel(cat) }}</td>
							<td>{{ formatCurrency(sum) }}</td>
						</tr>
					</tbody>
				</table>
				<p><strong>Gesamt: {{ formatCurrency(annualCosts) }}</strong></p>
			</div>
		</section>

		<section v-else-if="tab === 'Kalkulation'" class="erp-costs__section">
			<h3>Produktive Stunden &amp; Aufschläge</h3>
			<form class="erp-costs__form" @submit.prevent="submitUpdateSettings">
				<label>Produktive Stunden/Jahr
					<input v-model.number="settingsForm.productiveHoursPerYear" type="number" step="0.01" min="0" required>
				</label>
				<label>Materialaufschlag %
					<input v-model.number="settingsForm.materialSurchargePercent" type="number" step="0.01" min="0" required>
				</label>
				<label>Produktaufschlag %
					<input v-model.number="settingsForm.productSurchargePercent" type="number" step="0.01" min="0" required>
				</label>
				<button type="submit">Speichern</button>
			</form>

			<div class="erp-costs__rate">
				<p>Jahreskosten {{ year }}: <strong>{{ formatCurrency(annualCosts) }}</strong></p>
				<p>Interner Stundensatz: <strong>{{ formatCurrency(internalHourlyRate) }}/h</strong>
					<span class="erp-costs__hint"> (rein informativ — wird nicht automatisch in Verrechnungssätze übernommen, ADR-0018)</span>
				</p>
			</div>

			<h3>Aufschlagsrechner</h3>
			<form class="erp-costs__form" @submit.prevent>
				<label>Basiskosten
					<input v-model.number="calculator.baseCost" type="number" step="0.01" min="0">
				</label>
				<label>Aufschlag
					<select v-model="calculator.kind">
						<option value="material">Material ({{ settingsForm.materialSurchargePercent }} %)</option>
						<option value="product">Produkt ({{ settingsForm.productSurchargePercent }} %)</option>
						<option value="custom">Individuell</option>
					</select>
				</label>
				<input v-if="calculator.kind === 'custom'" v-model.number="calculator.customPercent" type="number" step="0.01" placeholder="% individuell">
			</form>
			<p>Verkaufspreis: <strong>{{ formatCurrency(calculatedPrice) }}</strong></p>
		</section>
	</div>
</template>

<script>
import { fetchYearOverview, createCostEntry, removeCostEntry, updateCostSettings } from '../services/costsApi.js'

const CATEGORY_LABELS = {
	rent: 'Miete',
	phone_internet: 'Telefon/Internet',
	software: 'Software',
	salaries: 'Gehälter',
	payroll_costs: 'Lohnnebenkosten',
	insurance: 'Versicherungen',
	professional_association: 'Berufsgenossenschaft',
	tax_advisor: 'Steuerberater',
	vehicles: 'Fahrzeuge',
	tools: 'Werkzeuge',
	energy: 'Energie',
	financing_leasing: 'Finanzierung/Leasing',
	marketing: 'Marketing',
	other: 'Sonstiges',
}
const MONTH_LABELS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']

export default {
	name: 'KostenKalkulationView',
	data() {
		return {
			tab: 'Kostenarten',
			tabs: ['Kostenarten', 'Kalkulation'],
			year: new Date().getFullYear(),
			loadError: null,
			entries: [],
			costsByCategory: {},
			annualCosts: 0,
			internalHourlyRate: 0,
			newEntry: { category: '', title: '', monthlyAmount: 0, month: new Date().getMonth() + 1, notes: '' },
			settingsForm: { productiveHoursPerYear: 1600, materialSurchargePercent: 0, productSurchargePercent: 0 },
			calculator: { baseCost: 0, kind: 'material', customPercent: 0 },
			categories: Object.entries(CATEGORY_LABELS).map(([value, label]) => ({ value, label })),
		}
	},
	computed: {
		calculatedPrice() {
			const percent = this.calculator.kind === 'material'
				? this.settingsForm.materialSurchargePercent
				: this.calculator.kind === 'product'
					? this.settingsForm.productSurchargePercent
					: this.calculator.customPercent
			return this.calculator.baseCost * (1 + (percent || 0) / 100)
		},
	},
	async mounted() {
		await this.loadOverview()
	},
	methods: {
		categoryLabel(value) {
			return CATEGORY_LABELS[value] ?? value
		},
		monthLabel(month) {
			return MONTH_LABELS[month - 1] ?? month
		},
		formatCurrency(value) {
			return `${Number(value ?? 0).toFixed(2)} €`
		},
		errorMessage(e) {
			return e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		},
		async loadOverview() {
			try {
				const overview = await fetchYearOverview(this.year)
				this.entries = overview.entries
				this.costsByCategory = overview.costsByCategory
				this.annualCosts = overview.annualCosts
				this.internalHourlyRate = overview.internalHourlyRate
				this.settingsForm = {
					productiveHoursPerYear: overview.settings.productiveHoursPerYear,
					materialSurchargePercent: overview.settings.materialSurchargePercent,
					productSurchargePercent: overview.settings.productSurchargePercent,
				}
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitCreateEntry() {
			try {
				await createCostEntry({ ...this.newEntry, year: this.year, notes: this.newEntry.notes || null })
				this.newEntry = { category: '', title: '', monthlyAmount: 0, month: new Date().getMonth() + 1, notes: '' }
				await this.loadOverview()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitRemoveEntry(id) {
			try {
				await removeCostEntry(id)
				await this.loadOverview()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitUpdateSettings() {
			try {
				await updateCostSettings({ year: this.year, ...this.settingsForm })
				await this.loadOverview()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
	},
}
</script>

<style scoped>
.erp-costs { padding: 20px; max-width: 960px; }
.erp-costs__header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.erp-costs__error { color: var(--color-error-text, #c00); }
.erp-costs__tabs { display: flex; gap: 4px; margin: 16px 0; border-bottom: 1px solid var(--color-border); }
.erp-costs__tabs button { background: none; border: none; padding: 8px 12px; cursor: pointer; }
.erp-costs__tabs button.is-active { border-bottom: 2px solid var(--color-primary-element); font-weight: bold; }
.erp-costs__form { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; align-items: center; }
.erp-costs__table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.erp-costs__table th, .erp-costs__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.erp-costs__summary { margin-top: 24px; }
.erp-costs__rate { margin: 16px 0; }
.erp-costs__hint { color: var(--color-text-maxcontrast); font-size: 12px; }
</style>
