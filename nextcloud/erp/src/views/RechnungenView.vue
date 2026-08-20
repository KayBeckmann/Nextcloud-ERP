<template>
	<div class="erp-invoices">
		<div class="erp-invoices__header">
			<h2>Rechnungen</h2>
			<button @click="showCreate = !showCreate">+ Rechnung</button>
		</div>

		<form v-if="showCreate" class="erp-invoices__create" @submit.prevent="submitCreate">
			<input v-model="newInvoice.title" placeholder="Rechnungstitel" required>
			<select v-model="newInvoice.type">
				<option value="invoice">Rechnung</option>
				<option value="partial">Abschlagsrechnung</option>
				<option value="final">Schlussrechnung</option>
			</select>
			<input v-model="newInvoice.customerContactUid" placeholder="Kunde (Contact-UID, optional)">
			<input v-model="newInvoice.dueDate" type="date" placeholder="Fällig am">
			<button type="submit">Anlegen</button>
		</form>

		<p v-if="loadError" class="erp-invoices__error">{{ loadError }}</p>

		<table v-if="invoices.length" class="erp-invoices__table">
			<thead>
				<tr><th>Nr.</th><th>Titel</th><th>Kunde</th><th>Fällig</th><th>Status</th></tr>
			</thead>
			<tbody>
				<tr v-for="i in invoices" :key="i.id" class="erp-invoices__row" @click="open(i.id)">
					<td>{{ i.invoiceNumber || '(Entwurf)' }}</td>
					<td>{{ i.title }}</td>
					<td>{{ i.customerContactUid || '—' }}</td>
					<td>{{ i.dueDate || '—' }}</td>
					<td><span class="erp-status-badge" :class="`is-${i.status}`">{{ statusLabel(i.status) }}</span></td>
				</tr>
			</tbody>
		</table>
		<p v-else-if="!loadError">Noch keine Rechnungen.</p>
	</div>
</template>

<script>
import { createInvoice, fetchInvoices } from '../services/invoicesApi.js'

const STATUS_LABELS = { draft: 'Entwurf', issued: 'Ausgestellt', partially_paid: 'Teilweise bezahlt', paid: 'Bezahlt', cancelled: 'Storniert' }

export default {
	name: 'RechnungenView',
	data() {
		return {
			invoices: [],
			loadError: null,
			showCreate: false,
			newInvoice: { title: '', type: 'invoice', customerContactUid: '', dueDate: '' },
		}
	},
	async mounted() {
		await this.load()
	},
	methods: {
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		async load() {
			try {
				this.invoices = await fetchInvoices()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async submitCreate() {
			await createInvoice({
				...this.newInvoice,
				customerContactUid: this.newInvoice.customerContactUid || null,
				dueDate: this.newInvoice.dueDate || null,
			})
			this.newInvoice = { title: '', type: 'invoice', customerContactUid: '', dueDate: '' }
			this.showCreate = false
			await this.load()
		},
		open(id) {
			this.$router.push({ name: 'rechnung-detail', params: { id } })
		},
	},
}
</script>

<style scoped>
.erp-invoices { padding: 20px; }
.erp-invoices__header { display: flex; align-items: center; justify-content: space-between; max-width: 900px; }
.erp-invoices__create { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; }
.erp-invoices__error { color: var(--color-error-text, #c00); }
.erp-invoices__table { border-collapse: collapse; width: 100%; max-width: 900px; }
.erp-invoices__table th, .erp-invoices__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.erp-invoices__row { cursor: pointer; }
.erp-invoices__row:hover { background: var(--color-background-hover); }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
</style>
