<template>
	<div class="erp-orders">
		<div class="erp-orders__header">
			<h3>Aufträge</h3>
			<button @click="toggleCreate">+ Auftrag</button>
		</div>

		<form v-if="showCreate" class="erp-orders__create" @submit.prevent="submitCreate">
			<input v-model="newOrder.title" placeholder="Auftragstitel" required>
			<ContactPicker v-model="newOrder.customerContactUid" placeholder="Kunde (optional)" />
			<input v-model="newOrder.description" placeholder="Beschreibung (optional)">
			<button type="submit">Anlegen</button>
		</form>

		<p v-if="loadError" class="erp-orders__error">{{ loadError }}</p>

		<table v-if="orders.length" class="erp-orders__table">
			<thead>
				<tr><th>Titel</th><th>Kunde</th><th>Status</th></tr>
			</thead>
			<tbody>
				<tr v-for="o in orders" :key="o.id" class="erp-orders__row" @click="open(o.id)">
					<td>{{ o.title }}</td>
					<td>{{ o.customerContactUid || '—' }}</td>
					<td><span class="erp-status-badge" :class="`is-${o.status}`">{{ statusLabel(o.status) }}</span></td>
				</tr>
			</tbody>
		</table>
		<p v-else-if="!loadError">Noch keine Aufträge in diesem Projekt.</p>
	</div>
</template>

<script>
import { createOrder, fetchOrders } from '../services/ordersApi.js'
import ContactPicker from '../components/ContactPicker.vue'

const STATUS_LABELS = { draft: 'Entwurf', confirmed: 'Bestätigt', done: 'Abgeschlossen' }

export default {
	name: 'AuftraegeView',
	components: { ContactPicker },
	props: {
		projectId: { type: [String, Number], required: true },
		// Kunde des Projekts — wird beim Öffnen des Anlegen-Formulars als
		// Vorbelegung für den Auftragskunden übernommen (Nutzerwunsch
		// 2026-08-21, generalisiert aus AngeboteView), bleibt änderbar.
		customerContactUid: { type: String, default: null },
	},
	data() {
		return {
			orders: [],
			loadError: null,
			showCreate: false,
			newOrder: { title: '', customerContactUid: null, description: '' },
		}
	},
	async mounted() {
		await this.load()
	},
	methods: {
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		toggleCreate() {
			this.showCreate = !this.showCreate
			if (this.showCreate) {
				this.newOrder.customerContactUid = this.customerContactUid ?? null
			}
		},
		async load() {
			try {
				this.orders = await fetchOrders(this.projectId)
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async submitCreate() {
			try {
				await createOrder(this.projectId, {
					title: this.newOrder.title,
					customerContactUid: this.newOrder.customerContactUid || null,
					description: this.newOrder.description || null,
				})
				this.newOrder = { title: '', customerContactUid: null, description: '' }
				this.showCreate = false
				await this.load()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		open(id) {
			this.$router.push({ name: 'auftrag-detail', params: { id } })
		},
	},
}
</script>

<style scoped>
.erp-orders { padding: 4px 0; }
.erp-orders__header { display: flex; align-items: center; justify-content: space-between; max-width: 900px; }
.erp-orders__create { display: flex; gap: 8px; margin: 12px 0; align-items: center; flex-wrap: wrap; }
.erp-orders__error { color: var(--color-error-text, #c00); }
.erp-orders__table { border-collapse: collapse; width: 100%; max-width: 900px; }
.erp-orders__table th, .erp-orders__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.erp-orders__row { cursor: pointer; }
.erp-orders__row:hover { background: var(--color-background-hover); }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
</style>
