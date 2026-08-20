<template>
	<div class="erp-projects">
		<div class="erp-projects__header">
			<h2>Projekte</h2>
			<button @click="showCreate = !showCreate">+ Projekt</button>
		</div>

		<div class="erp-projects__filters">
			<button
				v-for="chip in statusChips"
				:key="chip.value || 'all'"
				:class="{ 'is-active': filter === chip.value }"
				@click="setFilter(chip.value)">
				{{ chip.label }}
			</button>
		</div>

		<form v-if="showCreate" class="erp-projects__create" @submit.prevent="submitCreate">
			<input v-model="newTitle" placeholder="Projekttitel" required>
			<input v-model="newCustomerUid" placeholder="Kunde (Contact-UID, optional)">
			<button type="submit">Anlegen</button>
		</form>

		<p v-if="loadError" class="erp-projects__error">{{ loadError }}</p>

		<table v-if="projects.length" class="erp-projects__table">
			<thead>
				<tr>
					<th>Nr.</th>
					<th>Titel</th>
					<th>Kunde</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="p in projects" :key="p.id" class="erp-projects__row" @click="openProject(p.id)">
					<td>{{ p.projectNumber }}</td>
					<td>{{ p.title }}</td>
					<td>{{ customerNames[p.id] || p.customerContactUid || '—' }}</td>
					<td><span class="erp-status-badge" :class="`is-${p.status}`">{{ statusLabel(p.status) }}</span></td>
				</tr>
			</tbody>
		</table>
		<p v-else-if="!loadError">Noch keine Projekte{{ filter ? ' mit diesem Status' : '' }}.</p>
	</div>
</template>

<script>
import { createProject, fetchProjects } from '../services/projectsApi.js'
import { resolveContactName } from '../services/contactsApi.js'

const STATUS_LABELS = {
	draft: 'Entwurf',
	quote: 'Angebot',
	in_progress: 'In Bearbeitung',
	waiting: 'Wartet',
	done: 'Abgeschlossen',
	archived: 'Archiv',
}

export default {
	name: 'ProjekteView',
	data() {
		return {
			projects: [],
			customerNames: {},
			loadError: null,
			filter: null,
			showCreate: false,
			newTitle: '',
			newCustomerUid: '',
			statusChips: [
				{ value: null, label: 'Alle' },
				{ value: 'draft', label: 'Entwurf' },
				{ value: 'quote', label: 'Angebot' },
				{ value: 'in_progress', label: 'In Bearbeitung' },
				{ value: 'waiting', label: 'Wartet' },
				{ value: 'done', label: 'Abgeschlossen' },
				{ value: 'archived', label: 'Archiv' },
			],
		}
	},
	async mounted() {
		await this.load()
	},
	methods: {
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		async setFilter(value) {
			this.filter = value
			await this.load()
		},
		async load() {
			try {
				this.projects = await fetchProjects(this.filter)
				await Promise.all(this.projects
					.filter((p) => p.customerContactUid && !this.customerNames[p.id])
					.map(async (p) => {
						const resolved = await resolveContactName(p.customerContactUid)
						this.customerNames = { ...this.customerNames, [p.id]: resolved.displayName }
					}))
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async submitCreate() {
			try {
				await createProject({ title: this.newTitle, customerContactUid: this.newCustomerUid || null })
				this.newTitle = ''
				this.newCustomerUid = ''
				this.showCreate = false
				await this.load()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		openProject(id) {
			this.$router.push({ name: 'projekt-detail', params: { id } })
		},
	},
}
</script>

<style scoped>
.erp-projects {
	padding: 20px;
}
.erp-projects__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	max-width: 900px;
}
.erp-projects__filters {
	display: flex;
	gap: 6px;
	margin: 12px 0;
	flex-wrap: wrap;
}
.erp-projects__filters button {
	border-radius: 16px;
	padding: 4px 12px;
	font-size: 12px;
}
.erp-projects__filters button.is-active {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}
.erp-projects__create {
	display: flex;
	gap: 8px;
	margin-bottom: 16px;
}
.erp-projects__error {
	color: var(--color-error-text, #c00);
}
.erp-projects__table {
	border-collapse: collapse;
	width: 100%;
	max-width: 900px;
}
.erp-projects__table th,
.erp-projects__table td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}
.erp-projects__row {
	cursor: pointer;
}
.erp-projects__row:hover {
	background: var(--color-background-hover);
}
.erp-status-badge {
	font-size: 11px;
	padding: 2px 8px;
	border-radius: 10px;
	background: var(--color-background-dark);
}
.erp-status-badge.is-in_progress {
	background: #fff3cd;
	color: #7a5b00;
}
.erp-status-badge.is-done {
	background: #d4edda;
	color: #155724;
}
.erp-status-badge.is-archived {
	background: var(--color-background-darker, #eee);
}
</style>
