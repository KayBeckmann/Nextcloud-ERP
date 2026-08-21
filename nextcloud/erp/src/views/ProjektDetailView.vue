<template>
	<div class="erp-project-detail">
		<p v-if="loadError" class="erp-project-detail__error">{{ loadError }}</p>
		<template v-else-if="project">
			<header>
				<h2>{{ project.projectNumber }} — {{ project.title }}</h2>
				<span class="erp-status-badge" :class="`is-${project.status}`">{{ statusLabel(project.status) }}</span>
			</header>

			<nav class="erp-project-detail__tabs">
				<button v-for="t in tabs" :key="t" :class="{ 'is-active': tab === t }" @click="tab = t">{{ t }}</button>
			</nav>

			<section v-if="tab === 'Übersicht'" class="erp-project-detail__section">
				<label>Titel <input v-model="edit.title"></label>
				<label>Status
					<select v-model="edit.status">
						<option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
					</select>
				</label>
				<label>Kunde <ContactPicker v-model="edit.customerContactUid" placeholder="Kunde suchen …" /></label>
				<label>Verantwortlich <UserPicker v-model="edit.responsibleUserId" placeholder="User suchen …" /></label>
				<label>Notizen <textarea v-model="edit.notes" rows="4"></textarea></label>
				<button @click="save">Speichern</button>
				<p v-if="project.filesFolderId">
					<a :href="openInFilesUrl(project.filesFolderId)" target="_blank" rel="noopener">Projektordner öffnen</a>
				</p>
			</section>

			<section v-else-if="tab === 'Aufgaben'" class="erp-project-detail__section">
				<ul class="erp-project-detail__tasks">
					<li v-for="t in tasks" :key="t.id">
						<input type="checkbox" :checked="t.done" @change="toggleTask(t, $event.target.checked)">
						<span :class="{ 'is-done': t.done }">{{ t.title }}</span>
						<button @click="removeTask(t)">✕</button>
					</li>
				</ul>
				<form class="erp-project-detail__inline-form" @submit.prevent="addTask">
					<input v-model="newTaskTitle" placeholder="Neue Aufgabe" required>
					<button type="submit">+</button>
				</form>
			</section>

			<section v-else-if="tab === 'Aufträge'" class="erp-project-detail__section">
				<ul class="erp-project-detail__orders">
					<li v-for="o in orders" :key="o.id">
						<strong>{{ o.title }}</strong>
						<select :value="o.status" @change="updateOrderStatus(o, $event.target.value)">
							<option value="draft">Entwurf</option>
							<option value="confirmed">Bestätigt</option>
							<option value="done">Abgeschlossen</option>
						</select>
						<p v-if="o.description">{{ o.description }}</p>
					</li>
				</ul>
				<form class="erp-project-detail__inline-form" @submit.prevent="addOrder">
					<input v-model="newOrderTitle" placeholder="Neuer Auftrag" required>
					<button type="submit">+</button>
				</form>
			</section>

			<section v-else-if="tab === 'Angebote'" class="erp-project-detail__section">
				<AngeboteView :project-id="id" />
			</section>

			<section v-else-if="tab === 'Rechnungen'" class="erp-project-detail__section">
				<RechnungenView :project-id="id" />
			</section>

			<section v-else-if="tab === 'Lieferscheine'" class="erp-project-detail__section">
				<LieferscheineView :project-id="id" />
			</section>

			<section v-else-if="tab === 'Gutschriften'" class="erp-project-detail__section">
				<h3>Gutschriften</h3>
				<table v-if="creditNotes.length" class="erp-project-detail__credit-notes">
					<thead><tr><th>Nr.</th><th>Grund</th><th>Vollstorno</th><th>Status</th><th></th></tr></thead>
					<tbody>
						<tr v-for="cn in creditNotes" :key="cn.id">
							<td>{{ cn.creditNoteNumber || '(Entwurf)' }}</td>
							<td>{{ cn.reason }}</td>
							<td>{{ cn.cancelsInvoice ? 'ja' : 'nein' }}</td>
							<td><span class="erp-status-badge" :class="`is-${cn.status}`">{{ cn.status }}</span></td>
							<td><a href="#" @click.prevent="openInvoice(cn.invoiceId)">zur Rechnung</a></td>
						</tr>
					</tbody>
				</table>
				<p v-else>Noch keine Gutschriften in diesem Projekt.</p>
			</section>

			<section v-else-if="tab === 'Termine'" class="erp-project-detail__section">
				<ul class="erp-project-detail__events">
					<li v-for="l in calendarLinks" :key="l.id">{{ l.summary }} <small>({{ l.calendarUri }})</small></li>
				</ul>
				<form class="erp-project-detail__inline-form" @submit.prevent="addEvent">
					<input v-model="newEventSummary" placeholder="Termintitel" required>
					<input v-model="newEventStart" type="datetime-local" required>
					<input v-model="newEventEnd" type="datetime-local" required>
					<button type="submit">Termin anlegen</button>
				</form>
			</section>

			<section v-else-if="tab === 'Dokumente'" class="erp-project-detail__section">
				<p v-if="project.filesFolderId">
					<a :href="openInFilesUrl(project.filesFolderId)" target="_blank" rel="noopener">Projektordner in Dateien öffnen</a>
				</p>
				<p v-else>Kein Projektordner hinterlegt.</p>
			</section>
		</template>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import {
	fetchProject, updateProject,
	fetchTasks, createTask, updateTask, deleteTask,
	fetchOrders, createOrder, updateOrder,
} from '../services/projectsApi.js'
import { fetchCalendarLinks, createCalendarEvent } from '../services/calendarApi.js'
import { fetchCreditNotes } from '../services/invoicesApi.js'
import ContactPicker from '../components/ContactPicker.vue'
import UserPicker from '../components/UserPicker.vue'
import AngeboteView from './AngeboteView.vue'
import RechnungenView from './RechnungenView.vue'
import LieferscheineView from './LieferscheineView.vue'

const STATUS_LABELS = {
	draft: 'Entwurf',
	quote: 'Angebot',
	in_progress: 'In Bearbeitung',
	waiting: 'Wartet',
	done: 'Abgeschlossen',
	archived: 'Archiv',
}

export default {
	name: 'ProjektDetailView',
	components: { ContactPicker, UserPicker, AngeboteView, RechnungenView, LieferscheineView },
	props: {
		id: { type: [String, Number], required: true },
	},
	data() {
		return {
			project: null,
			edit: { title: '', status: 'draft', customerContactUid: null, responsibleUserId: null, notes: '' },
			tasks: [],
			orders: [],
			calendarLinks: [],
			creditNotes: [],
			loadError: null,
			tab: 'Übersicht',
			tabs: ['Übersicht', 'Aufgaben', 'Angebote', 'Aufträge', 'Rechnungen', 'Lieferscheine', 'Gutschriften', 'Termine', 'Dokumente'],
			statusOptions: Object.keys(STATUS_LABELS),
			newTaskTitle: '',
			newOrderTitle: '',
			newEventSummary: '',
			newEventStart: '',
			newEventEnd: '',
		}
	},
	async mounted() {
		await this.loadAll()
	},
	watch: {
		async tab(newTab) {
			if (newTab === 'Gutschriften') {
				await this.loadCreditNotes()
			}
		},
	},
	methods: {
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		openInFilesUrl(fileId) {
			return generateUrl(`/f/${fileId}`)
		},
		openInvoice(invoiceId) {
			this.$router.push({ name: 'rechnung-detail', params: { id: invoiceId } })
		},
		async loadAll() {
			try {
				this.project = await fetchProject(this.id)
				this.edit = {
					title: this.project.title,
					status: this.project.status,
					customerContactUid: this.project.customerContactUid ?? null,
					responsibleUserId: this.project.responsibleUserId ?? null,
					notes: this.project.notes ?? '',
				}
				const [tasks, orders, links] = await Promise.all([
					fetchTasks(this.id),
					fetchOrders(this.id),
					fetchCalendarLinks('projekte', String(this.id)),
				])
				this.tasks = tasks
				this.orders = orders
				this.calendarLinks = links
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async loadCreditNotes() {
			try {
				this.creditNotes = await fetchCreditNotes(null, this.id)
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async save() {
			try {
				this.project = await updateProject(this.id, {
					title: this.edit.title,
					status: this.edit.status,
					customerContactUid: this.edit.customerContactUid || null,
					responsibleUserId: this.edit.responsibleUserId || null,
					notes: this.edit.notes || null,
				})
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async addTask() {
			await createTask(this.id, this.newTaskTitle)
			this.newTaskTitle = ''
			this.tasks = await fetchTasks(this.id)
		},
		async toggleTask(task, done) {
			await updateTask(this.id, task.id, { title: task.title, done })
			this.tasks = await fetchTasks(this.id)
		},
		async removeTask(task) {
			await deleteTask(this.id, task.id)
			this.tasks = this.tasks.filter((t) => t.id !== task.id)
		},
		async addOrder() {
			await createOrder(this.id, { title: this.newOrderTitle })
			this.newOrderTitle = ''
			this.orders = await fetchOrders(this.id)
		},
		async updateOrderStatus(order, status) {
			await updateOrder(this.id, order.id, { title: order.title, status, description: order.description })
			this.orders = await fetchOrders(this.id)
		},
		async addEvent() {
			await createCalendarEvent({
				calendarUri: 'personal',
				resourceType: 'projekte',
				resourceId: String(this.id),
				summary: this.newEventSummary,
				start: this.newEventStart,
				end: this.newEventEnd,
			})
			this.newEventSummary = ''
			this.calendarLinks = await fetchCalendarLinks('projekte', String(this.id))
		},
	},
}
</script>

<style scoped>
.erp-project-detail {
	padding: 20px;
	max-width: 960px;
}
.erp-project-detail__error {
	color: var(--color-error-text, #c00);
}
header {
	display: flex;
	align-items: center;
	gap: 12px;
}
.erp-project-detail__tabs {
	display: flex;
	gap: 4px;
	margin: 16px 0;
	border-bottom: 1px solid var(--color-border);
	flex-wrap: wrap;
}
.erp-project-detail__tabs button {
	background: none;
	border: none;
	padding: 8px 12px;
	cursor: pointer;
}
.erp-project-detail__tabs button.is-active {
	border-bottom: 2px solid var(--color-primary-element);
	font-weight: bold;
}
.erp-project-detail__section label {
	display: block;
	margin-bottom: 10px;
}
.erp-project-detail__section input,
.erp-project-detail__section select,
.erp-project-detail__section textarea {
	display: block;
	width: 100%;
	margin-top: 2px;
	max-width: 400px;
}
.erp-project-detail__tasks,
.erp-project-detail__orders,
.erp-project-detail__events {
	list-style: none;
	padding: 0;
}
.erp-project-detail__tasks li {
	display: flex;
	align-items: center;
	gap: 8px;
}
.erp-project-detail__tasks .is-done {
	text-decoration: line-through;
	color: var(--color-text-maxcontrast);
}
.erp-project-detail__inline-form {
	display: flex;
	gap: 8px;
	margin-top: 10px;
}
.erp-project-detail__credit-notes {
	border-collapse: collapse;
	width: 100%;
	max-width: 900px;
}
.erp-project-detail__credit-notes th,
.erp-project-detail__credit-notes td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}
.erp-status-badge {
	font-size: 11px;
	padding: 2px 8px;
	border-radius: 10px;
	background: var(--color-background-dark);
}
</style>
