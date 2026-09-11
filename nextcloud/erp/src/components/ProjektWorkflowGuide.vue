<template>
	<section class="erp-workflow-guide" aria-labelledby="workflow-guide-title">
		<header class="erp-workflow-guide__header">
			<div>
				<h3 id="workflow-guide-title">Geführter Projektablauf</h3>
				<p>Der Überblick nutzt die vorhandenen Belege und führt keine Aktionen automatisch aus.</p>
			</div>
			<button :disabled="loading" @click="load">Aktualisieren</button>
		</header>

		<p v-if="loading" class="erp-workflow-guide__hint">Ablauf wird geladen …</p>
		<p v-else-if="loadHint" class="erp-workflow-guide__hint">{{ loadHint }}</p>
		<p class="erp-workflow-guide__rights">
			Ihre Rechte ({{ currentUser || 'aktueller Benutzer' }}): {{ rightsSummary }}.
			<template v-if="hasRestrictedStage">Nicht verfügbare Schritte bleiben sichtbar; sie können erst mit Schreibrecht ausgeführt werden.</template>
		</p>

		<ol v-if="!loading" class="erp-workflow-guide__stages">
			<li v-for="stage in stages" :key="stage.key" :class="`is-${stage.state}`">
				<div>
					<strong>{{ stage.title }}</strong>
					<span class="erp-workflow-guide__status">{{ stateLabel(stage.state) }}</span>
					<p>{{ stage.detail }}</p>
				</div>
				<button v-if="stage.action" @click="openAction(stage.action)">{{ stage.action.label }}</button>
			</li>
		</ol>
	</section>
</template>

<script>
import { fetchMyPermissions } from '../services/permissionsApi.js'
import { fetchQuotes } from '../services/quotesApi.js'
import { fetchOrders } from '../services/ordersApi.js'
import { fetchDeliveryNotes } from '../services/deliveryNotesApi.js'
import { fetchInvoices } from '../services/invoicesApi.js'
import { fetchPurchaseOrder, fetchPurchaseOrders } from '../services/purchaseOrdersApi.js'
import { buildWorkflowStages } from '../services/workflowProgress.mjs'

const STATE_LABELS = { complete: 'erledigt', waiting: 'wartet auf nächsten Schritt', todo: 'als Nächstes möglich', blocked: 'Voraussetzung fehlt', restricted: 'keine Schreibberechtigung' }
const RESOURCE_LABELS = { projekte: 'Projekte', angebote: 'Angebote', auftraege: 'Aufträge', lager: 'Lager', lieferscheine: 'Lieferscheine', rechnungen: 'Rechnungen' }

export default {
	name: 'ProjektWorkflowGuide',
	props: {
		project: { type: Object, required: true },
		projectId: { type: [String, Number], required: true },
	},
	emits: ['select-tab'],
	data() {
		return { loading: true, loadHint: null, permissions: {}, currentUser: null, stages: [] }
	},
	computed: {
		rightsSummary() {
			const entries = Object.entries(RESOURCE_LABELS).map(([key, label]) => `${label}: ${this.permissions[key] ?? 'keine'}`)
			return entries.join(' · ')
		},
		hasRestrictedStage() {
			return this.stages.some((stage) => stage.state === 'restricted')
		},
	},
	watch: {
		projectId() { this.load() },
	},
	mounted() { this.load() },
	methods: {
		stateLabel(state) { return STATE_LABELS[state] ?? state },
		errorMessage(error) { return error?.response?.data?.ocs?.meta?.message ?? error?.message ?? String(error) },
		async load() {
			this.loading = true
			this.loadHint = null
			const results = await Promise.allSettled([
				fetchMyPermissions(), fetchQuotes(null, this.projectId), fetchOrders(this.projectId),
				fetchDeliveryNotes(this.projectId), fetchInvoices(null, this.projectId), fetchPurchaseOrders(),
			])
			const [myPermissions, quotes, orders, deliveryNotes, invoices, purchaseOrderList] = results
			const failed = results.filter((result) => result.status === 'rejected')
			this.permissions = myPermissions.status === 'fulfilled' ? myPermissions.value.permissions : {}
			this.currentUser = myPermissions.status === 'fulfilled' ? myPermissions.value.userId : null
			let purchaseOrders = []
			if (purchaseOrderList.status === 'fulfilled') {
				const details = await Promise.allSettled(purchaseOrderList.value.map((order) => fetchPurchaseOrder(order.id)))
				purchaseOrders = details.filter((result) => result.status === 'fulfilled').map((result) => result.value)
				if (details.some((result) => result.status === 'rejected')) failed.push(...details.filter((result) => result.status === 'rejected'))
			}
			this.stages = buildWorkflowStages({
				project: this.project,
				permissions: this.permissions,
				quotes: quotes.status === 'fulfilled' ? quotes.value : [],
				orders: orders.status === 'fulfilled' ? orders.value : [],
				deliveryNotes: deliveryNotes.status === 'fulfilled' ? deliveryNotes.value : [],
				invoices: invoices.status === 'fulfilled' ? invoices.value : [],
				purchaseOrders,
			})
			if (failed.length) this.loadHint = 'Einige Statusdaten konnten nicht geladen werden. Sichtbare Schritte zeigen Ihre Rechte; aktualisieren Sie nach einer Rechteänderung erneut.'
			this.loading = false
		},
		openAction(action) {
			if (action.tab) this.$emit('select-tab', action.tab)
			if (action.route) this.$router.push({ name: action.route })
		},
	},
}
</script>

<style scoped>
.erp-workflow-guide { border: 1px solid var(--color-border); border-radius: 8px; padding: 16px; }
.erp-workflow-guide__header { display: flex; justify-content: space-between; gap: 12px; align-items: start; }
.erp-workflow-guide__header h3 { margin: 0; }
.erp-workflow-guide__header p, .erp-workflow-guide__hint, .erp-workflow-guide__rights { color: var(--color-text-maxcontrast); font-size: 13px; }
.erp-workflow-guide__stages { list-style: none; padding: 0; margin: 16px 0 0; display: grid; gap: 8px; }
.erp-workflow-guide__stages li { border-left: 4px solid var(--color-border); background: var(--color-background-hover); padding: 10px 12px; display: flex; justify-content: space-between; align-items: start; gap: 12px; }
.erp-workflow-guide__stages li p { margin: 5px 0 0; font-size: 13px; }
.erp-workflow-guide__stages .is-complete { border-color: var(--color-success); }
.erp-workflow-guide__stages .is-waiting, .erp-workflow-guide__stages .is-todo { border-color: var(--color-primary-element); }
.erp-workflow-guide__stages .is-blocked, .erp-workflow-guide__stages .is-restricted { border-color: var(--color-warning); }
.erp-workflow-guide__status { margin-left: 8px; color: var(--color-text-maxcontrast); font-size: 12px; }
@media (max-width: 600px) { .erp-workflow-guide__header, .erp-workflow-guide__stages li { flex-direction: column; } }
</style>
