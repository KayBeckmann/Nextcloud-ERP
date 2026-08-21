<template>
	<div class="erp-invoice-detail">
		<p v-if="loadError" class="erp-invoice-detail__error">{{ loadError }}</p>
		<template v-else-if="invoice">
			<header>
				<h2>{{ invoice.invoiceNumber || '(Entwurf)' }} — {{ invoice.title }}</h2>
				<span class="erp-status-badge" :class="`is-${invoice.status}`">{{ statusLabel(invoice.status) }}</span>
				<span v-if="invoice.isOverdue" class="erp-status-badge is-overdue">Überfällig</span>
			</header>

			<section class="erp-invoice-detail__meta">
				<p><strong>Typ:</strong> {{ typeLabel(invoice.type) }}</p>
				<p><strong>Kunde:</strong> {{ invoice.customerContactUid || '—' }}</p>
				<p><strong>Fällig am:</strong> {{ invoice.dueDate || '—' }}</p>
				<p v-if="invoice.quoteId"><strong>Aus Angebot:</strong> #{{ invoice.quoteId }}</p>
				<p><strong>Bezahlt:</strong> {{ formatMoney(invoice.paidAmount) }} / {{ invoice.calculation ? formatMoney(invoice.calculation.grossTotal) : '—' }}</p>
				<p v-if="invoice.documentFileId"><a :href="openInFilesUrl(invoice.documentFileId)" target="_blank" rel="noopener">Rechnungsdokument öffnen</a></p>
			</section>

			<section class="erp-invoice-detail__positions">
				<h3>Positionen</h3>
				<table>
					<thead>
						<tr><th>Typ</th><th>Beschreibung</th><th>Menge</th><th>Einheit</th><th>EP netto</th><th>MwSt.</th><th>Gesamt netto</th><th></th></tr>
					</thead>
					<tbody>
						<tr v-for="p in invoice.positions" :key="p.id">
							<td>{{ typeLabel(p.positionType) }}</td>
							<td>{{ p.description }}</td>
							<td>{{ p.quantity }}</td>
							<td>{{ p.unit }}</td>
							<td>{{ formatMoney(p.unitPriceNet) }}</td>
							<td>{{ p.vatRatePercent }}%</td>
							<td>{{ formatMoney(p.netTotal) }}</td>
							<td><button v-if="invoice.status === 'draft'" @click="removePos(p.id)">✕</button></td>
						</tr>
					</tbody>
				</table>

				<form v-if="invoice.status === 'draft'" class="erp-invoice-detail__position-form" @submit.prevent="submitPosition">
					<select v-model="newPosition.positionType">
						<option value="custom">Freitext</option>
						<option value="article">Artikel</option>
						<option value="product">Produkt</option>
						<option value="labor">Arbeitsstunden</option>
					</select>
					<input v-model="newPosition.description" placeholder="Beschreibung" required>
					<input v-model.number="newPosition.quantity" type="number" step="0.01" placeholder="Menge" required>
					<input v-model="newPosition.unit" placeholder="Einheit" style="max-width:70px">
					<input v-model.number="newPosition.unitPriceNet" type="number" step="0.01" placeholder="EP netto" required>
					<select v-model.number="newPosition.vatRatePercent">
						<option v-for="v in vatRates" :key="v.id" :value="v.percentage">{{ v.name }}</option>
					</select>
					<button type="submit">Hinzufügen</button>
				</form>

				<button v-if="invoice.status === 'draft'" class="erp-invoice-detail__issue" @click="doIssue">Rechnung ausstellen</button>
			</section>

			<section v-if="invoice.calculation" class="erp-invoice-detail__summary">
				<h3>Abschlussblock</h3>
				<p>Netto-Zwischensumme: <strong>{{ formatMoney(invoice.calculation.netSubtotal) }}</strong></p>
				<p v-for="v in invoice.calculation.vatBreakdown" :key="v.ratePercent">
					+ MwSt. {{ v.ratePercent }}% auf {{ formatMoney(v.netBase) }}: {{ formatMoney(v.vatAmount) }}
				</p>
				<p class="erp-invoice-detail__gross">Brutto-Gesamt: <strong>{{ formatMoney(invoice.calculation.grossTotal) }}</strong></p>
			</section>

			<section v-if="['issued', 'partially_paid', 'paid'].includes(invoice.status)" class="erp-invoice-detail__payment">
				<h3>Zahlung</h3>
				<form v-if="invoice.status !== 'paid'" class="erp-invoice-detail__payment-form" @submit.prevent="submitPayment">
					<input v-model.number="paymentAmount" type="number" step="0.01" min="0.01" placeholder="Betrag" required>
					<button type="submit">Zahlung erfassen</button>
				</form>
			</section>

			<section v-if="['issued', 'partially_paid', 'paid'].includes(invoice.status)" class="erp-invoice-detail__credit-notes">
				<h3>Gutschriften</h3>
				<table v-if="creditNotes.length">
					<thead><tr><th>Nr.</th><th>Grund</th><th>Vollstorno</th><th>Status</th></tr></thead>
					<tbody>
						<tr v-for="cn in creditNotes" :key="cn.id">
							<td>{{ cn.creditNoteNumber || '(Entwurf)' }}</td>
							<td>{{ cn.reason }}</td>
							<td>{{ cn.cancelsInvoice ? 'ja' : 'nein' }}</td>
							<td><span class="erp-status-badge" :class="`is-${cn.status}`">{{ cn.status }}</span></td>
						</tr>
					</tbody>
				</table>
				<p v-else>Keine Gutschriften.</p>

				<button v-if="invoice.status !== 'cancelled'" @click="doFullCancellation">Vollstorno erstellen</button>

				<form class="erp-invoice-detail__credit-note-form" @submit.prevent="submitPartialCreditNote">
					<input v-model="partialCreditNote.reason" placeholder="Grund der Teilkorrektur" required>
					<input v-model="partialCreditNote.description" placeholder="Beschreibung" required>
					<input v-model.number="partialCreditNote.quantity" type="number" step="0.01" placeholder="Menge" required>
					<input v-model.number="partialCreditNote.unitPriceNet" type="number" step="0.01" placeholder="Betrag netto" required>
					<button type="submit">Teilkorrektur ausstellen</button>
				</form>
			</section>

			<section v-if="invoice.relatedInvoices && invoice.relatedInvoices.length" class="erp-invoice-detail__related">
				<h3>Teilrechnungen &amp; Teilzahlungen dieses Auftrags</h3>
				<p class="erp-invoice-detail__related-note">
					Rein informative Auflistung — keine automatische Verrechnung mit dieser Rechnung (ADR-0016).
				</p>
				<table>
					<thead><tr><th>Nr.</th><th>Titel</th><th>Typ</th><th>Status</th><th>Betrag brutto</th><th>Bezahlt</th></tr></thead>
					<tbody>
						<tr v-for="ri in invoice.relatedInvoices" :key="ri.id" class="erp-invoice-detail__related-row" @click="openRelated(ri.id)">
							<td>{{ ri.invoiceNumber || '(Entwurf)' }}</td>
							<td>{{ ri.title }}</td>
							<td>{{ typeLabel(ri.type) }}</td>
							<td><span class="erp-status-badge" :class="`is-${ri.status}`">{{ statusLabel(ri.status) }}</span></td>
							<td>{{ formatMoney(ri.grossTotal) }}</td>
							<td>{{ formatMoney(ri.paidAmount) }}</td>
						</tr>
					</tbody>
				</table>
			</section>
		</template>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import {
	fetchInvoice, addInvoicePosition, removeInvoicePosition, issueInvoice, recordInvoicePayment,
	fetchCreditNotes, createFullCancellation, createPartialCreditNote, addCreditNotePosition, issueCreditNote,
} from '../services/invoicesApi.js'
import { fetchVatRates } from '../services/settingsApi.js'

const STATUS_LABELS = { draft: 'Entwurf', issued: 'Ausgestellt', partially_paid: 'Teilweise bezahlt', paid: 'Bezahlt', cancelled: 'Storniert' }
const TYPE_LABELS = { article: 'Artikel', product: 'Produkt', labor: 'Arbeitsstunden', custom: 'Freitext', invoice: 'Rechnung', partial: 'Abschlagsrechnung', final: 'Schlussrechnung' }

export default {
	name: 'RechnungDetailView',
	props: {
		id: { type: [String, Number], required: true },
	},
	data() {
		return {
			invoice: null,
			creditNotes: [],
			vatRates: [],
			loadError: null,
			newPosition: { positionType: 'custom', description: '', quantity: 1, unit: 'Stk', unitPriceNet: 0, vatRatePercent: 19 },
			paymentAmount: null,
			partialCreditNote: { reason: '', description: '', quantity: 1, unitPriceNet: 0 },
		}
	},
	async mounted() {
		await this.load()
		this.vatRates = await fetchVatRates()
		if (this.vatRates.length) {
			this.newPosition.vatRatePercent = this.vatRates.find((v) => v.isDefault)?.percentage ?? this.vatRates[0].percentage
		}
	},
	watch: {
		// Klick auf eine Teilrechnung/Schlussrechnung in der
		// relatedInvoices-Liste navigiert innerhalb derselben Route
		// (rechnung-detail) auf eine andere id — Vue Router mountet die
		// Komponente dabei nicht neu, deshalb hier explizit neu laden.
		async id() {
			await this.load()
		},
	},
	methods: {
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		typeLabel(type) {
			return TYPE_LABELS[type] ?? type
		},
		formatMoney(value) {
			return `${Number(value).toFixed(2)} €`
		},
		openInFilesUrl(fileId) {
			return generateUrl(`/f/${fileId}`)
		},
		errorMessage(e) {
			return e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		},
		async load() {
			try {
				this.invoice = await fetchInvoice(this.id)
				this.creditNotes = await fetchCreditNotes(this.id)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitPosition() {
			try {
				await addInvoicePosition(this.id, this.newPosition)
				this.newPosition = { ...this.newPosition, description: '', quantity: 1, unitPriceNet: 0 }
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async removePos(positionId) {
			await removeInvoicePosition(this.id, positionId)
			await this.load()
		},
		async doIssue() {
			try {
				await issueInvoice(this.id)
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitPayment() {
			try {
				await recordInvoicePayment(this.id, this.paymentAmount)
				this.paymentAmount = null
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async doFullCancellation() {
			const reason = window.prompt('Grund für den Vollstorno:')
			if (!reason) {
				return
			}
			try {
				const creditNote = await createFullCancellation(this.id, reason)
				await issueCreditNote(creditNote.id)
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		openRelated(id) {
			this.$router.push({ name: 'rechnung-detail', params: { id } })
		},
		async submitPartialCreditNote() {
			try {
				const creditNote = await createPartialCreditNote(this.id, this.partialCreditNote.reason)
				await addCreditNotePosition(creditNote.id, {
					description: this.partialCreditNote.description,
					quantity: this.partialCreditNote.quantity,
					unitPriceNet: this.partialCreditNote.unitPriceNet,
					vatRatePercent: 19,
				})
				await issueCreditNote(creditNote.id)
				this.partialCreditNote = { reason: '', description: '', quantity: 1, unitPriceNet: 0 }
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
	},
}
</script>

<style scoped>
.erp-invoice-detail { padding: 20px 20px 80px; max-width: 960px; }
.erp-invoice-detail__error { color: var(--color-error-text, #c00); }
header { display: flex; align-items: center; gap: 12px; }
.erp-invoice-detail__meta { margin: 16px 0; padding: 12px; background: var(--color-background-dark); }
.erp-invoice-detail__meta p { margin: 4px 0; }
.erp-invoice-detail__positions table, .erp-invoice-detail__credit-notes table { width: 100%; border-collapse: collapse; }
.erp-invoice-detail__positions th, .erp-invoice-detail__positions td,
.erp-invoice-detail__credit-notes th, .erp-invoice-detail__credit-notes td { text-align: left; padding: 4px 6px; border-bottom: 1px solid var(--color-border); font-size: 13px; }
.erp-invoice-detail__position-form, .erp-invoice-detail__payment-form, .erp-invoice-detail__credit-note-form { display: flex; gap: 6px; margin: 10px 0; flex-wrap: wrap; }
.erp-invoice-detail__issue { margin-top: 8px; }
.erp-invoice-detail__summary { margin-top: 20px; padding: 12px; border: 1px solid var(--color-border); border-radius: 8px; max-width: 400px; }
.erp-invoice-detail__gross { font-size: 16px; }
.erp-invoice-detail__payment, .erp-invoice-detail__credit-notes, .erp-invoice-detail__related { margin-top: 20px; }
.erp-invoice-detail__related table { width: 100%; border-collapse: collapse; }
.erp-invoice-detail__related th, .erp-invoice-detail__related td { text-align: left; padding: 4px 6px; border-bottom: 1px solid var(--color-border); font-size: 13px; }
.erp-invoice-detail__related-row { cursor: pointer; }
.erp-invoice-detail__related-row:hover { background: var(--color-background-hover); }
.erp-invoice-detail__related-note { color: var(--color-text-maxcontrast); font-size: 12px; }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
.erp-status-badge.is-overdue { background: var(--color-error, #c00); color: #fff; }
</style>
