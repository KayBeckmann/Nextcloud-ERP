<template>
	<div class="erp-products">
		<div class="erp-products__header">
			<h2>Produkte</h2>
			<button @click="showCreate = !showCreate">+ Produkt</button>
		</div>

		<form v-if="showCreate" class="erp-products__create" @submit.prevent="submitCreate">
			<input v-model="newProduct.name" placeholder="Produktname" required>
			<select v-model.number="newProduct.vatRateId">
				<option :value="null">MwSt. —</option>
				<option v-for="v in vatRates" :key="v.id" :value="v.id">{{ v.name }}</option>
			</select>
			<button type="submit">Anlegen</button>
		</form>

		<p v-if="loadError" class="erp-products__error">{{ loadError }}</p>

		<ul class="erp-products__list">
			<li v-for="p in products" :key="p.id">
				<div class="erp-products__row" @click="toggle(p.id)">
					<strong>{{ p.name }}</strong>
					<span>{{ expanded === p.id ? '▲' : '▼' }}</span>
				</div>
				<div v-if="expanded === p.id && detail" class="erp-products__detail">
					<h4>Materialkomponenten</h4>
					<ul>
						<li v-for="c in detail.components" :key="c.id">
							Artikel #{{ c.articleId }} — {{ c.quantity }} {{ c.unit }}
							<button @click="removeComp(p.id, c.id)">✕</button>
						</li>
					</ul>
					<form class="erp-products__inline-form" @submit.prevent="submitComponent(p.id)">
						<input v-model.number="newComponent.articleId" type="number" placeholder="Artikel-ID" required>
						<input v-model.number="newComponent.quantity" type="number" step="0.01" placeholder="Menge" required>
						<input v-model="newComponent.unit" placeholder="Einheit" style="max-width:80px">
						<button type="submit">+ Komponente</button>
					</form>

					<h4>Arbeitsleistungen</h4>
					<ul>
						<li v-for="l in detail.labor" :key="l.id">
							Arbeitsart #{{ l.workTypeId }} — {{ l.hours }} Std.
							<button @click="removeLaborEntry(p.id, l.id)">✕</button>
						</li>
					</ul>
					<form class="erp-products__inline-form" @submit.prevent="submitLabor(p.id)">
						<input v-model.number="newLabor.workTypeId" type="number" placeholder="Arbeitsart-ID" required>
						<input v-model.number="newLabor.hours" type="number" step="0.5" placeholder="Stunden" required>
						<button type="submit">+ Arbeitsleistung</button>
					</form>
				</div>
			</li>
		</ul>
	</div>
</template>

<script>
import { addComponent, addLabor, createProduct, fetchProduct, fetchProducts, removeComponent, removeLabor } from '../services/productsApi.js'
import { fetchVatRates } from '../services/settingsApi.js'

export default {
	name: 'ProdukteView',
	data() {
		return {
			products: [],
			vatRates: [],
			loadError: null,
			showCreate: false,
			expanded: null,
			detail: null,
			newProduct: { name: '', vatRateId: null },
			newComponent: { articleId: null, quantity: 1, unit: 'Stk' },
			newLabor: { workTypeId: null, hours: 1 },
		}
	},
	async mounted() {
		await this.load()
		this.vatRates = await fetchVatRates()
	},
	methods: {
		async load() {
			try {
				this.products = await fetchProducts()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async toggle(id) {
			if (this.expanded === id) {
				this.expanded = null
				this.detail = null
				return
			}
			this.expanded = id
			this.detail = await fetchProduct(id)
		},
		async submitCreate() {
			await createProduct(this.newProduct)
			this.newProduct = { name: '', vatRateId: null }
			this.showCreate = false
			await this.load()
		},
		async submitComponent(productId) {
			await addComponent(productId, this.newComponent)
			this.newComponent = { articleId: null, quantity: 1, unit: 'Stk' }
			this.detail = await fetchProduct(productId)
		},
		async removeComp(productId, id) {
			await removeComponent(productId, id)
			this.detail = await fetchProduct(productId)
		},
		async submitLabor(productId) {
			await addLabor(productId, this.newLabor)
			this.newLabor = { workTypeId: null, hours: 1 }
			this.detail = await fetchProduct(productId)
		},
		async removeLaborEntry(productId, id) {
			await removeLabor(productId, id)
			this.detail = await fetchProduct(productId)
		},
	},
}
</script>

<style scoped>
.erp-products { padding: 20px; max-width: 720px; }
.erp-products__header { display: flex; align-items: center; justify-content: space-between; }
.erp-products__create { display: flex; gap: 8px; margin: 12px 0; }
.erp-products__error { color: var(--color-error-text, #c00); }
.erp-products__list { list-style: none; padding: 0; }
.erp-products__row { display: flex; justify-content: space-between; padding: 8px; cursor: pointer; border-bottom: 1px solid var(--color-border); }
.erp-products__row:hover { background: var(--color-background-hover); }
.erp-products__detail { padding: 10px; background: var(--color-background-dark); margin-bottom: 8px; }
.erp-products__detail ul { list-style: none; padding: 0; }
.erp-products__inline-form { display: flex; gap: 8px; margin-bottom: 12px; }
</style>
