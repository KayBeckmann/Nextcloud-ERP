<template>
	<div class="erp-vehicle-detail">
		<p v-if="loadError" class="erp-vehicle-detail__error">{{ loadError }}</p>
		<template v-else-if="vehicle">
			<header>
				<h2>{{ vehicle.licensePlate }} <span v-if="vehicle.brandModel">— {{ vehicle.brandModel }}</span></h2>
				<span class="erp-status-badge" :class="`is-${vehicle.status}`">{{ statusLabel(vehicle.status) }}</span>
			</header>

			<section class="erp-vehicle-detail__meta">
				<label>Kennzeichen <input v-model="edit.licensePlate"></label>
				<label>Marke/Modell <input v-model="edit.brandModel"></label>
				<label>Typ
					<select v-model="edit.vehicleType">
						<option value="car">PKW</option>
						<option value="van">Transporter</option>
						<option value="trailer">Anhänger</option>
						<option value="other">Sonstiges</option>
					</select>
				</label>
				<label>Status
					<select v-model="edit.status">
						<option value="active">Aktiv</option>
						<option value="inactive">Inaktiv</option>
						<option value="sold">Verkauft</option>
					</select>
				</label>
				<label>Fahrer <UserPicker v-model="edit.assignedUserId" placeholder="Fahrer suchen …" /></label>
				<label>Kilometerstand <input :value="`${vehicle.currentMileageKm} km (aus Tankbelegen fortgeschrieben)`" disabled></label>
				<label>TÜV fällig
					<input v-model="edit.nextInspectionDate" type="date" :class="inspectionClass(vehicle.nextInspectionDate)">
				</label>
				<label>Notizen <textarea v-model="edit.notes" rows="2"></textarea></label>
				<button @click="save">Speichern</button>
			</section>

			<section class="erp-vehicle-detail__appointment">
				<h3>Termin</h3>
				<button @click="toggleAppointmentForm">TÜV-/Werkstatttermin anlegen</button>
				<form v-if="showAppointmentForm" class="erp-vehicle-detail__appointment-form" @submit.prevent="submitAppointment">
					<select v-model="appointment.calendarUri" required>
						<option :value="null">Kalender wählen</option>
						<option v-for="c in calendars" :key="c.uri" :value="c.uri">{{ c.displayName }}</option>
					</select>
					<input v-model="appointment.summary" placeholder="Titel" required>
					<input v-model="appointment.start" type="datetime-local" required>
					<input v-model="appointment.end" type="datetime-local" required>
					<button type="submit">Anlegen</button>
				</form>
				<ul v-if="calendarLinks.length" class="erp-vehicle-detail__events">
					<li v-for="l in calendarLinks" :key="l.id">{{ l.summary }} <small>({{ l.calendarUri }})</small></li>
				</ul>
			</section>

			<section class="erp-vehicle-detail__fuel">
				<h3>Tankbelege</h3>
				<table v-if="vehicle.fuelLogs.length" class="erp-vehicle-detail__table">
					<thead><tr><th>Datum</th><th>Liter</th><th>Betrag</th><th>Kilometerstand</th><th>Beleg</th><th></th></tr></thead>
					<tbody>
						<tr v-for="log in vehicle.fuelLogs" :key="log.id">
							<td>{{ log.entryDate }}</td>
							<td>{{ log.liters }} l</td>
							<td>{{ formatMoney(log.amount) }}</td>
							<td>{{ log.mileageKm }} km</td>
							<td>
								<a v-if="log.receiptFileId" :href="openInFilesUrl(log.receiptFileId)" target="_blank" rel="noopener">Foto öffnen</a>
								<label v-else class="erp-vehicle-detail__upload">
									Foto hochladen
									<input type="file" accept="image/*" @change="uploadReceipt(log.id, $event)">
								</label>
							</td>
							<td><button @click="removeLog(log.id)">✕</button></td>
						</tr>
					</tbody>
				</table>
				<p v-else>Noch keine Tankbelege erfasst.</p>

				<form class="erp-vehicle-detail__fuel-form" @submit.prevent="submitFuelLog">
					<input v-model="newFuelLog.entryDate" type="date" required>
					<input v-model.number="newFuelLog.liters" type="number" step="0.01" placeholder="Liter" required>
					<input v-model.number="newFuelLog.amount" type="number" step="0.01" placeholder="Betrag" required>
					<input v-model.number="newFuelLog.mileageKm" type="number" placeholder="Kilometerstand" required>
					<input v-model="newFuelLog.notes" placeholder="Notiz (optional)">
					<button type="submit">Tankbeleg erfassen</button>
				</form>
			</section>

			<section v-if="vehicle.warehouses.length" class="erp-vehicle-detail__warehouse">
				<h3>Fahrzeuglager</h3>
				<div v-for="w in vehicle.warehouses" :key="w.id">
					<h4>{{ w.name }}</h4>
					<table v-if="stockByWarehouse[w.id]?.length" class="erp-vehicle-detail__table">
						<thead><tr><th>Artikel-ID</th><th>Ist</th><th>Reserviert</th><th>Mindestbestand</th></tr></thead>
						<tbody>
							<tr v-for="s in stockByWarehouse[w.id]" :key="s.id">
								<td>{{ s.articleId }}</td>
								<td>{{ s.quantityOnHand }}</td>
								<td>{{ s.quantityReserved }}</td>
								<td>{{ s.minQuantity }}</td>
							</tr>
						</tbody>
					</table>
					<p v-else>Kein Bestand gebucht.</p>
				</div>
			</section>
		</template>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { fetchVehicle, updateVehicle, addFuelLog, removeFuelLog, uploadFuelReceipt } from '../services/vehiclesApi.js'
import { fetchCalendars, createCalendarEvent, fetchCalendarLinks } from '../services/calendarApi.js'
import { fetchStock } from '../services/warehouseApi.js'
import UserPicker from '../components/UserPicker.vue'

const STATUS_LABELS = { active: 'Aktiv', inactive: 'Inaktiv', sold: 'Verkauft' }

export default {
	name: 'VehicleDetailView',
	components: { UserPicker },
	props: {
		id: { type: [String, Number], required: true },
	},
	data() {
		return {
			vehicle: null,
			loadError: null,
			edit: { licensePlate: '', brandModel: '', vehicleType: 'car', status: 'active', assignedUserId: null, nextInspectionDate: '', notes: '' },
			newFuelLog: { entryDate: '', liters: 0, amount: 0, mileageKm: 0, notes: '' },
			calendars: [],
			calendarLinks: [],
			showAppointmentForm: false,
			appointment: { calendarUri: null, summary: '', start: '', end: '' },
			stockByWarehouse: {},
		}
	},
	async mounted() {
		await this.load()
		await this.loadCalendarLinks()
	},
	methods: {
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		formatMoney(value) {
			return `${Number(value).toFixed(2)} €`
		},
		openInFilesUrl(fileId) {
			return generateUrl(`/f/${fileId}`)
		},
		inspectionClass(date) {
			if (!date) {
				return ''
			}
			const days = (new Date(date) - new Date()) / (1000 * 60 * 60 * 24)
			if (days < 0) {
				return 'is-overdue'
			}
			if (days <= 30) {
				return 'is-due-soon'
			}
			return ''
		},
		errorMessage(e) {
			return e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		},
		async load() {
			try {
				this.vehicle = await fetchVehicle(this.id)
				this.edit = {
					licensePlate: this.vehicle.licensePlate,
					brandModel: this.vehicle.brandModel ?? '',
					vehicleType: this.vehicle.vehicleType,
					status: this.vehicle.status,
					assignedUserId: this.vehicle.assignedUserId ?? null,
					nextInspectionDate: this.vehicle.nextInspectionDate ?? '',
					notes: this.vehicle.notes ?? '',
				}
				for (const w of this.vehicle.warehouses) {
					this.stockByWarehouse[w.id] = await fetchStock(w.id)
				}
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async loadCalendarLinks() {
			try {
				this.calendarLinks = await fetchCalendarLinks('fuhrpark', String(this.id))
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async save() {
			try {
				await updateVehicle(this.id, {
					...this.edit,
					brandModel: this.edit.brandModel || null,
					nextInspectionDate: this.edit.nextInspectionDate || null,
					notes: this.edit.notes || null,
				})
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async toggleAppointmentForm() {
			this.showAppointmentForm = !this.showAppointmentForm
			if (this.showAppointmentForm) {
				if (!this.calendars.length) {
					this.calendars = await fetchCalendars()
				}
				const writable = this.calendars.find((c) => c.writable)
				const date = this.vehicle.nextInspectionDate || new Date().toISOString().slice(0, 10)
				this.appointment = {
					calendarUri: writable?.uri ?? null,
					summary: `TÜV ${this.vehicle.licensePlate}`,
					start: `${date}T09:00`,
					end: `${date}T10:00`,
				}
			}
		},
		async submitAppointment() {
			try {
				await createCalendarEvent({
					calendarUri: this.appointment.calendarUri,
					resourceType: 'fuhrpark',
					resourceId: String(this.id),
					summary: this.appointment.summary,
					start: this.appointment.start,
					end: this.appointment.end,
				})
				this.showAppointmentForm = false
				await this.loadCalendarLinks()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async submitFuelLog() {
			try {
				await addFuelLog(this.id, { ...this.newFuelLog, notes: this.newFuelLog.notes || null })
				this.newFuelLog = { entryDate: '', liters: 0, amount: 0, mileageKm: 0, notes: '' }
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async removeLog(logId) {
			await removeFuelLog(this.id, logId)
			await this.load()
		},
		async uploadReceipt(logId, event) {
			const file = event.target.files[0]
			if (!file) {
				return
			}
			try {
				const base64 = await this.readFileAsBase64(file)
				await uploadFuelReceipt(this.id, logId, file.name, base64)
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		readFileAsBase64(file) {
			return new Promise((resolve, reject) => {
				const reader = new FileReader()
				reader.onload = () => resolve(reader.result.split(',')[1])
				reader.onerror = reject
				reader.readAsDataURL(file)
			})
		},
	},
}
</script>

<style scoped>
.erp-vehicle-detail { padding: 20px 20px 80px; max-width: 960px; }
.erp-vehicle-detail__error { color: var(--color-error-text, #c00); }
header { display: flex; align-items: center; gap: 12px; }
.erp-vehicle-detail__meta { margin: 16px 0; padding: 12px; background: var(--color-background-dark); }
.erp-vehicle-detail__meta label { display: block; margin-bottom: 8px; }
.erp-vehicle-detail__meta input, .erp-vehicle-detail__meta select, .erp-vehicle-detail__meta textarea { width: 100%; max-width: 400px; }
.erp-vehicle-detail__appointment, .erp-vehicle-detail__fuel, .erp-vehicle-detail__warehouse { margin-top: 20px; }
.erp-vehicle-detail__appointment-form, .erp-vehicle-detail__fuel-form { display: flex; gap: 8px; margin: 10px 0; flex-wrap: wrap; align-items: center; }
.erp-vehicle-detail__events { list-style: none; padding: 0; }
.erp-vehicle-detail__table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.erp-vehicle-detail__table th, .erp-vehicle-detail__table td { text-align: left; padding: 4px 6px; border-bottom: 1px solid var(--color-border); font-size: 13px; }
.erp-vehicle-detail__upload { cursor: pointer; color: var(--color-primary-element); font-size: 12px; }
.erp-vehicle-detail__upload input { display: none; }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
.is-overdue { border-color: var(--color-error, #c00); }
.is-due-soon { border-color: #b36b00; }
</style>
