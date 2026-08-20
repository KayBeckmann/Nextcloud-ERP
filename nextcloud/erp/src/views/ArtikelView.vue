<template>
	<div class="erp-articles">
		<div class="erp-articles__header">
			<h2>Artikel</h2>
			<button @click="showCreate = !showCreate">+ Artikel</button>
		</div>

		<form v-if="showCreate" class="erp-articles__create" @submit.prevent="submitCreate">
			<input v-model="newArticle.name" placeholder="Bezeichnung" required>
			<input v-model="newArticle.manufacturer" placeholder="Hersteller">
			<input v-model="newArticle.manufacturerArticleNo" placeholder="Hersteller-Art.Nr.">
			<input v-model="newArticle.unit" placeholder="Einheit" style="max-width:80px">
			<select v-model.number="newArticle.vatRateId">
				<option :value="null">MwSt. —</option>
				<option v-for="v in vatRates" :key="v.id" :value="v.id">{{ v.name }}</option>
			</select>
			<button type="submit">Anlegen</button>
		</form>

		<p v-if="loadError" class="erp-articles__error">{{ loadError }}</p>

		<table v-if="articles.length" class="erp-articles__table">
			<thead>
				<tr>
					<th>Bezeichnung</th>
					<th>Hersteller</th>
					<th>Hersteller-Art.Nr.</th>
					<th>Einheit</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<template v-for="a in articles" :key="a.id">
					<tr class="erp-articles__row" @click="toggle(a.id)">
						<td>{{ a.name }}</td>
						<td>{{ a.manufacturer }}</td>
						<td>{{ a.manufacturerArticleNo }}</td>
						<td>{{ a.unit }}</td>
						<td>{{ expanded === a.id ? '▲' : '▼' }}</td>
					</tr>
					<tr v-if="expanded === a.id">
						<td colspan="5">
							<div v-if="detail" class="erp-articles__detail">
								<table class="erp-articles__prices">
									<thead>
										<tr><th>Lieferant (Contact-UID)</th><th>Lief.-Art.Nr.</th><th>EK-Preis</th><th></th></tr>
									</thead>
									<tbody>
										<tr v-for="p in detail.supplierPrices" :key="p.id">
											<td>{{ p.supplierContactUid }}</td>
											<td>{{ p.supplierArticleNo }}</td>
											<td>{{ p.purchasePrice }} {{ p.currency }}</td>
											<td><button @click="removePrice(a.id, p.id)">✕</button></td>
										</tr>
									</tbody>
								</table>
								<form class="erp-articles__inline-form" @submit.prevent="submitPrice(a.id)">
									<input v-model="newPrice.supplierContactUid" placeholder="Lieferant (Contact-UID)" required>
									<input v-model.number="newPrice.purchasePrice" type="number" step="0.01" placeholder="EK-Preis" required>
									<button type="submit">+ Preis</button>
								</form>
							</div>
						</td>
					</tr>
				</template>
			</tbody>
		</table>
	</div>
</template>

<script>
import { addSupplierPrice, createArticle, fetchArticle, fetchArticles, removeSupplierPrice } from '../services/articlesApi.js'
import { fetchVatRates } from '../services/settingsApi.js'

export default {
	name: 'ArtikelView',
	data() {
		return {
			articles: [],
			vatRates: [],
			loadError: null,
			showCreate: false,
			expanded: null,
			detail: null,
			newArticle: { name: '', manufacturer: '', manufacturerArticleNo: '', unit: 'Stk', vatRateId: null },
			newPrice: { supplierContactUid: '', purchasePrice: null },
		}
	},
	async mounted() {
		await this.load()
		this.vatRates = await fetchVatRates()
	},
	methods: {
		async load() {
			try {
				this.articles = await fetchArticles()
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
			this.detail = await fetchArticle(id)
		},
		async submitCreate() {
			await createArticle(this.newArticle)
			this.newArticle = { name: '', manufacturer: '', manufacturerArticleNo: '', unit: 'Stk', vatRateId: null }
			this.showCreate = false
			await this.load()
		},
		async submitPrice(articleId) {
			await addSupplierPrice(articleId, this.newPrice)
			this.newPrice = { supplierContactUid: '', purchasePrice: null }
			this.detail = await fetchArticle(articleId)
		},
		async removePrice(articleId, priceId) {
			await removeSupplierPrice(articleId, priceId)
			this.detail = await fetchArticle(articleId)
		},
	},
}
</script>

<style scoped>
.erp-articles { padding: 20px; }
.erp-articles__header { display: flex; align-items: center; justify-content: space-between; max-width: 900px; }
.erp-articles__create { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; }
.erp-articles__error { color: var(--color-error-text, #c00); }
.erp-articles__table { border-collapse: collapse; width: 100%; max-width: 900px; }
.erp-articles__table th, .erp-articles__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.erp-articles__row { cursor: pointer; }
.erp-articles__row:hover { background: var(--color-background-hover); }
.erp-articles__detail { padding: 10px; background: var(--color-background-dark); }
.erp-articles__prices { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.erp-articles__prices th, .erp-articles__prices td { text-align: left; padding: 4px 6px; }
.erp-articles__inline-form { display: flex; gap: 8px; }
</style>
