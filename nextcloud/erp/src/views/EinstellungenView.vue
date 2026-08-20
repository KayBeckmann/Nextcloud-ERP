<template>
	<div class="erp-settings">
		<h2>Einstellungen</h2>

		<section class="erp-settings__section">
			<h3>Dateien &amp; Ordner</h3>
			<p class="erp-settings__hint">
				Legt die ERP-Ordnerstruktur in deinem persönlichen Nextcloud-Dateibereich an
				(<code>ERP/Projekte</code>, <code>ERP/Artikel</code>, …). Mehrfach ausführbar,
				bestehende Ordner werden nicht verändert. Bekannte Einschränkung: aktuell pro User,
				siehe <code>docs/adr/0009-contacts-calendar-files-integration.md</code>.
			</p>
			<button :disabled="loadingFolders" @click="loadFolders">
				{{ folders.length ? 'Erneut prüfen' : 'ERP-Ordnerstruktur anlegen/prüfen' }}
			</button>
			<p v-if="folderError" class="erp-settings__error">{{ folderError }}</p>
			<ul v-if="folders.length" class="erp-settings__folders">
				<li v-for="f in folders" :key="f.fileId">
					<a :href="openInFilesUrl(f.fileId)" target="_blank" rel="noopener">{{ f.name }}</a>
					<span class="erp-settings__path">{{ f.path }}</span>
				</li>
			</ul>
		</section>

		<section class="erp-settings__section">
			<h3>MwSt.-Sätze</h3>
			<table v-if="vatRates.length" class="erp-settings__table">
				<thead><tr><th>Name</th><th>Prozentsatz</th><th>Standard</th><th>Aktiv</th></tr></thead>
				<tbody>
					<tr v-for="v in vatRates" :key="v.id">
						<td>{{ v.name }}</td>
						<td>{{ v.percentage }}%</td>
						<td>{{ v.isDefault ? '✓' : '' }}</td>
						<td>{{ v.active ? '✓' : '' }}</td>
					</tr>
				</tbody>
			</table>
			<form class="erp-settings__inline-form" @submit.prevent="submitVatRate">
				<input v-model="newVatRate.name" placeholder="Name, z. B. Standard 19%" required>
				<input v-model.number="newVatRate.percentage" type="number" step="0.01" placeholder="Prozentsatz" required>
				<label><input v-model="newVatRate.isDefault" type="checkbox"> Standard</label>
				<button type="submit">+ MwSt.-Satz</button>
			</form>
		</section>

		<section class="erp-settings__section">
			<h3>Arbeitsarten</h3>
			<table v-if="workTypes.length" class="erp-settings__table">
				<thead><tr><th>Name</th><th>Stundensatz</th></tr></thead>
				<tbody>
					<tr v-for="w in workTypes" :key="w.id">
						<td>{{ w.name }}</td>
						<td>{{ w.hourlyRate }} €</td>
					</tr>
				</tbody>
			</table>
			<form class="erp-settings__inline-form" @submit.prevent="submitWorkType">
				<input v-model="newWorkType.name" placeholder="Name, z. B. Monteur" required>
				<input v-model.number="newWorkType.hourlyRate" type="number" step="0.01" placeholder="Stundensatz" required>
				<button type="submit">+ Arbeitsart</button>
			</form>
		</section>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { ensureErpFolder } from '../services/filesApi.js'
import { createVatRate, createWorkType, fetchVatRates, fetchWorkTypes } from '../services/settingsApi.js'

export default {
	name: 'EinstellungenView',
	data() {
		return {
			folders: [],
			loadingFolders: false,
			folderError: null,
			vatRates: [],
			workTypes: [],
			newVatRate: { name: '', percentage: null, isDefault: false },
			newWorkType: { name: '', hourlyRate: null },
		}
	},
	async mounted() {
		this.vatRates = await fetchVatRates()
		this.workTypes = await fetchWorkTypes()
	},
	methods: {
		openInFilesUrl(fileId) {
			// Nextclouds generischer "öffne per Datei-ID"-Redirect.
			return generateUrl(`/f/${fileId}`)
		},
		async loadFolders() {
			this.loadingFolders = true
			this.folderError = null
			try {
				this.folders = await ensureErpFolder()
			} catch (e) {
				this.folderError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			} finally {
				this.loadingFolders = false
			}
		},
		async submitVatRate() {
			await createVatRate(this.newVatRate)
			this.newVatRate = { name: '', percentage: null, isDefault: false }
			this.vatRates = await fetchVatRates()
		},
		async submitWorkType() {
			await createWorkType(this.newWorkType)
			this.newWorkType = { name: '', hourlyRate: null }
			this.workTypes = await fetchWorkTypes()
		},
	},
}
</script>

<style scoped>
.erp-settings {
	padding: 20px;
	max-width: 720px;
}
.erp-settings__section {
	margin-bottom: 28px;
}
.erp-settings__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 12px;
}
.erp-settings__error {
	color: var(--color-error-text, #c00);
}
.erp-settings__folders {
	list-style: none;
	margin: 12px 0 0;
	padding: 0;
}
.erp-settings__folders li {
	display: flex;
	gap: 12px;
	padding: 3px 0;
}
.erp-settings__path {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}
.erp-settings__table {
	border-collapse: collapse;
	width: 100%;
	margin-bottom: 10px;
}
.erp-settings__table th,
.erp-settings__table td {
	text-align: left;
	padding: 4px 8px;
	border-bottom: 1px solid var(--color-border);
}
.erp-settings__inline-form {
	display: flex;
	gap: 8px;
	align-items: center;
	flex-wrap: wrap;
}
</style>
