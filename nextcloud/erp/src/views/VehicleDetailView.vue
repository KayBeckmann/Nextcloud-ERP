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

			<section class="erp-vehicle-detail__trips">
				<h3>Fahrtenbuch</h3>
				<table v-if="vehicle.trips.length" class="erp-vehicle-detail__table">
					<thead><tr><th>Datum</th><th>Zweck</th><th>Von</th><th>Nach</th><th>km</th><th>Distanz</th><th>Fahrer</th><th></th></tr></thead>
					<tbody>
						<tr v-for="trip in vehicle.trips" :key="trip.id">
							<td>{{ trip.tripDate }}</td>
							<td>{{ trip.purpose === 'business' ? 'Dienstlich' : 'Privat' }}</td>
							<td>{{ trip.startLocation }}</td>
							<td>{{ trip.destination }}</td>
							<td>{{ trip.startMileageKm }} → {{ trip.endMileageKm }}</td>
							<td>{{ trip.distanceKm }} km</td>
							<td>{{ trip.driverUserId ?? '—' }}</td>
							<td><button @click="removeTripEntry(trip.id)">✕</button></td>
						</tr>
					</tbody>
				</table>
				<p v-else>Noch keine Fahrten erfasst.</p>

				<form class="erp-vehicle-detail__fuel-form" @submit.prevent="submitTrip">
					<input v-model="newTrip.tripDate" type="date" required>
					<select v-model="newTrip.purpose">
						<option value="business">Dienstlich</option>
						<option value="private">Privat</option>
					</select>
					<input v-model="newTrip.startLocation" placeholder="Von" required>
					<input v-model="newTrip.destination" placeholder="Nach" required>
					<input v-model.number="newTrip.startMileageKm" type="number" placeholder="km Start" required>
					<input v-model.number="newTrip.endMileageKm" type="number" placeholder="km Ende" required>
					<UserPicker v-model="newTrip.driverUserId" placeholder="Fahrer (optional)" />
					<input v-model="newTrip.notes" placeholder="Notiz (optional)">
					<button type="submit">Fahrt erfassen</button>
				</form>
			</section>

			<section class="erp-vehicle-detail__fuel-stats">
				<h3>Kraftstoffverbrauch</h3>
				<p v-if="vehicle.fuelConsumption.averageL100km !== null">
					Ø {{ vehicle.fuelConsumption.averageL100km.toFixed(1) }} l/100 km
					<small>(unterstellt Volltanken bei jedem Beleg, siehe ADR-0028)</small>
				</p>
				<table v-if="vehicle.fuelConsumption.entries.length" class="erp-vehicle-detail__table">
					<thead><tr><th>Datum</th><th>Liter</th><th>Distanz</th><th>Verbrauch</th></tr></thead>
					<tbody>
						<tr v-for="entry in vehicle.fuelConsumption.entries" :key="entry.id">
							<td>{{ entry.entryDate }}</td>
							<td>{{ entry.liters }} l</td>
							<td>{{ entry.distanceKm !== null ? `${entry.distanceKm} km` : '—' }}</td>
							<td>{{ entry.consumptionL100km !== null ? `${entry.consumptionL100km.toFixed(1)} l/100km` : '—' }}</td>
						</tr>
					</tbody>
				</table>
				<p v-else>Noch keine Verbrauchsdaten — mindestens zwei Tankbelege nötig.</p>
			</section>

			<section class="erp-vehicle-detail__assignments">
				<h3>Fahrer-Zuweisungs-Historie</h3>
				<table v-if="vehicle.assignmentHistory.length" class="erp-vehicle-detail__table">
					<thead><tr><th>Fahrer</th><th>Von</th><th>Bis</th></tr></thead>
					<tbody>
						<tr v-for="a in vehicle.assignmentHistory" :key="a.id">
							<td>{{ a.userId }}</td>
							<td>{{ formatTimestamp(a.assignedAt) }}</td>
							<td>{{ a.unassignedAt ? formatTimestamp(a.unassignedAt) : 'aktuell zugewiesen' }}</td>
						</tr>
					</tbody>
				</table>
				<p v-else>Noch keine Zuweisungs-Historie.</p>
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
import { fetchVehicle, updateVehicle, addFuelLog, removeFuelLog, uploadFuelReceipt, addTrip, removeTrip } from '../services/vehiclesApi.js'
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
			newTrip: { tripDate: '', purpose: 'business', startLocation: '', destination: '', startMileageKm: 0, endMileageKm: 0, driverUserId: null, notes: '' },
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
		formatTimestamp(unixSeconds) {
			return new Date(unixSeconds * 1000).toLocaleString('de-DE')
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
		async submitTrip() {
			try {
				await addTrip(this.id, { ...this.newTrip, notes: this.newTrip.notes || null })
				this.newTrip = { tripDate: '', purpose: 'business', startLocation: '', destination: '', startMileageKm: 0, endMileageKm: 0, driverUserId: null, notes: '' }
				await this.load()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async removeTripEntry(tripId) {
			await removeTrip(this.id, tripId)
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
