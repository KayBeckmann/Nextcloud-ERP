<template>
	<div class="erp-quote-detail">
		<p v-if="loadError" class="erp-quote-detail__error">{{ loadError }}</p>
		<template v-else-if="quote">
			<header>
				<h2>{{ quote.quoteNumber }} — {{ quote.title }}</h2>
				<span class="erp-status-badge" :class="`is-${quote.status}`">{{ statusLabel(quote.status) }}</span>
				<button @click="createOrder">In Auftrag wandeln</button>
				<button @click="createInvoice">Rechnung aus diesem Angebot erstellen</button>
			</header>

			<section v-if="quote.documentFileId" class="erp-quote-detail__document">
				<p><a :href="openInFilesUrl(quote.documentFileId)" target="_blank" rel="noopener">Angebotsdokument öffnen</a></p>
				<iframe :src="documentPreviewUrl(quote.documentFileId)" class="erp-quote-detail__pdf-frame" title="Angebotsdokument"></iframe>
			</section>

			<section class="erp-quote-detail__meta">
				<label>Titel <input v-model="edit.title"></label>
				<label>Status
					<select v-model="edit.status">
						<option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
					</select>
				</label>
				<label>Kunde <ContactPicker v-model="edit.customerContactUid" placeholder="Kunde suchen …" /></label>
				<label>Notizen <textarea v-model="edit.notes" rows="2"></textarea></label>
				<label>Rabatt auf gesamtes Angebot <input v-model.number="edit.discountPercent" type="number" step="0.01" min="0" max="100" style="max-width:100px"> %</label>
				<button @click="save">Speichern</button>
				<span v-if="quote.sentAt" class="erp-quote-detail__hint">Versendet am {{ formatDate(quote.sentAt) }} — Preise/Sätze sind ab Hinzufügen der jeweiligen Position festgeschrieben.</span>
			</section>

			<section class="erp-quote-detail__groups">
				<div v-for="g in groupedPositions" :key="g.key" class="erp-quote-group">
					<h3>{{ g.title }} <span class="erp-quote-group__total">{{ formatMoney(g.netTotal) }}</span></h3>
					<table>
						<thead>
							<tr><th>Typ</th><th>Beschreibung</th><th>Menge</th><th>Einheit</th><th>EP netto</th><th>Rabatt</th><th>MwSt.</th><th>Gesamt netto</th><th></th></tr>
						</thead>
						<tbody>
							<template v-for="p in g.positions" :key="p.id">
								<tr v-if="editingPositionId !== p.id">
									<td>{{ typeLabel(p.positionType) }}</td>
									<td>{{ p.description }}</td>
									<td>{{ p.quantity }}</td>
									<td>{{ p.unit }}</td>
									<td>{{ formatMoney(p.unitPriceNet) }}</td>
									<td>{{ p.discountPercent > 0 ? p.discountPercent + ' %' : '—' }}</td>
									<td>{{ p.vatRatePercent }}%</td>
									<td>{{ formatMoney(p.netTotal) }}</td>
									<td>
										<button @click="startEditPos(p)">✎</button>
										<button @click="removePos(p.id)">✕</button>
									</td>
								</tr>
								<tr v-else class="erp-quote-detail__edit-row">
									<td>{{ typeLabel(p.positionType) }}</td>
									<td><input v-model="editPosition.description"></td>
									<td><input v-model.number="editPosition.quantity" type="number" step="0.01" style="max-width:70px"></td>
									<td><input v-model="editPosition.unit" style="max-width:60px"></td>
									<td><input v-model.number="editPosition.unitPriceNet" type="number" step="0.01" style="max-width:80px"></td>
									<td><input v-model.number="editPosition.discountPercent" type="number" step="0.01" min="0" max="100" style="max-width:60px"></td>
									<td>
										<select v-model.number="editPosition.vatRatePercent">
											<option v-for="v in vatRates" :key="v.id" :value="v.percentage">{{ v.name }}</option>
										</select>
									</td>
									<td>—</td>
									<td>
										<button @click="saveEditPos(p.id)">✓</button>
										<button @click="cancelEditPos">✕</button>
									</td>
								</tr>
							</template>
						</tbody>
					</table>
				</div>

				<h3>+ Position hinzufügen</h3>
				<form class="erp-quote-detail__position-form" @submit.prevent="submitPosition">
					<select v-model="newPosition.groupId">
						<option :value="null">Ohne Gruppe</option>
						<option v-for="grp in groups" :key="grp.id" :value="grp.id">{{ grp.title }}</option>
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

				<form class="erp-quote-detail__group-form" @submit.prevent="submitGroup">
					<input v-model="newGroupTitle" placeholder="Neue Positionsgruppe" required>
					<button type="submit">+ Gruppe</button>
				</form>

				<button v-if="quote.status === 'draft'" class="erp-quote-detail__issue" @click="generateDocument">PDF erstellen</button>
			</section>

			<section v-if="quote.calculation" class="erp-quote-detail__summary">
				<h3>Abschlussblock</h3>
				<p v-if="quote.calculation.documentDiscountAmount > 0">
					Zwischensumme netto: {{ formatMoney(quote.calculation.netSubtotalBeforeDiscount) }}<br>
					Rabatt: -{{ formatMoney(quote.calculation.documentDiscountAmount) }}
				</p>
				<p>Netto-Zwischensumme: <strong>{{ formatMoney(quote.calculation.netSubtotal) }}</strong></p>
				<p v-for="v in quote.calculation.vatBreakdown" :key="v.ratePercent">
					+ MwSt. {{ v.ratePercent }}% auf {{ formatMoney(v.netBase) }}: {{ formatMoney(v.vatAmount) }}
				</p>
				<p class="erp-quote-detail__gross">Brutto-Gesamt: <strong>{{ formatMoney(quote.calculation.grossTotal) }}</strong></p>
			</section>
		</template>
	</div>
</template>

<script>
import { addGroup, addPosition, fetchQuote, removePosition, updatePosition, updateQuote } from '../services/quotesApi.js'
import { fetchVatRates } from '../services/settingsApi.js'
import { createInvoiceFromQuote } from '../services/invoicesApi.js'
import { createOrderFromQuote } from '../services/ordersApi.js'
import ContactPicker from '../components/ContactPicker.vue'
import { generateUrl } from '@nextcloud/router'

const STATUS_LABELS = { draft: 'Entwurf', sent: 'Versendet', accepted: 'Angenommen', rejected: 'Abgelehnt', expired: 'Abgelaufen' }
const TYPE_LABELS = { article: 'Artikel', product: 'Produkt', labor: 'Arbeitsstunden', custom: 'Freitext' }

export default {
	name: 'AngebotDetailView',
	components: { ContactPicker },
	props: {
		id: { type: [String, Number], required: true },
	},
	data() {
		return {
			quote: null,
			groups: [],
			positions: [],
			vatRates: [],
			loadError: null,
			edit: { title: '', status: 'draft', customerContactUid: null, notes: '', discountPercent: 0 },
			statusOptions: Object.keys(STATUS_LABELS),
			newGroupTitle: '',
			newPosition: { groupId: null, positionType: 'custom', description: '', quantity: 1, unit: 'Stk', unitPriceNet: 0, vatRatePercent: 19 },
			editingPositionId: null,
			editPosition: {},
		}
	},
	computed: {
		groupedPositions() {
			const byGroup = {}
			for (const p of this.positions) {
				const key = p.groupId ?? 'none'
				if (!byGroup[key]) {
					const group = this.groups.find((g) => g.id === p.groupId)
					byGroup[key] = { key, title: group ? group.title : 'Ohne Gruppe', positions: [], netTotal: 0 }
				}
				byGroup[key].positions.push(p)
				byGroup[key].netTotal += p.netTotal
			}
			return Object.values(byGroup)
		},
	},
	async mounted() {
		await this.load()
		this.vatRates = await fetchVatRates()
		if (this.vatRates.length) {
			this.newPosition.vatRatePercent = this.vatRates.find((v) => v.isDefault)?.percentage ?? this.vatRates[0].percentage
		}
	},
	methods: {
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		openInFilesUrl(fileId) {
			return generateUrl(`/f/${fileId}`)
		},
		documentPreviewUrl(fileId) {
			return generateUrl(`/apps/erp/documents/${fileId}`)
		},
		typeLabel(type) {
			return TYPE_LABELS[type] ?? type
		},
		formatMoney(value) {
			return `${Number(value).toFixed(2)} €`
		},
		formatDate(timestamp) {
			return new Date(timestamp * 1000).toLocaleDateString('de-DE')
		},
		async load() {
			try {
				const full = await fetchQuote(this.id)
				this.quote = full
				this.groups = full.groups
				this.positions = full.positions
				this.edit = {
					title: full.title,
					status: full.status,
					customerContactUid: full.customerContactUid ?? null,
					notes: full.notes ?? '',
					discountPercent: full.discountPercent ?? 0,
				}
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async save() {
			await updateQuote(this.id, {
				title: this.edit.title,
				status: this.edit.status,
				projectId: this.quote.projectId,
				customerContactUid: this.edit.customerContactUid || null,
				notes: this.edit.notes || null,
				discountPercent: this.edit.discountPercent || 0,
			})
			await this.load()
		},
		async submitGroup() {
			await addGroup(this.id, this.newGroupTitle)
			this.newGroupTitle = ''
			await this.load()
		},
		async submitPosition() {
			await addPosition(this.id, this.newPosition)
			this.newPosition = { ...this.newPosition, description: '', quantity: 1, unitPriceNet: 0 }
			await this.load()
		},
		async removePos(id) {
			await removePosition(this.id, id)
			await this.load()
		},
		startEditPos(p) {
			this.editingPositionId = p.id
			this.editPosition = {
				description: p.description,
				quantity: p.quantity,
				unit: p.unit,
				unitPriceNet: p.unitPriceNet,
				vatRatePercent: p.vatRatePercent,
				discountPercent: p.discountPercent || 0,
			}
		},
		cancelEditPos() {
			this.editingPositionId = null
		},
		async saveEditPos(id) {
			try {
				await updatePosition(this.id, id, this.editPosition)
				this.editingPositionId = null
				await this.load()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async generateDocument() {
			try {
				await updateQuote(this.id, {
					title: this.edit.title,
					status: 'sent',
					projectId: this.quote.projectId,
					customerContactUid: this.edit.customerContactUid || null,
					notes: this.edit.notes || null,
					discountPercent: this.edit.discountPercent || 0,
				})
				await this.load()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async createOrder() {
			try {
				const order = await createOrderFromQuote({ quoteId: this.id })
				this.$router.push({ name: 'auftrag-detail', params: { id: order.id } })
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async createInvoice() {
			try {
				const invoice = await createInvoiceFromQuote({ quoteId: this.id, title: `Rechnung zu ${this.quote.quoteNumber}` })
				this.$router.push({ name: 'rechnung-detail', params: { id: invoice.id } })
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
	},
}
</script>

<style scoped>
.erp-quote-detail { padding: 20px 20px 80px; max-width: 960px; }
.erp-quote-detail__error { color: var(--color-error-text, #c00); }
header { display: flex; align-items: center; gap: 12px; }
.erp-quote-detail__meta { margin: 16px 0; padding: 12px; background: var(--color-background-dark); }
.erp-quote-detail__meta label { display: block; margin-bottom: 8px; }
.erp-quote-detail__meta input, .erp-quote-detail__meta select, .erp-quote-detail__meta textarea { width: 100%; max-width: 400px; }
.erp-quote-detail__hint { display: block; margin-top: 8px; color: var(--color-text-maxcontrast); font-size: 12px; }
.erp-quote-group { margin-bottom: 16px; }
.erp-quote-group__total { font-weight: normal; color: var(--color-text-maxcontrast); font-size: 13px; }
.erp-quote-group table { width: 100%; border-collapse: collapse; }
.erp-quote-group th, .erp-quote-group td { text-align: left; padding: 4px 6px; border-bottom: 1px solid var(--color-border); font-size: 13px; }
.erp-quote-detail__edit-row input, .erp-quote-detail__edit-row select { width: 100%; }
.erp-quote-detail__position-form, .erp-quote-detail__group-form { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
.erp-quote-detail__summary { margin-top: 20px; padding: 12px; border: 1px solid var(--color-border); border-radius: 8px; max-width: 400px; }
.erp-quote-detail__gross { font-size: 16px; }
.erp-quote-detail__issue { margin-top: 8px; }
.erp-quote-detail__document { margin: 8px 0 16px; }
.erp-quote-detail__pdf-frame { width: 100%; max-width: 800px; height: 600px; border: 1px solid var(--color-border); margin-top: 8px; }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
</style>
