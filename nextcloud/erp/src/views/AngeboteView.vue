<template>
	<div class="erp-quotes">
		<div class="erp-quotes__header">
			<h2>Angebote</h2>
			<button @click="showCreate = !showCreate">+ Angebot</button>
		</div>

		<form v-if="showCreate" class="erp-quotes__create" @submit.prevent="submitCreate">
			<input v-model="newQuote.title" placeholder="Angebotstitel" required>
			<input v-model="newQuote.customerContactUid" placeholder="Kunde (Contact-UID, optional)">
			<button type="submit">Anlegen</button>
		</form>

		<p v-if="loadError" class="erp-quotes__error">{{ loadError }}</p>

		<table v-if="quotes.length" class="erp-quotes__table">
			<thead>
				<tr><th>Nr.</th><th>Titel</th><th>Kunde</th><th>Status</th></tr>
			</thead>
			<tbody>
				<tr v-for="q in quotes" :key="q.id" class="erp-quotes__row" @click="open(q.id)">
					<td>{{ q.quoteNumber }}</td>
					<td>{{ q.title }}</td>
					<td>{{ q.customerContactUid || '—' }}</td>
					<td><span class="erp-status-badge" :class="`is-${q.status}`">{{ statusLabel(q.status) }}</span></td>
				</tr>
			</tbody>
		</table>
		<p v-else-if="!loadError">Noch keine Angebote.</p>
	</div>
</template>

<script>
import { createQuote, fetchQuotes } from '../services/quotesApi.js'

const STATUS_LABELS = { draft: 'Entwurf', sent: 'Versendet', accepted: 'Angenommen', rejected: 'Abgelehnt', expired: 'Abgelaufen' }

export default {
	name: 'AngeboteView',
	data() {
		return {
			quotes: [],
			loadError: null,
			showCreate: false,
			newQuote: { title: '', customerContactUid: '' },
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
				this.quotes = await fetchQuotes()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async submitCreate() {
			await createQuote({ ...this.newQuote, customerContactUid: this.newQuote.customerContactUid || null })
			this.newQuote = { title: '', customerContactUid: '' }
			this.showCreate = false
			await this.load()
		},
		open(id) {
			this.$router.push({ name: 'angebot-detail', params: { id } })
		},
	},
}
</script>

<style scoped>
.erp-quotes { padding: 20px; }
.erp-quotes__header { display: flex; align-items: center; justify-content: space-between; max-width: 900px; }
.erp-quotes__create { display: flex; gap: 8px; margin: 12px 0; }
.erp-quotes__error { color: var(--color-error-text, #c00); }
.erp-quotes__table { border-collapse: collapse; width: 100%; max-width: 900px; }
.erp-quotes__table th, .erp-quotes__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.erp-quotes__row { cursor: pointer; }
.erp-quotes__row:hover { background: var(--color-background-hover); }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
</style>
