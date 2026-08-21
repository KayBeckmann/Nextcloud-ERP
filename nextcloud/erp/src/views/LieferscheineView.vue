<template>
	<div class="erp-delivery-notes">
		<template v-if="!selected">
			<div class="erp-delivery-notes__header">
				<h3>Lieferscheine</h3>
				<button @click="showCreate = !showCreate">+ Lieferschein</button>
			</div>

			<form v-if="showCreate" class="erp-delivery-notes__create" @submit.prevent="submitCreate">
				<input v-model="newNotes" placeholder="Notiz (optional)">
				<button type="submit">Anlegen</button>
			</form>

			<p v-if="loadError" class="erp-delivery-notes__error">{{ loadError }}</p>

			<table v-if="deliveryNotes.length" class="erp-delivery-notes__table">
				<thead><tr><th>Nr.</th><th>Status</th><th>Notiz</th><th></th></tr></thead>
				<tbody>
					<tr v-for="dn in deliveryNotes" :key="dn.id" class="erp-delivery-notes__row" @click="open(dn.id)">
						<td>{{ dn.deliveryNoteNumber }}</td>
						<td><span class="erp-status-badge" :class="`is-${dn.status}`">{{ dn.status === 'draft' ? 'Entwurf' : 'Ausgestellt' }}</span></td>
						<td>{{ dn.notes }}</td>
						<td></td>
					</tr>
				</tbody>
			</table>
			<p v-else-if="!loadError">Noch keine Lieferscheine in diesem Projekt.</p>
		</template>

		<template v-else>
			<button @click="selected = null">← Zurück zur Liste</button>
			<h3>{{ selected.deliveryNoteNumber }} <span class="erp-status-badge" :class="`is-${selected.status}`">{{ selected.status === 'draft' ? 'Entwurf' : 'Ausgestellt' }}</span></h3>

			<div v-for="g in groupedPositions" :key="g.key" class="erp-delivery-note-group">
				<h4>{{ g.title }}</h4>
				<table class="erp-delivery-notes__table">
					<thead><tr><th>Typ</th><th>Beschreibung</th><th>Menge</th><th>Einheit</th><th></th></tr></thead>
					<tbody>
						<tr v-for="p in g.positions" :key="p.id">
							<td>{{ typeLabel(p.positionType) }}</td>
							<td>{{ p.description }}</td>
							<td>{{ p.quantity }}</td>
							<td>{{ p.unit }}</td>
							<td><button v-if="selected.status === 'draft'" @click="removePos(p.id)">✕</button></td>
						</tr>
					</tbody>
				</table>
			</div>

			<form v-if="selected.status === 'draft'" class="erp-delivery-notes__position-form" @submit.prevent="submitPosition">
				<select v-model="newPosition.groupId">
					<option :value="null">Ohne Gruppe</option>
					<option v-for="grp in selected.groups" :key="grp.id" :value="grp.id">{{ grp.title }}</option>
				</select>
				<select v-model="newPosition.positionType">
					<option value="custom">Freitext</option>
					<option value="article">Artikel</option>
					<option value="product">Produkt</option>
				</select>
				<input v-model="newPosition.description" placeholder="Beschreibung" required>
				<input v-model.number="newPosition.quantity" type="number" step="0.01" placeholder="Menge" required>
				<input v-model="newPosition.unit" placeholder="Einheit" style="max-width:70px">
				<button type="submit">Hinzufügen</button>
			</form>

			<form v-if="selected.status === 'draft'" class="erp-delivery-notes__group-form" @submit.prevent="submitGroup">
				<input v-model="newGroupTitle" placeholder="Neue Positionsgruppe" required>
				<button type="submit">+ Gruppe</button>
			</form>

			<button v-if="selected.status === 'draft'" @click="doIssue">Lieferschein ausstellen</button>

			<section class="erp-delivery-notes__convert">
				<h3>Umwandeln</h3>
				<button @click="toggleInvoiceMode">In Rechnung wandeln</button>
				<p v-if="convertError" class="erp-delivery-notes__error">{{ convertError }}</p>

				<form v-if="invoiceMode" class="erp-delivery-notes__convert-form" @submit.prevent="submitInvoice">
					<label v-for="p in selected.positions" :key="p.id" class="erp-delivery-notes__convert-row">
						<input type="checkbox" v-model="invoiceSelection[p.id].selected">
						{{ typeLabel(p.positionType) }} — {{ p.description }} ({{ p.quantity }} {{ p.unit }})
						<template v-if="!p.orderPositionId">
							<input type="number" step="0.01" placeholder="EP netto" v-model.number="invoiceSelection[p.id].unitPriceNet" style="max-width:90px">
							<select v-model.number="invoiceSelection[p.id].vatRatePercent">
								<option v-for="v in vatRates" :key="v.id" :value="v.percentage">{{ v.name }}</option>
							</select>
						</template>
						<span v-else class="erp-delivery-notes__convert-note">(Preis aus Auftrag)</span>
					</label>
					<input v-model="invoiceForm.title" placeholder="Rechnungstitel" required>
					<select v-model="invoiceForm.type">
						<option value="invoice">Rechnung</option>
						<option value="partial">Teilrechnung</option>
						<option value="final">Schlussrechnung</option>
					</select>
					<button type="submit">Rechnung erstellen</button>
				</form>
			</section>
		</template>
	</div>
</template>

<script>
import {
	fetchDeliveryNotes, createDeliveryNote, fetchDeliveryNote,
	addDeliveryNoteGroup, addDeliveryNotePosition, removeDeliveryNotePosition, issueDeliveryNote,
} from '../services/deliveryNotesApi.js'
import { createInvoiceFromDeliveryNote } from '../services/invoicesApi.js'
import { fetchVatRates } from '../services/settingsApi.js'

const TYPE_LABELS = { article: 'Artikel', product: 'Produkt', custom: 'Freitext' }

export default {
	name: 'LieferscheineView',
	props: {
		projectId: { type: [String, Number], required: true },
	},
	data() {
		return {
			deliveryNotes: [],
			selected: null,
			loadError: null,
			showCreate: false,
			newNotes: '',
			newPosition: { groupId: null, positionType: 'custom', description: '', quantity: 1, unit: 'Stk' },
			newGroupTitle: '',
			vatRates: [],
			invoiceMode: false,
			invoiceSelection: {},
			invoiceForm: { title: '', type: 'invoice' },
			convertError: null,
		}
	},
	computed: {
		groupedPositions() {
			const byGroup = {}
			for (const p of this.selected?.positions ?? []) {
				const key = p.groupId ?? 'none'
				if (!byGroup[key]) {
					const group = (this.selected.groups ?? []).find((g) => g.id === p.groupId)
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
	},
	methods: {
		typeLabel(type) {
			return TYPE_LABELS[type] ?? type
		},
		errorMessage(e) {
			return e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		},
		async load() {
			try {
				this.deliveryNotes = await fetchDeliveryNotes(this.projectId)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitCreate() {
			try {
				await createDeliveryNote({ projectId: this.projectId, notes: this.newNotes || null })
				this.newNotes = ''
				this.showCreate = false
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async open(id) {
			try {
				this.selected = await fetchDeliveryNote(id)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitPosition() {
			try {
				await addDeliveryNotePosition(this.selected.id, this.newPosition)
				this.newPosition = { ...this.newPosition, description: '', quantity: 1 }
				this.selected = await fetchDeliveryNote(this.selected.id)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitGroup() {
			try {
				await addDeliveryNoteGroup(this.selected.id, this.newGroupTitle)
				this.newGroupTitle = ''
				this.selected = await fetchDeliveryNote(this.selected.id)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async removePos(id) {
			await removeDeliveryNotePosition(this.selected.id, id)
			this.selected = await fetchDeliveryNote(this.selected.id)
		},
		async doIssue() {
			try {
				await issueDeliveryNote(this.selected.id)
				this.selected = await fetchDeliveryNote(this.selected.id)
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		toggleInvoiceMode() {
			this.invoiceMode = !this.invoiceMode
			this.convertError = null
			if (this.invoiceMode) {
				this.invoiceSelection = {}
				for (const p of this.selected.positions) {
					this.invoiceSelection[p.id] = { selected: true, unitPriceNet: null, vatRatePercent: this.vatRates.find((v) => v.isDefault)?.percentage ?? 19 }
				}
				this.invoiceForm = { title: `Rechnung zu ${this.selected.deliveryNoteNumber}`, type: 'invoice' }
			}
		},
		async submitInvoice() {
			this.convertError = null
			const positions = Object.entries(this.invoiceSelection)
				.filter(([, sel]) => sel.selected)
				.map(([deliveryNotePositionId, sel]) => ({
					deliveryNotePositionId: Number(deliveryNotePositionId),
					unitPriceNet: sel.unitPriceNet ?? undefined,
					vatRatePercent: sel.unitPriceNet != null ? sel.vatRatePercent : undefined,
				}))
			if (positions.length === 0) {
				this.convertError = 'Bitte mindestens eine Position auswählen.'
				return
			}
			try {
				const invoice = await createInvoiceFromDeliveryNote({
					deliveryNoteId: this.selected.id,
					title: this.invoiceForm.title,
					type: this.invoiceForm.type,
					positions,
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
.erp-delivery-notes { padding: 4px 0; }
.erp-delivery-notes__header { display: flex; align-items: center; justify-content: space-between; max-width: 900px; }
.erp-delivery-notes__create, .erp-delivery-notes__position-form, .erp-delivery-notes__group-form { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; align-items: center; }
.erp-delivery-note-group { margin-bottom: 12px; }
.erp-delivery-notes__error { color: var(--color-error-text, #c00); }
.erp-delivery-notes__table { border-collapse: collapse; width: 100%; max-width: 900px; margin-bottom: 10px; }
.erp-delivery-notes__table th, .erp-delivery-notes__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.erp-delivery-notes__row { cursor: pointer; }
.erp-delivery-notes__row:hover { background: var(--color-background-hover); }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
.erp-delivery-notes__convert { margin-top: 20px; }
.erp-delivery-notes__convert-form { margin-top: 10px; padding: 12px; border: 1px solid var(--color-border); border-radius: 8px; max-width: 600px; display: flex; flex-direction: column; gap: 8px; }
.erp-delivery-notes__convert-row { display: flex; align-items: center; gap: 8px; }
.erp-delivery-notes__convert-note { color: var(--color-text-maxcontrast); font-size: 12px; }
</style>
