<template>
	<div class="erp-warehouse">
		<h2>Lager</h2>
		<p v-if="loadError" class="erp-warehouse__error">{{ loadError }}</p>

		<nav class="erp-warehouse__tabs">
			<button v-for="t in tabs" :key="t" :class="{ 'is-active': tab === t }" @click="tab = t">{{ t }}</button>
		</nav>

		<section v-if="tab === 'Lagerorte'" class="erp-warehouse__section">
			<form class="erp-warehouse__form" @submit.prevent="submitCreateWarehouse">
				<input v-model="newWarehouse.name" placeholder="Name" required>
				<select v-model="newWarehouse.type">
					<option value="central">Zentrallager</option>
					<option value="vehicle">Fahrzeuglager</option>
					<option value="site">Baustellenlager</option>
				</select>
				<input v-if="newWarehouse.type === 'site'" v-model.number="newWarehouse.projectId" type="number" placeholder="Projekt-ID" required>
				<input v-model="newWarehouse.notes" placeholder="Notiz">
				<button type="submit">Anlegen</button>
			</form>

			<table class="erp-warehouse__table">
				<thead><tr><th>Name</th><th>Typ</th><th>Projekt</th><th>Status</th></tr></thead>
				<tbody>
					<tr v-for="w in warehouses" :key="w.id">
						<td>{{ w.name }}</td>
						<td>{{ typeLabel(w.type) }}</td>
						<td>{{ w.projectId ?? '—' }}</td>
						<td>{{ w.active ? 'aktiv' : 'inaktiv' }}</td>
					</tr>
				</tbody>
			</table>
		</section>

		<section v-else-if="tab === 'Bestand'" class="erp-warehouse__section">
			<label>Lagerort
				<select v-model.number="selectedWarehouseId" @change="onSelectWarehouse">
					<option :value="null">— wählen —</option>
					<option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
				</select>
			</label>

			<template v-if="selectedWarehouseId">
				<table class="erp-warehouse__table">
					<thead><tr><th>Artikel</th><th>Ist</th><th>Reserviert</th><th>Soll</th><th>Mindestbestand</th></tr></thead>
					<tbody>
						<tr v-for="s in stockLevels" :key="s.id" :class="{ 'is-low': s.quantityOnHand < s.minQuantity || s.sollQuantity < s.minQuantity }">
							<td>{{ articleName(s.articleId) }}</td>
							<td>{{ s.quantityOnHand }}</td>
							<td>{{ s.quantityReserved }}</td>
							<td>{{ s.sollQuantity }}</td>
							<td>{{ s.minQuantity }}</td>
						</tr>
					</tbody>
				</table>

				<h3>Mindestbestand setzen</h3>
				<form class="erp-warehouse__form" @submit.prevent="submitMinQuantity">
					<select v-model.number="minQuantityForm.articleId" required>
						<option :value="null">Artikel wählen</option>
						<option v-for="a in articles" :key="a.id" :value="a.id">{{ a.name }}</option>
					</select>
					<input v-model.number="minQuantityForm.minQuantity" type="number" step="0.01" placeholder="Mindestbestand" required>
					<button type="submit">Setzen</button>
				</form>

				<h3>Bewegung buchen</h3>
				<form class="erp-warehouse__form" @submit.prevent="submitMovement">
					<select v-model.number="newMovement.articleId" required>
						<option :value="null">Artikel wählen</option>
						<option v-for="a in articles" :key="a.id" :value="a.id">{{ a.name }}</option>
					</select>
					<select v-model="newMovement.movementType">
						<option value="receipt">Wareneingang</option>
						<option value="consumption">Verbrauch</option>
						<option value="adjustment">Korrektur</option>
					</select>
					<input v-model.number="newMovement.quantity" type="number" step="0.01" min="0.01" placeholder="Menge" required>
					<input v-model="newMovement.notes" placeholder="Notiz">
					<button type="submit">Buchen</button>
				</form>

				<h3>Umlagern</h3>
				<form class="erp-warehouse__form" @submit.prevent="submitTransfer">
					<select v-model.number="transferForm.articleId" required>
						<option :value="null">Artikel wählen</option>
						<option v-for="a in articles" :key="a.id" :value="a.id">{{ a.name }}</option>
					</select>
					<select v-model.number="transferForm.toWarehouseId" required>
						<option :value="null">Ziel-Lagerort</option>
						<option v-for="w in warehouses.filter((w) => w.id !== selectedWarehouseId)" :key="w.id" :value="w.id">{{ w.name }}</option>
					</select>
					<input v-model.number="transferForm.quantity" type="number" step="0.01" min="0.01" placeholder="Menge" required>
					<button type="submit">Umlagern</button>
				</form>
			</template>
		</section>

		<section v-else-if="tab === 'Inventur'" class="erp-warehouse__section">
			<label>Lagerort
				<select v-model.number="selectedWarehouseId" @change="onSelectWarehouse">
					<option :value="null">— wählen —</option>
					<option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
				</select>
			</label>

			<template v-if="selectedWarehouseId">
				<template v-if="!selectedInventory">
					<form class="erp-warehouse__form" @submit.prevent="submitStartInventory">
						<input v-model="newInventoryNotes" placeholder="Notiz (optional)">
						<button type="submit">Inventur starten</button>
					</form>

					<table class="erp-warehouse__table">
						<thead><tr><th>Gestartet</th><th>Status</th><th></th></tr></thead>
						<tbody>
							<tr v-for="i in inventories" :key="i.id">
								<td>{{ formatDate(i.startedAt) }}</td>
								<td><span class="erp-status-badge" :class="`is-${i.status}`">{{ i.status }}</span></td>
								<td><button @click="openInventory(i.id)">Öffnen</button></td>
							</tr>
						</tbody>
					</table>
				</template>

				<template v-else>
					<button @click="selectedInventory = null">← Zurück zur Liste</button>
					<h3>Inventur vom {{ formatDate(selectedInventory.startedAt) }} <span class="erp-status-badge" :class="`is-${selectedInventory.status}`">{{ selectedInventory.status }}</span></h3>

					<form v-if="selectedInventory.status === 'open'" class="erp-warehouse__form" @submit.prevent="submitCount">
						<select v-model.number="newCount.articleId" required>
							<option :value="null">Artikel wählen</option>
							<option v-for="a in articles" :key="a.id" :value="a.id">{{ a.name }}</option>
						</select>
						<input v-model.number="newCount.countedQuantity" type="number" step="0.01" placeholder="Gezählte Menge" required>
						<button type="submit">Zählung erfassen</button>
					</form>

					<table class="erp-warehouse__table">
						<thead><tr><th>Artikel</th><th>Erwartet</th><th>Gezählt</th><th>Differenz</th></tr></thead>
						<tbody>
							<tr v-for="c in selectedInventory.counts" :key="c.id" :class="{ 'is-diff': c.difference !== 0 }">
								<td>{{ articleName(c.articleId) }}</td>
								<td>{{ c.expectedQuantity }}</td>
								<td>{{ c.countedQuantity }}</td>
								<td>{{ c.difference }}</td>
							</tr>
						</tbody>
					</table>

					<button v-if="selectedInventory.status === 'open'" @click="submitCloseInventory">Inventur abschließen</button>
				</template>
			</template>
		</section>

		<section v-else-if="tab === 'Bestellvorschläge'" class="erp-warehouse__section">
			<label>Lagerort (optional, sonst alle)
				<select v-model.number="selectedWarehouseId" @change="loadSuggestions">
					<option :value="null">Alle Lagerorte</option>
					<option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
				</select>
			</label>

			<table v-if="suggestions.length" class="erp-warehouse__table">
				<thead><tr><th>Artikel</th><th>Lagerort</th><th>Ist</th><th>Mindestbestand</th><th>Vorschlag</th><th>Günstigster Lieferant</th></tr></thead>
				<tbody>
					<tr v-for="s in suggestions" :key="`${s.articleId}-${s.warehouseId}`">
						<td>{{ s.articleName }}</td>
						<td>{{ s.warehouseName }}</td>
						<td>{{ s.quantityOnHand }}</td>
						<td>{{ s.minQuantity }}</td>
						<td>{{ s.suggestedQuantity }}</td>
						<td>
							<template v-if="s.supplierOptions.length">
								{{ s.supplierOptions[0].supplierContactUid }} ({{ s.supplierOptions[0].purchasePrice }} €)
							</template>
							<template v-else>—</template>
						</td>
					</tr>
				</tbody>
			</table>
			<p v-else>Kein Nachbestellbedarf.</p>
		</section>
	</div>
</template>

<script>
import {
	fetchWarehouses, createWarehouse,
	fetchStock, setMinQuantity, recordMovement, transferStock,
	fetchInventories, fetchInventory, startInventory, recordInventoryCount, closeInventory,
	fetchPurchaseSuggestions,
} from '../services/warehouseApi.js'
import { fetchArticles } from '../services/articlesApi.js'

const TYPE_LABELS = { central: 'Zentrallager', vehicle: 'Fahrzeuglager', site: 'Baustellenlager' }

export default {
	name: 'LagerView',
	data() {
		return {
			tab: 'Lagerorte',
			tabs: ['Lagerorte', 'Bestand', 'Inventur', 'Bestellvorschläge'],
			loadError: null,
			warehouses: [],
			articles: [],
			newWarehouse: { name: '', type: 'central', projectId: null, notes: '' },
			selectedWarehouseId: null,
			stockLevels: [],
			minQuantityForm: { articleId: null, minQuantity: 0 },
			newMovement: { articleId: null, movementType: 'receipt', quantity: 1, notes: '' },
			transferForm: { articleId: null, toWarehouseId: null, quantity: 1 },
			inventories: [],
			newInventoryNotes: '',
			selectedInventory: null,
			newCount: { articleId: null, countedQuantity: 0 },
			suggestions: [],
		}
	},
	async mounted() {
		await this.loadAll()
	},
	methods: {
		typeLabel(type) {
			return TYPE_LABELS[type] ?? type
		},
		articleName(id) {
			return this.articles.find((a) => a.id === id)?.name ?? `#${id}`
		},
		formatDate(timestamp) {
			return new Date(timestamp * 1000).toLocaleString('de-DE')
		},
		errorMessage(e) {
			return e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		},
		async loadAll() {
			try {
				this.warehouses = await fetchWarehouses()
				this.articles = await fetchArticles()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitCreateWarehouse() {
			try {
				await createWarehouse({ ...this.newWarehouse, notes: this.newWarehouse.notes || null })
				this.newWarehouse = { name: '', type: 'central', projectId: null, notes: '' }
				this.warehouses = await fetchWarehouses()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async onSelectWarehouse() {
			this.selectedInventory = null
			if (!this.selectedWarehouseId) {
				return
			}
			await Promise.all([this.loadStock(), this.loadInventories()])
		},
		async loadStock() {
			try {
				this.stockLevels = await fetchStock(this.selectedWarehouseId)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitMinQuantity() {
			try {
				await setMinQuantity(this.minQuantityForm.articleId, this.selectedWarehouseId, this.minQuantityForm.minQuantity)
				await this.loadStock()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitMovement() {
			try {
				const delta = this.newMovement.movementType === 'consumption' ? -Math.abs(this.newMovement.quantity) : this.newMovement.quantity
				await recordMovement({
					articleId: this.newMovement.articleId,
					warehouseId: this.selectedWarehouseId,
					quantityDelta: delta,
					movementType: this.newMovement.movementType,
					notes: this.newMovement.notes || null,
				})
				this.newMovement = { articleId: null, movementType: 'receipt', quantity: 1, notes: '' }
				await this.loadStock()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitTransfer() {
			try {
				await transferStock({
					articleId: this.transferForm.articleId,
					fromWarehouseId: this.selectedWarehouseId,
					toWarehouseId: this.transferForm.toWarehouseId,
					quantity: this.transferForm.quantity,
				})
				this.transferForm = { articleId: null, toWarehouseId: null, quantity: 1 }
				await this.loadStock()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async loadInventories() {
			try {
				this.inventories = await fetchInventories(this.selectedWarehouseId)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitStartInventory() {
			try {
				await startInventory(this.selectedWarehouseId, this.newInventoryNotes || null)
				this.newInventoryNotes = ''
				await this.loadInventories()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async openInventory(id) {
			try {
				this.selectedInventory = await fetchInventory(id)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitCount() {
			try {
				await recordInventoryCount(this.selectedInventory.id, this.newCount.articleId, this.newCount.countedQuantity)
				this.newCount = { articleId: null, countedQuantity: 0 }
				this.selectedInventory = await fetchInventory(this.selectedInventory.id)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitCloseInventory() {
			try {
				await closeInventory(this.selectedInventory.id)
				this.selectedInventory = await fetchInventory(this.selectedInventory.id)
				await this.loadStock()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async loadSuggestions() {
			try {
				this.suggestions = await fetchPurchaseSuggestions(this.selectedWarehouseId)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
	},
	watch: {
		async tab(newTab) {
			if (newTab === 'Bestellvorschläge') {
				await this.loadSuggestions()
			}
		},
	},
}
</script>

<style scoped>
.erp-warehouse { padding: 20px; max-width: 960px; }
.erp-warehouse__error { color: var(--color-error-text, #c00); }
.erp-warehouse__tabs { display: flex; gap: 4px; margin: 16px 0; border-bottom: 1px solid var(--color-border); }
.erp-warehouse__tabs button { background: none; border: none; padding: 8px 12px; cursor: pointer; }
.erp-warehouse__tabs button.is-active { border-bottom: 2px solid var(--color-primary-element); font-weight: bold; }
.erp-warehouse__form { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; align-items: center; }
.erp-warehouse__table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.erp-warehouse__table th, .erp-warehouse__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.erp-warehouse__table tr.is-low td { color: var(--color-error-text, #c00); }
.erp-warehouse__table tr.is-diff td { font-weight: bold; }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
</style>
