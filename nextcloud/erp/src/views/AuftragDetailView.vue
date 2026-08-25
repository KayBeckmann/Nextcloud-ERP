<template>
	<div class="erp-order-detail">
		<p v-if="loadError" class="erp-order-detail__error">{{ loadError }}</p>
		<template v-else-if="order">
			<header>
				<h2>{{ order.title }}</h2>
				<span class="erp-status-badge" :class="`is-${order.status}`">{{ statusLabel(order.status) }}</span>
				<span v-if="order.quoteId" class="erp-order-detail__hint">aus Angebot #{{ order.quoteId }}</span>
			</header>

			<p v-if="order.documentFileId"><a :href="openInFilesUrl(order.documentFileId)" target="_blank" rel="noopener">Auftragsdokument öffnen</a></p>

			<section class="erp-order-detail__meta">
				<label>Titel <input v-model="edit.title"></label>
				<label>Status
					<select v-model="edit.status">
						<option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
					</select>
				</label>
				<label>Kunde <ContactPicker v-model="edit.customerContactUid" placeholder="Kunde suchen …" /></label>
				<label>Zugewiesener Mitarbeiter <UserPicker v-model="edit.assignedUserId" placeholder="Mitarbeiter suchen …" /></label>
				<label>Beschreibung <textarea v-model="edit.description" rows="2"></textarea></label>
				<button @click="save">Speichern</button>
			</section>

			<section class="erp-order-detail__positions">
				<div v-for="g in groupedPositions" :key="g.key" class="erp-order-group">
					<h3>{{ g.title }}</h3>
					<table>
						<thead>
							<tr><th>Typ</th><th>Beschreibung</th><th>Menge</th><th>Einheit</th><th>EP netto</th><th>MwSt.</th><th>Berechnet</th><th>Geliefert</th><th></th></tr>
						</thead>
						<tbody>
							<tr v-for="p in g.positions" :key="p.id">
								<td>{{ typeLabel(p.positionType) }}</td>
								<td>{{ p.description }}</td>
								<td>{{ p.quantity }}</td>
								<td>{{ p.unit }}</td>
								<td>{{ formatMoney(p.unitPriceNet) }}</td>
								<td>{{ p.vatRatePercent }}%</td>
								<td>{{ p.invoicedQuantity }} / {{ p.quantity }}</td>
								<td>{{ p.deliveredQuantity }} / {{ p.quantity }}</td>
								<td><button @click="removePos(p.id)">✕</button></td>
							</tr>
						</tbody>
					</table>
				</div>

				<h3>+ Position hinzufügen</h3>
				<form class="erp-order-detail__position-form" @submit.prevent="submitPosition">
					<select v-model="newPosition.groupId">
						<option :value="null">Ohne Gruppe</option>
						<option v-for="grp in order.groups" :key="grp.id" :value="grp.id">{{ grp.title }}</option>
					</select>
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

				<form class="erp-order-detail__group-form" @submit.prevent="submitGroup">
					<input v-model="newGroupTitle" placeholder="Neue Positionsgruppe" required>
					<button type="submit">+ Gruppe</button>
				</form>
			</section>

			<section v-if="order.calculation" class="erp-order-detail__summary">
				<h3>Abschlussblock</h3>
				<p>Netto-Zwischensumme: <strong>{{ formatMoney(order.calculation.netSubtotal) }}</strong></p>
				<p class="erp-order-detail__gross">Brutto-Gesamt: <strong>{{ formatMoney(order.calculation.grossTotal) }}</strong></p>
			</section>

			<section class="erp-order-detail__convert">
				<h3>Umwandeln</h3>
				<div class="erp-order-detail__convert-buttons">
					<button @click="toggleDeliveryNoteMode">In Lieferschein wandeln</button>
					<button @click="toggleInvoiceMode">In Rechnung wandeln</button>
					<button @click="toggleDepositMode">Materialabschlag anlegen</button>
				</div>
				<p v-if="convertError" class="erp-order-detail__error">{{ convertError }}</p>
				<p v-if="convertSuccess" class="erp-order-detail__success">{{ convertSuccess }}</p>

				<form v-if="deliveryNoteMode" class="erp-order-detail__convert-form" @submit.prevent="submitDeliveryNote">
					<p class="erp-order-detail__convert-note">Nur Artikel/Produkt können geliefert werden — keine Arbeitsstunden.</p>
					<label v-for="p in deliverablePositions" :key="p.id" class="erp-order-detail__convert-row">
						<input type="checkbox" v-model="dnSelection[p.id].selected">
						{{ typeLabel(p.positionType) }} — {{ p.description }} (offen: {{ remaining(p, 'deliveredQuantity') }} {{ p.unit }})
						<input type="number" step="0.01" min="0" :max="remaining(p, 'deliveredQuantity')" v-model.number="dnSelection[p.id].quantity" style="max-width:90px">
					</label>
					<input v-model="dnNotes" placeholder="Notiz (optional)">
					<button type="submit">Lieferschein erstellen</button>
				</form>

				<form v-if="invoiceMode" class="erp-order-detail__convert-form" @submit.prevent="submitInvoice">
					<label v-for="p in order.positions" :key="p.id" class="erp-order-detail__convert-row">
						<input type="checkbox" v-model="invoiceSelection[p.id].selected">
						{{ typeLabel(p.positionType) }} — {{ p.description }} (offen: {{ remaining(p, 'invoicedQuantity') }} {{ p.unit }})
						<input type="number" step="0.01" min="0" :max="remaining(p, 'invoicedQuantity')" v-model.number="invoiceSelection[p.id].quantity" style="max-width:90px">
					</label>
					<input v-model="invoiceForm.title" placeholder="Rechnungstitel" required>
					<select v-model="invoiceForm.type">
						<option value="invoice">Rechnung</option>
						<option value="partial">Teilrechnung</option>
						<option value="final">Schlussrechnung</option>
					</select>
					<button type="submit">Rechnung erstellen</button>
				</form>

				<form v-if="depositMode" class="erp-order-detail__convert-form" @submit.prevent="submitDeposit">
					<p class="erp-order-detail__convert-note">Pauschalbetrag ohne Bezug zu einzelnen Positionen (Abschlagsrechnung).</p>
					<input v-model="depositForm.title" placeholder="Rechnungstitel" required>
					<input v-model.number="depositForm.amountNet" type="number" step="0.01" placeholder="Betrag netto" required>
					<select v-model.number="depositForm.vatRatePercent">
						<option v-for="v in vatRates" :key="v.id" :value="v.percentage">{{ v.name }}</option>
					</select>
					<button type="submit">Materialabschlag erstellen</button>
				</form>
			</section>
		</template>
	</div>
</template>

<script>
import { fetchOrder, updateOrder, addOrderGroup, addOrderPosition, removeOrderPosition } from '../services/ordersApi.js'
import { createDeliveryNoteFromOrder } from '../services/deliveryNotesApi.js'
import { createInvoice, createInvoiceFromOrder, addInvoicePosition } from '../services/invoicesApi.js'
import { fetchVatRates } from '../services/settingsApi.js'
import ContactPicker from '../components/ContactPicker.vue'
import UserPicker from '../components/UserPicker.vue'
import { generateUrl } from '@nextcloud/router'

const STATUS_LABELS = { draft: 'Entwurf', confirmed: 'Bestätigt', done: 'Abgeschlossen' }
const TYPE_LABELS = { article: 'Artikel', product: 'Produkt', labor: 'Arbeitsstunden', custom: 'Freitext' }
const DELIVERABLE_TYPES = ['article', 'product']

export default {
	name: 'AuftragDetailView',
	components: { ContactPicker, UserPicker },
	props: {
		id: { type: [String, Number], required: true },
	},
	data() {
		return {
			order: null,
			vatRates: [],
			loadError: null,
			convertError: null,
			convertSuccess: null,
			edit: { title: '', status: 'draft', customerContactUid: null, assignedUserId: null, description: '' },
			statusOptions: Object.keys(STATUS_LABELS),
			newPosition: { groupId: null, positionType: 'custom', description: '', quantity: 1, unit: 'Stk', unitPriceNet: 0, vatRatePercent: 19 },
			newGroupTitle: '',
			deliveryNoteMode: false,
			dnSelection: {},
			dnNotes: '',
			invoiceMode: false,
			invoiceSelection: {},
			invoiceForm: { title: '', type: 'invoice' },
			depositMode: false,
			depositForm: { title: '', amountNet: 0, vatRatePercent: 19 },
		}
	},
	computed: {
		deliverablePositions() {
			return (this.order?.positions ?? []).filter((p) => DELIVERABLE_TYPES.includes(p.positionType))
		},
		groupedPositions() {
			const byGroup = {}
			for (const p of this.order?.positions ?? []) {
				const key = p.groupId ?? 'none'
				if (!byGroup[key]) {
					const group = (this.order.groups ?? []).find((g) => g.id === p.groupId)
					byGroup[key] = { key, title: group ? group.title : 'Ohne Gruppe', positions: [] }
				}
				byGroup[key].positions.push(p)
			}
			return Object.values(byGroup)
		},
	},
	async mounted() {
		await this.load()
		this.vatRates = await fetchVatRates()
		if (this.vatRates.length) {
			const def = this.vatRates.find((v) => v.isDefault)?.percentage ?? this.vatRates[0].percentage
			this.newPosition.vatRatePercent = def
			this.depositForm.vatRatePercent = def
		}
	},
	methods: {
		openInFilesUrl(fileId) {
			return generateUrl(`/f/${fileId}`)
		},
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		typeLabel(type) {
			return TYPE_LABELS[type] ?? type
		},
		formatMoney(value) {
			return `${Number(value).toFixed(2)} €`
		},
		remaining(position, kind) {
			return Math.max(0, position.quantity - (position[kind] ?? 0))
		},
		errorMessage(e) {
			return e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		},
		async load() {
			try {
				this.order = await fetchOrder(this.id)
				this.edit = {
					title: this.order.title,
					status: this.order.status,
					customerContactUid: this.order.customerContactUid ?? null,
					assignedUserId: this.order.assignedUserId ?? null,
					description: this.order.description ?? '',
				}
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async save() {
			try {
				await updateOrder(this.order.projectId, this.id, this.edit)
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitPosition() {
			try {
				await addOrderPosition(this.id, this.newPosition)
				this.newPosition = { ...this.newPosition, description: '', quantity: 1, unitPriceNet: 0 }
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitGroup() {
			try {
				await addOrderGroup(this.id, this.newGroupTitle)
				this.newGroupTitle = ''
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async removePos(positionId) {
			await removeOrderPosition(this.id, positionId)
			await this.load()
		},
		toggleDeliveryNoteMode() {
			this.deliveryNoteMode = !this.deliveryNoteMode
			this.invoiceMode = false
			this.depositMode = false
			this.convertError = null
			if (this.deliveryNoteMode) {
				this.dnSelection = {}
				for (const p of this.deliverablePositions) {
					this.dnSelection[p.id] = { selected: false, quantity: this.remaining(p, 'deliveredQuantity') }
				}
			}
		},
		toggleInvoiceMode() {
			this.invoiceMode = !this.invoiceMode
			this.deliveryNoteMode = false
			this.depositMode = false
			this.convertError = null
			if (this.invoiceMode) {
				this.invoiceSelection = {}
				for (const p of this.order.positions) {
					this.invoiceSelection[p.id] = { selected: false, quantity: this.remaining(p, 'invoicedQuantity') }
				}
				this.invoiceForm = { title: `Rechnung zu ${this.order.title}`, type: 'invoice' }
			}
		},
		toggleDepositMode() {
			this.depositMode = !this.depositMode
			this.deliveryNoteMode = false
			this.invoiceMode = false
			this.convertError = null
			if (this.depositMode) {
				this.depositForm = { title: `Materialabschlag zu ${this.order.title}`, amountNet: 0, vatRatePercent: this.depositForm.vatRatePercent }
			}
		},
		async submitDeliveryNote() {
			this.convertError = null
			const positions = Object.entries(this.dnSelection)
				.filter(([, sel]) => sel.selected && sel.quantity > 0)
				.map(([orderPositionId, sel]) => ({ orderPositionId: Number(orderPositionId), quantity: sel.quantity }))
			if (positions.length === 0) {
				this.convertError = 'Bitte mindestens eine Position mit Menge auswählen.'
				return
			}
			try {
				const dn = await createDeliveryNoteFromOrder({ orderId: this.id, positions, notes: this.dnNotes || null })
				this.convertSuccess = `Lieferschein ${dn.deliveryNoteNumber} erstellt.`
				this.deliveryNoteMode = false
				this.dnNotes = ''
				await this.load()
			} catch (e) {
				this.convertError = this.errorMessage(e)
			}
		},
		async submitInvoice() {
			this.convertError = null
			const positions = Object.entries(this.invoiceSelection)
				.filter(([, sel]) => sel.selected && sel.quantity > 0)
				.map(([orderPositionId, sel]) => ({ orderPositionId: Number(orderPositionId), quantity: sel.quantity }))
			if (positions.length === 0) {
				this.convertError = 'Bitte mindestens eine Position mit Menge auswählen.'
				return
			}
			try {
				const invoice = await createInvoiceFromOrder({
					orderId: this.id,
					title: this.invoiceForm.title,
					type: this.invoiceForm.type,
					positions,
				})
				this.$router.push({ name: 'rechnung-detail', params: { id: invoice.id } })
			} catch (e) {
				this.convertError = this.errorMessage(e)
			}
		},
		async submitDeposit() {
			this.convertError = null
			try {
				const invoice = await createInvoice({
					title: this.depositForm.title,
					projectId: this.order.projectId,
					type: 'partial',
					orderId: this.id,
					customerContactUid: this.order.customerContactUid,
				})
				await addInvoicePosition(invoice.id, {
					positionType: 'custom',
					description: this.depositForm.title,
					quantity: 1,
					unit: 'pausch.',
					unitPriceNet: this.depositForm.amountNet,
					vatRatePercent: this.depositForm.vatRatePercent,
				})
				this.$router.push({ name: 'rechnung-detail', params: { id: invoice.id } })
			} catch (e) {
				this.convertError = this.errorMessage(e)
			}
		},
	},
}
</script>

<style scoped>
.erp-order-detail { padding: 20px 20px 80px; max-width: 960px; }
.erp-order-detail__error { color: var(--color-error-text, #c00); }
.erp-order-detail__success { color: var(--color-success-text, #2a2); }
header { display: flex; align-items: center; gap: 12px; }
.erp-order-detail__hint { color: var(--color-text-maxcontrast); font-size: 13px; }
.erp-order-detail__meta { margin: 16px 0; padding: 12px; background: var(--color-background-dark); }
.erp-order-detail__meta label { display: block; margin-bottom: 8px; }
.erp-order-detail__meta input, .erp-order-detail__meta select, .erp-order-detail__meta textarea { width: 100%; max-width: 400px; }
.erp-order-detail__positions table { width: 100%; border-collapse: collapse; }
.erp-order-detail__positions th, .erp-order-detail__positions td { text-align: left; padding: 4px 6px; border-bottom: 1px solid var(--color-border); font-size: 13px; }
.erp-order-group { margin-bottom: 16px; }
.erp-order-detail__position-form, .erp-order-detail__group-form { display: flex; gap: 6px; margin: 10px 0; flex-wrap: wrap; }
.erp-order-detail__summary { margin-top: 20px; padding: 12px; border: 1px solid var(--color-border); border-radius: 8px; max-width: 400px; }
.erp-order-detail__gross { font-size: 16px; }
.erp-order-detail__convert { margin-top: 24px; }
.erp-order-detail__convert-buttons { display: flex; gap: 8px; }
.erp-order-detail__convert-form { margin-top: 12px; padding: 12px; border: 1px solid var(--color-border); border-radius: 8px; max-width: 600px; }
.erp-order-detail__convert-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.erp-order-detail__convert-note { color: var(--color-text-maxcontrast); font-size: 12px; }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
</style>
