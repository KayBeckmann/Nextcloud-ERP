<template>
	<div class="erp-berechtigungen">
		<h2>Berechtigungen & Sätze</h2>

		<nav class="erp-berechtigungen__tabs">
			<button v-for="t in tabs" :key="t" :class="{ 'is-active': tab === t }" @click="tab = t">{{ t }}</button>
		</nav>

		<section v-if="tab === 'Rechte-Matrix'">
			<p class="erp-berechtigungen__hint">
				Rechte-Matrix (Roadmap Phase 2) — siehe <code>docs/adr/0008-rechte-modell.md</code>. Nur
				Nextcloud-Instanz-Admins dürfen diese Seite bearbeiten; sie haben selbst immer
				"administrieren" auf alles, auch ohne eigenen Eintrag hier.
			</p>

			<p v-if="loadError" class="erp-berechtigungen__error">
				Fehler beim Laden: {{ loadError }}
				<span v-if="isForbidden"> — vermutlich bist du kein Nextcloud-Instanz-Admin.</span>
			</p>

			<div v-else class="erp-berechtigungen__layout">
				<aside class="erp-berechtigungen__principals">
					<h3>User &amp; Gruppen</h3>
					<ul>
						<li
							v-for="p in principals"
							:key="`${p.type}:${p.id}`"
							:class="{ 'is-selected': isSelected(p) }"
							@click="selectedPrincipal = p">
							<span class="erp-principal__name">{{ p.displayName }}</span>
							<span class="erp-principal__tag">{{ p.type === 'group' ? 'Gruppe' : 'User' }}</span>
						</li>
					</ul>
				</aside>

				<section class="erp-berechtigungen__matrix">
					<h3 v-if="selectedPrincipal">
						Rechte für {{ selectedPrincipal.displayName }}
						<span class="erp-principal__tag">{{ selectedPrincipal.type === 'group' ? 'Gruppe' : 'User' }}</span>
					</h3>
					<p v-else>Links einen User oder eine Gruppe auswählen.</p>

					<table v-if="selectedPrincipal">
						<thead>
							<tr>
								<th>Modul</th>
								<th>Berechtigung</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="resource in resourceTypes" :key="resource">
								<td>{{ resourceLabel(resource) }}</td>
								<td>
									<select
										:value="currentLevel(resource)"
										:disabled="saving"
										@change="onChange(resource, $event.target.value)">
										<option v-for="level in permissionLevels" :key="level" :value="level">
											{{ levelLabel(level) }}
										</option>
									</select>
								</td>
							</tr>
						</tbody>
					</table>
				</section>
			</div>
		</section>

		<section v-else-if="tab === 'Standard-Verrechnungssätze'" class="erp-berechtigungen__rates">
			<p class="erp-berechtigungen__hint">
				Sätze je Arbeitsart, optional eingeschränkt auf einen User/eine Gruppe (ADR-0012,
				6-stufige Priorität bei der Auflösung). Ohne Einschränkung ("Alle") gilt der Satz
				global für die Arbeitsart.
			</p>
			<p v-if="ratesError" class="erp-berechtigungen__error">{{ ratesError }}</p>

			<table v-if="standardRates.length" class="erp-berechtigungen__rates-table">
				<thead><tr><th>Arbeitsart</th><th>Gilt für</th><th>Satz</th><th></th></tr></thead>
				<tbody>
					<tr v-for="r in standardRates" :key="r.id">
						<td>{{ workTypeName(r.workTypeId) }}</td>
						<td>{{ principalLabel(r.principalType, r.principalId) }}</td>
						<td>{{ formatMoney(r.rate) }}/h</td>
						<td><button @click="editRate(r)">Bearbeiten</button></td>
					</tr>
				</tbody>
			</table>
			<p v-else>Noch keine Sätze hinterlegt.</p>

			<h3>{{ editingRateId ? 'Satz bearbeiten' : 'Satz anlegen' }}</h3>
			<form class="erp-berechtigungen__rate-form" @submit.prevent="submitRate">
				<select v-model.number="newRate.workTypeId" required>
					<option :value="null">Arbeitsart wählen …</option>
					<option v-for="w in workTypes" :key="w.id" :value="w.id">{{ w.name }}</option>
				</select>
				<select v-model="newRate.principalKey">
					<option value="">Alle</option>
					<option v-for="p in principals" :key="`${p.type}:${p.id}`" :value="`${p.type}:${p.id}`">
						{{ p.displayName }} ({{ p.type === 'group' ? 'Gruppe' : 'User' }})
					</option>
				</select>
				<input v-model.number="newRate.rate" type="number" step="0.01" placeholder="Satz €/h" required>
				<button type="submit">{{ editingRateId ? 'Speichern' : 'Anlegen' }}</button>
				<button v-if="editingRateId" type="button" @click="cancelEditRate">Abbrechen</button>
			</form>
		</section>

		<section v-else-if="tab === 'Kundenverträge'" class="erp-berechtigungen__contracts">
			<p class="erp-berechtigungen__hint">
				Verträge binden Verrechnungssätze an einen Kunden (höchste Priorität bei der
				Satz-Auflösung, solange der Vertrag gültig ist, ADR-0012). Kunde wählen, um dessen
				Verträge zu sehen.
			</p>
			<label>Kunde <ContactPicker v-model="contractCustomerUid" placeholder="Kunde suchen …" /></label>
			<p v-if="contractsError" class="erp-berechtigungen__error">{{ contractsError }}</p>

			<template v-if="contractCustomerUid">
				<table v-if="contracts.length" class="erp-berechtigungen__contracts-table">
					<thead><tr><th>Titel</th><th>Gültig von</th><th>Gültig bis</th><th></th></tr></thead>
					<tbody>
						<template v-for="c in contracts" :key="c.id">
							<tr class="erp-berechtigungen__contract-row" @click="toggleContract(c)">
								<td>{{ c.title }}</td>
								<td>{{ formatDate(c.validFrom) }}</td>
								<td>{{ formatDate(c.validUntil) }}</td>
								<td>{{ expandedContractId === c.id ? '▲' : '▼' }}</td>
							</tr>
							<tr v-if="expandedContractId === c.id && contractDetail">
								<td colspan="4">
									<div class="erp-berechtigungen__contract-detail">
										<table v-if="contractDetail.rates.length">
											<thead><tr><th>Arbeitsart</th><th>Gilt für</th><th>Satz</th><th></th></tr></thead>
											<tbody>
												<tr v-for="r in contractDetail.rates" :key="r.id">
													<td>{{ workTypeName(r.workTypeId) }}</td>
													<td>{{ principalLabel(r.principalType, r.principalId) }}</td>
													<td>{{ formatMoney(r.rate) }}/h</td>
													<td><button @click="removeContractRateRow(c.id, r.id)">✕</button></td>
												</tr>
											</tbody>
										</table>
										<p v-else>Noch keine vertraglichen Sätze.</p>

										<form class="erp-berechtigungen__rate-form" @submit.prevent="submitContractRate(c.id)">
											<select v-model.number="newContractRate.workTypeId" required>
												<option :value="null">Arbeitsart wählen …</option>
												<option v-for="w in workTypes" :key="w.id" :value="w.id">{{ w.name }}</option>
											</select>
											<select v-model="newContractRate.principalKey">
												<option value="">Alle</option>
												<option v-for="p in principals" :key="`${p.type}:${p.id}`" :value="`${p.type}:${p.id}`">
													{{ p.displayName }} ({{ p.type === 'group' ? 'Gruppe' : 'User' }})
												</option>
											</select>
											<input v-model.number="newContractRate.rate" type="number" step="0.01" placeholder="Satz €/h" required>
											<button type="submit">+ Satz</button>
										</form>
									</div>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
				<p v-else>Noch keine Verträge für diesen Kunden.</p>

				<h3>Neuer Vertrag</h3>
				<form class="erp-berechtigungen__contract-form" @submit.prevent="submitContract">
					<input v-model="newContract.title" placeholder="Titel" required>
					<label>Gültig von <input v-model="newContract.validFrom" type="date"></label>
					<label>Gültig bis <input v-model="newContract.validUntil" type="date"></label>
					<input v-model="newContract.notes" placeholder="Notiz (optional)">
					<button type="submit">Anlegen</button>
				</form>
			</template>
		</section>
	</div>
</template>

<script>
import { fetchMatrix, fetchPrincipals, setMatrixEntry } from '../services/permissionsApi.js'
import { fetchWorkTypes } from '../services/settingsApi.js'
import {
	fetchStandardRates, setStandardRate as setStandardRateApi,
	fetchContracts, fetchContract, createContract, addContractRate, removeContractRate,
} from '../services/ratesApi.js'
import ContactPicker from '../components/ContactPicker.vue'
import { modules } from '../router/index.js'

const LEVEL_LABELS = {
	none: 'Kein Zugriff',
	read: 'Lesen',
	write: 'Lesen & Schreiben',
	approve: 'Freigeben/Buchen',
	admin: 'Administrieren',
}

export default {
	name: 'BerechtigungenView',
	components: { ContactPicker },
	data() {
		return {
			tab: 'Rechte-Matrix',
			tabs: ['Rechte-Matrix', 'Standard-Verrechnungssätze', 'Kundenverträge'],
			principals: [],
			resourceTypes: [],
			permissionLevels: [],
			entries: [],
			selectedPrincipal: null,
			loadError: null,
			isForbidden: false,
			saving: false,
			resourceTitles: Object.fromEntries([
				['dashboard', 'Dashboard'],
				...modules.map((m) => [m.path, m.title]),
			]),
			workTypes: [],
			standardRates: [],
			ratesError: null,
			newRate: { workTypeId: null, principalKey: '', rate: 0 },
			editingRateId: null,
			contractCustomerUid: null,
			contracts: [],
			contractsError: null,
			expandedContractId: null,
			contractDetail: null,
			newContract: { title: '', validFrom: '', validUntil: '', notes: '' },
			newContractRate: { workTypeId: null, principalKey: '', rate: 0 },
		}
	},
	watch: {
		async contractCustomerUid(uid) {
			this.expandedContractId = null
			this.contractDetail = null
			if (!uid) {
				this.contracts = []
				return
			}
			try {
				this.contracts = await fetchContracts(uid)
			} catch (e) {
				this.contractsError = this.errorMessage(e)
			}
		},
	},
	async mounted() {
		try {
			const [principals, matrix] = await Promise.all([fetchPrincipals(), fetchMatrix()])
			this.principals = principals
			this.resourceTypes = matrix.resourceTypes
			this.permissionLevels = matrix.permissionLevels
			this.entries = matrix.entries
		} catch (e) {
			this.isForbidden = e?.response?.status === 403
			this.loadError = this.errorMessage(e)
		}
		try {
			this.workTypes = await fetchWorkTypes()
			this.standardRates = await fetchStandardRates()
		} catch (e) {
			this.ratesError = this.errorMessage(e)
		}
	},
	methods: {
		errorMessage(e) {
			return e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		},
		formatMoney(value) {
			return `${Number(value ?? 0).toFixed(2)} €`
		},
		formatDate(unixSeconds) {
			return unixSeconds ? new Date(unixSeconds * 1000).toLocaleDateString('de-DE') : '—'
		},
		dateInputToUnix(dateStr) {
			return dateStr ? Math.floor(new Date(`${dateStr}T00:00:00`).getTime() / 1000) : null
		},
		workTypeName(id) {
			return this.workTypes.find((w) => w.id === id)?.name ?? id
		},
		principalLabel(principalType, principalId) {
			if (principalType === null) {
				return 'Alle'
			}
			const p = this.principals.find((x) => x.type === principalType && x.id === principalId)
			return p ? `${p.displayName} (${principalType === 'group' ? 'Gruppe' : 'User'})` : `${principalId} (${principalType})`
		},
		splitPrincipalKey(key) {
			if (!key) {
				return { principalType: null, principalId: null }
			}
			const [principalType, principalId] = key.split(':')
			return { principalType, principalId }
		},
		isSelected(p) {
			return this.selectedPrincipal && this.selectedPrincipal.type === p.type && this.selectedPrincipal.id === p.id
		},
		resourceLabel(resource) {
			return this.resourceTitles[resource] ?? resource
		},
		levelLabel(level) {
			return LEVEL_LABELS[level] ?? level
		},
		currentLevel(resource) {
			const entry = this.entries.find(
				(e) => e.resourceType === resource
					&& e.principalType === this.selectedPrincipal.type
					&& e.principalId === this.selectedPrincipal.id,
			)
			return entry?.permission ?? 'none'
		},
		async onChange(resource, permission) {
			this.saving = true
			try {
				await setMatrixEntry({
					principalType: this.selectedPrincipal.type,
					principalId: this.selectedPrincipal.id,
					resourceType: resource,
					permission,
				})
				this.entries = this.entries.filter(
					(e) => !(e.resourceType === resource
						&& e.principalType === this.selectedPrincipal.type
						&& e.principalId === this.selectedPrincipal.id),
				)
				if (permission !== 'none') {
					this.entries.push({
						principalType: this.selectedPrincipal.type,
						principalId: this.selectedPrincipal.id,
						resourceType: resource,
						permission,
					})
				}
			} catch (e) {
				this.loadError = this.errorMessage(e)
			} finally {
				this.saving = false
			}
		},
		editRate(r) {
			this.editingRateId = r.id
			this.newRate = {
				workTypeId: r.workTypeId,
				principalKey: r.principalType ? `${r.principalType}:${r.principalId}` : '',
				rate: r.rate,
			}
		},
		cancelEditRate() {
			this.editingRateId = null
			this.newRate = { workTypeId: null, principalKey: '', rate: 0 }
		},
		async submitRate() {
			this.ratesError = null
			try {
				const { principalType, principalId } = this.splitPrincipalKey(this.newRate.principalKey)
				await setStandardRateApi({ workTypeId: this.newRate.workTypeId, principalType, principalId, rate: this.newRate.rate })
				this.cancelEditRate()
				this.standardRates = await fetchStandardRates()
			} catch (e) {
				this.ratesError = this.errorMessage(e)
			}
		},
		async toggleContract(c) {
			if (this.expandedContractId === c.id) {
				this.expandedContractId = null
				this.contractDetail = null
				return
			}
			this.expandedContractId = c.id
			try {
				this.contractDetail = await fetchContract(c.id)
			} catch (e) {
				this.contractsError = this.errorMessage(e)
			}
		},
		async submitContract() {
			this.contractsError = null
			try {
				await createContract({
					customerContactUid: this.contractCustomerUid,
					title: this.newContract.title,
					validFrom: this.dateInputToUnix(this.newContract.validFrom),
					validUntil: this.dateInputToUnix(this.newContract.validUntil),
					notes: this.newContract.notes || null,
				})
				this.newContract = { title: '', validFrom: '', validUntil: '', notes: '' }
				this.contracts = await fetchContracts(this.contractCustomerUid)
			} catch (e) {
				this.contractsError = this.errorMessage(e)
			}
		},
		async submitContractRate(contractId) {
			this.contractsError = null
			try {
				const { principalType, principalId } = this.splitPrincipalKey(this.newContractRate.principalKey)
				await addContractRate(contractId, { workTypeId: this.newContractRate.workTypeId, principalType, principalId, rate: this.newContractRate.rate })
				this.newContractRate = { workTypeId: null, principalKey: '', rate: 0 }
				this.contractDetail = await fetchContract(contractId)
			} catch (e) {
				this.contractsError = this.errorMessage(e)
			}
		},
		async removeContractRateRow(contractId, id) {
			try {
				await removeContractRate(contractId, id)
				this.contractDetail = await fetchContract(contractId)
			} catch (e) {
				this.contractsError = this.errorMessage(e)
			}
		},
	},
}
</script>

<style scoped>
.erp-berechtigungen {
	padding: 20px;
	padding-bottom: 60px;
}
.erp-berechtigungen__tabs {
	display: flex;
	gap: 8px;
	margin-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}
.erp-berechtigungen__tabs button {
	background: none;
	border: none;
	padding: 8px 12px;
	cursor: pointer;
	border-bottom: 2px solid transparent;
}
.erp-berechtigungen__tabs button.is-active {
	border-bottom-color: var(--color-primary-element);
	font-weight: bold;
}
.erp-berechtigungen__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
	max-width: 720px;
}
.erp-berechtigungen__error {
	color: var(--color-error-text, #c00);
}
.erp-berechtigungen__layout {
	display: flex;
	gap: 24px;
	align-items: flex-start;
}
.erp-berechtigungen__principals {
	min-width: 220px;
}
.erp-berechtigungen__principals ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
.erp-berechtigungen__principals li {
	padding: 6px 10px;
	border-radius: var(--border-radius, 4px);
	cursor: pointer;
	display: flex;
	justify-content: space-between;
	gap: 8px;
}
.erp-berechtigungen__principals li:hover,
.erp-berechtigungen__principals li.is-selected {
	background: var(--color-primary-element-light);
}
.erp-principal__tag {
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}
.erp-berechtigungen__matrix table {
	border-collapse: collapse;
	min-width: 420px;
}
.erp-berechtigungen__matrix th,
.erp-berechtigungen__matrix td {
	text-align: left;
	padding: 6px 10px;
	border-bottom: 1px solid var(--color-border);
}
.erp-berechtigungen__rates-table, .erp-berechtigungen__contracts-table {
	border-collapse: collapse;
	width: 100%;
	max-width: 720px;
	margin-bottom: 16px;
}
.erp-berechtigungen__rates-table th, .erp-berechtigungen__rates-table td,
.erp-berechtigungen__contracts-table th, .erp-berechtigungen__contracts-table td {
	text-align: left;
	padding: 6px 10px;
	border-bottom: 1px solid var(--color-border);
	font-size: 13px;
}
.erp-berechtigungen__contract-row {
	cursor: pointer;
}
.erp-berechtigungen__contract-row:hover {
	background: var(--color-background-hover);
}
.erp-berechtigungen__contract-detail {
	padding: 10px;
	background: var(--color-background-dark);
}
.erp-berechtigungen__rate-form, .erp-berechtigungen__contract-form {
	display: flex;
	gap: 8px;
	margin: 10px 0;
	flex-wrap: wrap;
	align-items: center;
}
</style>
