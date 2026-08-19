<template>
	<div class="erp-berechtigungen">
		<h2>Berechtigungen & Sätze</h2>
		<p class="erp-berechtigungen__hint">
			Rechte-Matrix (Roadmap Phase 2). Verrechnungssätze folgen erst in Phase 6 — siehe
			<code>docs/adr/0008-rechte-modell.md</code>. Nur Nextcloud-Instanz-Admins dürfen diese Seite
			bearbeiten; sie haben selbst immer "administrieren" auf alles, auch ohne eigenen Eintrag hier.
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
	</div>
</template>

<script>
import { fetchMatrix, fetchPrincipals, setMatrixEntry } from '../services/permissionsApi.js'
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
	data() {
		return {
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
		}
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
			this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		}
	},
	methods: {
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
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.erp-berechtigungen {
	padding: 20px;
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
</style>
