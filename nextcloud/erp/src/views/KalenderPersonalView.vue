<template>
	<div class="erp-calendar-personal">
		<header class="erp-calendar-personal__header">
			<div>
				<h2>Kalender &amp; Personal</h2>
				<p>Ihre Kalender bleiben die Quelle der Wahrheit in Nextcloud Calendar.</p>
			</div>
			<button :disabled="loading" @click="load">Aktualisieren</button>
		</header>

		<p v-if="calendarError" class="erp-calendar-personal__error" role="alert">{{ calendarError }}</p>
		<p v-if="projectError" class="erp-calendar-personal__error" role="alert">{{ projectError }}</p>

		<section class="erp-calendar-personal__section" aria-labelledby="calendar-list-heading">
			<h3 id="calendar-list-heading">Verfügbare Kalender</h3>
			<p v-if="loading">Kalender werden geladen …</p>
			<p v-else-if="!calendars.length" class="erp-calendar-personal__hint">
				Es sind keine Kalender verfügbar. Aktivieren oder erstellen Sie zuerst einen Kalender in der Nextcloud-App „Kalender“.
			</p>
			<ul v-else class="erp-calendar-personal__calendar-list">
				<li v-for="calendar in calendars" :key="calendar.uri">
					<strong>{{ calendar.displayName }}</strong>
					<span :class="calendar.writable ? 'is-writable' : 'is-read-only'">{{ calendar.writable ? 'Beschreibbar' : 'Nur lesbar' }}</span>
				</li>
			</ul>
		</section>

		<section class="erp-calendar-personal__section" aria-labelledby="event-form-heading">
			<h3 id="event-form-heading">Projekttermin anlegen</h3>
			<p class="erp-calendar-personal__hint">Der Termin wird direkt in Nextcloud Calendar angelegt und mit dem gewählten aktiven Projekt verknüpft.</p>
			<p v-if="calendars.length && !writableCalendars.length" class="erp-calendar-personal__hint">
				Keiner Ihrer verfügbaren Kalender ist beschreibbar. Bitten Sie die Kalenderverwaltung um Schreibberechtigung oder wählen Sie einen eigenen Kalender.
			</p>
			<p v-else-if="!activeProjectList.length && !projectError" class="erp-calendar-personal__hint">
				Es gibt keine aktiven Projekte. Reaktivieren oder erstellen Sie zuerst ein Projekt, um einen Projekttermin anzulegen.
			</p>
			<form v-else class="erp-calendar-personal__form" @submit.prevent="submitEvent">
				<label>Kalender
					<select v-model="event.calendarUri" required :disabled="!writableCalendars.length">
						<option v-for="calendar in writableCalendars" :key="calendar.uri" :value="calendar.uri">{{ calendar.displayName }}</option>
					</select>
				</label>
				<label>Aktives Projekt
					<select v-model="event.projectId" required :disabled="!activeProjectList.length">
						<option disabled value="">Projekt auswählen …</option>
						<option v-for="project in activeProjectList" :key="project.id" :value="String(project.id)">{{ project.projectNumber ? `${project.projectNumber} — ${project.title}` : project.title }}</option>
					</select>
				</label>
				<label>Termintitel <input v-model="event.summary" required maxlength="255" placeholder="z. B. Baustellenbesprechung"></label>
				<label>Beginn <input v-model="event.start" type="datetime-local" required></label>
				<label>Ende <input v-model="event.end" type="datetime-local" required></label>
				<label class="erp-calendar-personal__description">Beschreibung <textarea v-model="event.description" rows="3" placeholder="Optional"></textarea></label>
				<button type="submit" :disabled="submitting || !canCreateEvent">{{ submitting ? 'Termin wird angelegt …' : 'Termin in Calendar anlegen' }}</button>
			</form>
			<p v-if="eventError" class="erp-calendar-personal__error" role="alert">{{ eventError }}</p>
			<p v-if="eventSuccess" class="erp-calendar-personal__success" role="status">{{ eventSuccess }}</p>
		</section>
	</div>
</template>

<script>
import { createCalendarEvent, fetchCalendars } from '../services/calendarApi.js'
import { fetchProjects } from '../services/projectsApi.js'
import {
	activeProjects,
	buildProjectEventPayload,
	defaultWritableCalendarUri,
	isValidEventInterval,
	userFacingCalendarError,
} from '../services/calendarPersonal.mjs'

export default {
	name: 'KalenderPersonalView',
	data() {
		return {
			calendars: [],
			projects: [],
			loading: true,
			submitting: false,
			calendarError: null,
			projectError: null,
			eventError: null,
			eventSuccess: null,
			event: { calendarUri: '', projectId: '', summary: '', start: '', end: '', description: '' },
		}
	},
	computed: {
		writableCalendars() {
			return this.calendars.filter((calendar) => calendar.writable)
		},
		activeProjectList() {
			return activeProjects(this.projects)
		},
		selectedProject() {
			return this.activeProjectList.find((project) => String(project.id) === this.event.projectId) ?? null
		},
		canCreateEvent() {
			return Boolean(this.event.calendarUri && this.selectedProject && this.event.summary.trim() && isValidEventInterval(this.event.start, this.event.end))
		},
	},
	async mounted() {
		await this.load()
	},
	methods: {
		async load() {
			this.loading = true
			this.calendarError = null
			this.projectError = null
			const [calendars, projects] = await Promise.allSettled([fetchCalendars(), fetchProjects()])
			if (calendars.status === 'fulfilled') {
				this.calendars = calendars.value
				this.event.calendarUri = defaultWritableCalendarUri(this.calendars) ?? ''
			} else {
				this.calendars = []
				this.calendarError = userFacingCalendarError(calendars.reason)
			}
			if (projects.status === 'fulfilled') {
				this.projects = projects.value
			} else {
				this.projects = []
				this.projectError = `Projekte konnten nicht geladen werden: ${projects.reason?.response?.data?.ocs?.meta?.message ?? projects.reason?.message ?? String(projects.reason)}`
			}
			this.loading = false
		},
		async submitEvent() {
			if (!this.canCreateEvent) return
			this.submitting = true
			this.eventError = null
			this.eventSuccess = null
			try {
				await createCalendarEvent(buildProjectEventPayload({
					calendarUri: this.event.calendarUri,
					project: this.selectedProject,
					summary: this.event.summary,
					start: this.event.start,
					end: this.event.end,
					description: this.event.description,
				}))
				this.eventSuccess = 'Termin wurde in Nextcloud Calendar angelegt.'
				this.event.summary = ''
				this.event.start = ''
				this.event.end = ''
				this.event.description = ''
			} catch (error) {
				this.eventError = userFacingCalendarError(error)
			} finally {
				this.submitting = false
			}
		},
	},
}
</script>

<style scoped>
.erp-calendar-personal { max-width: 900px; padding: 20px 20px 80px; }
.erp-calendar-personal__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.erp-calendar-personal__header h2 { margin: 0; }
.erp-calendar-personal__header p { margin: 6px 0 0; color: var(--color-text-maxcontrast); }
.erp-calendar-personal__section { margin-top: 28px; padding: 18px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large, 8px); }
.erp-calendar-personal__section h3 { margin-top: 0; }
.erp-calendar-personal__calendar-list { list-style: none; padding: 0; margin: 0; }
.erp-calendar-personal__calendar-list li { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--color-border); }
.erp-calendar-personal__calendar-list li:last-child { border-bottom: 0; }
.is-writable, .is-read-only { font-size: 12px; border-radius: 12px; padding: 2px 8px; }
.is-writable { background: #d4edda; color: #155724; }
.is-read-only { background: var(--color-background-darker, #eee); color: var(--color-text-maxcontrast); }
.erp-calendar-personal__form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.erp-calendar-personal__form label { display: grid; gap: 5px; }
.erp-calendar-personal__form input, .erp-calendar-personal__form select, .erp-calendar-personal__form textarea { width: 100%; box-sizing: border-box; }
.erp-calendar-personal__description, .erp-calendar-personal__form button { grid-column: 1 / -1; }
.erp-calendar-personal__form button { justify-self: start; }
.erp-calendar-personal__hint { color: var(--color-text-maxcontrast); }
.erp-calendar-personal__error { color: var(--color-error-text, #c00); }
.erp-calendar-personal__success { color: var(--color-success-text, #155724); }
@media (max-width: 600px) { .erp-calendar-personal__header { display: block; } .erp-calendar-personal__header button { margin-top: 12px; } .erp-calendar-personal__form { grid-template-columns: 1fr; } }
</style>
