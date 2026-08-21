<template>
	<div class="erp-fleet">
		<div class="erp-fleet__header">
			<h2>Fuhrpark</h2>
			<button @click="showCreate = !showCreate">+ Fahrzeug</button>
		</div>

		<form v-if="showCreate" class="erp-fleet__create" @submit.prevent="submitCreate">
			<input v-model="newVehicle.licensePlate" placeholder="Kennzeichen" required>
			<input v-model="newVehicle.brandModel" placeholder="Marke/Modell">
			<select v-model="newVehicle.vehicleType">
				<option value="car">PKW</option>
				<option value="van">Transporter</option>
				<option value="trailer">Anhänger</option>
				<option value="other">Sonstiges</option>
			</select>
			<UserPicker v-model="newVehicle.assignedUserId" placeholder="Fahrer (optional)" />
			<label>TÜV fällig <input v-model="newVehicle.nextInspectionDate" type="date"></label>
			<button type="submit">Anlegen</button>
		</form>

		<p v-if="loadError" class="erp-fleet__error">{{ loadError }}</p>

		<table v-if="vehicles.length" class="erp-fleet__table">
			<thead><tr><th>Kennzeichen</th><th>Marke/Modell</th><th>Typ</th><th>Fahrer</th><th>Kilometerstand</th><th>TÜV</th><th>Status</th></tr></thead>
			<tbody>
				<tr v-for="v in vehicles" :key="v.id" class="erp-fleet__row" @click="open(v.id)">
					<td>{{ v.licensePlate }}</td>
					<td>{{ v.brandModel || '—' }}</td>
					<td>{{ typeLabel(v.vehicleType) }}</td>
					<td>{{ v.assignedUserId || '—' }}</td>
					<td>{{ v.currentMileageKm }} km</td>
					<td :class="inspectionClass(v.nextInspectionDate)">{{ v.nextInspectionDate || '—' }}</td>
					<td><span class="erp-status-badge" :class="`is-${v.status}`">{{ statusLabel(v.status) }}</span></td>
				</tr>
			</tbody>
		</table>
		<p v-else-if="!loadError">Noch keine Fahrzeuge erfasst.</p>
	</div>
</template>

<script>
import { fetchVehicles, createVehicle } from '../services/vehiclesApi.js'
import UserPicker from '../components/UserPicker.vue'

const TYPE_LABELS = { car: 'PKW', van: 'Transporter', trailer: 'Anhänger', other: 'Sonstiges' }
const STATUS_LABELS = { active: 'Aktiv', inactive: 'Inaktiv', sold: 'Verkauft' }

export default {
	name: 'FuhrparkView',
	components: { UserPicker },
	data() {
		return {
			vehicles: [],
			loadError: null,
			showCreate: false,
			newVehicle: { licensePlate: '', brandModel: '', vehicleType: 'car', assignedUserId: null, nextInspectionDate: '' },
		}
	},
	async mounted() {
		await this.load()
	},
	methods: {
		typeLabel(type) {
			return TYPE_LABELS[type] ?? type
		},
		statusLabel(status) {
			return STATUS_LABELS[status] ?? status
		},
		// Fälligkeits-Markierung (rein clientseitig, ADR-0017) — überfällig
		// oder innerhalb der nächsten 30 Tage fällig wird hervorgehoben.
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
		async load() {
			try {
				this.vehicles = await fetchVehicles()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async submitCreate() {
			try {
				await createVehicle({
					...this.newVehicle,
					brandModel: this.newVehicle.brandModel || null,
					nextInspectionDate: this.newVehicle.nextInspectionDate || null,
				})
				this.newVehicle = { licensePlate: '', brandModel: '', vehicleType: 'car', assignedUserId: null, nextInspectionDate: '' }
				this.showCreate = false
				await this.load()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		open(id) {
			this.$router.push({ name: 'vehicle-detail', params: { id } })
		},
	},
}
</script>

<style scoped>
.erp-fleet { padding: 20px; max-width: 960px; }
.erp-fleet__header { display: flex; align-items: center; justify-content: space-between; }
.erp-fleet__create { display: flex; gap: 8px; margin: 12px 0; flex-wrap: wrap; align-items: center; }
.erp-fleet__error { color: var(--color-error-text, #c00); }
.erp-fleet__table { border-collapse: collapse; width: 100%; }
.erp-fleet__table th, .erp-fleet__table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--color-border); }
.erp-fleet__row { cursor: pointer; }
.erp-fleet__row:hover { background: var(--color-background-hover); }
.erp-status-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: var(--color-background-dark); }
.is-overdue { color: var(--color-error-text, #c00); font-weight: bold; }
.is-due-soon { color: #b36b00; font-weight: bold; }
</style>
