<template>
	<div class="erp-time-account">
		<h2>Stunden &amp; Zeitkonto</h2>
		<p v-if="loadError" class="erp-time-account__error">{{ loadError }}</p>

		<nav class="erp-time-account__tabs">
			<button v-for="t in tabs" :key="t" :class="{ 'is-active': tab === t }" @click="tab = t">{{ t }}</button>
		</nav>

		<section v-if="tab === 'Zeiterfassung'" class="erp-time-account__section">
			<form class="erp-time-account__form" @submit.prevent="addTimeEntry">
				<label>Datum <input v-model="newEntry.entryDate" type="date" required></label>
				<label>Arbeitsart
					<select v-model="newEntry.workTypeId" required>
						<option v-for="wt in workTypes" :key="wt.id" :value="wt.id">{{ wt.name }}</option>
					</select>
				</label>
				<label>Dauer (Minuten) <input v-model.number="newEntry.durationMinutes" type="number" min="1" required></label>
				<label>Pause (Minuten) <input v-model.number="newEntry.breakMinutes" type="number" min="0"></label>
				<label class="erp-time-account__checkbox"><input v-model="newEntry.billable" type="checkbox"> Abrechenbar</label>
				<label>Notiz <input v-model="newEntry.notes"></label>
				<button type="submit">Buchen</button>
			</form>

			<table class="erp-time-account__table">
				<thead>
					<tr><th>Datum</th><th>Arbeitsart</th><th>Dauer</th><th>Pause</th><th>Abrechenbar</th><th>Satz</th><th>Notiz</th><th></th></tr>
				</thead>
				<tbody>
					<tr v-for="e in timeEntries" :key="e.id">
						<td>{{ e.entryDate }}</td>
						<td>{{ workTypeName(e.workTypeId) }}</td>
						<td>{{ e.durationMinutes }} min</td>
						<td>{{ e.breakMinutes }} min</td>
						<td>{{ e.billable ? 'ja' : 'nein' }}</td>
						<td>{{ e.rateSnapshot }} €/h</td>
						<td>{{ e.notes }}</td>
						<td><button @click="removeTimeEntry(e)">✕</button></td>
					</tr>
				</tbody>
			</table>
		</section>

		<section v-else-if="tab === 'Zeitkonto'" class="erp-time-account__section">
			<form class="erp-time-account__form" @submit.prevent="loadAccount">
				<label>Von <input v-model="accountRange.fromDate" type="date" required></label>
				<label>Bis <input v-model="accountRange.toDate" type="date" required></label>
				<button type="submit">Berechnen</button>
			</form>
			<div v-if="account" class="erp-time-account__balance">
				<div class="erp-time-account__balance-row"><span>Wochensoll</span><strong>{{ account.weeklyHours }} h</strong></div>
				<div class="erp-time-account__balance-row"><span>Werktage im Zeitraum</span><strong>{{ account.workdays }}</strong></div>
				<div class="erp-time-account__balance-row"><span>Soll-Stunden</span><strong>{{ account.sollHours }} h</strong></div>
				<div class="erp-time-account__balance-row"><span>Ist-Stunden</span><strong>{{ account.istHours }} h</strong></div>
				<div class="erp-time-account__balance-row">
					<span>Saldo</span>
					<strong :class="{ 'is-negative': account.balanceHours < 0, 'is-positive': account.balanceHours > 0 }">{{ account.balanceHours }} h</strong>
				</div>
			</div>
		</section>

		<section v-else-if="tab === 'Urlaub & Abwesenheit'" class="erp-time-account__section">
			<form class="erp-time-account__form" @submit.prevent="addAbsenceRequest">
				<label>Typ
					<select v-model="newAbsence.absenceTypeId" required>
						<option v-for="at in absenceTypes" :key="at.id" :value="at.id">{{ at.name }}</option>
					</select>
				</label>
				<label>Von <input v-model="newAbsence.startDate" type="date" required></label>
				<label>Bis <input v-model="newAbsence.endDate" type="date" required></label>
				<label>Notiz <input v-model="newAbsence.notes"></label>
				<button type="submit">Beantragen</button>
			</form>

			<h3>Eigene Anträge</h3>
			<table class="erp-time-account__table">
				<thead><tr><th>Typ</th><th>Von</th><th>Bis</th><th>Status</th><th>Notiz</th></tr></thead>
				<tbody>
					<tr v-for="r in absenceRequests" :key="r.id">
						<td>{{ absenceTypeName(r.absenceTypeId) }}</td>
						<td>{{ r.startDate }}</td>
						<td>{{ r.endDate }}</td>
						<td><span class="erp-status-badge" :class="`is-${r.status}`">{{ r.status }}</span></td>
						<td>{{ r.notes }}</td>
					</tr>
				</tbody>
			</table>

			<template v-if="pendingAbsenceRequests !== null">
				<h3>Offene Anträge zur Freigabe</h3>
				<table class="erp-time-account__table">
					<thead><tr><th>User</th><th>Typ</th><th>Von</th><th>Bis</th><th></th></tr></thead>
					<tbody>
						<tr v-for="r in pendingAbsenceRequests" :key="r.id">
							<td>{{ r.userId }}</td>
							<td>{{ absenceTypeName(r.absenceTypeId) }}</td>
							<td>{{ r.startDate }}</td>
							<td>{{ r.endDate }}</td>
							<td>
								<button @click="approveAbsence(r)">Genehmigen</button>
								<button @click="rejectAbsence(r)">Ablehnen</button>
							</td>
						</tr>
					</tbody>
				</table>
			</template>
		</section>

		<section v-else-if="tab === 'Überstunden'" class="erp-time-account__section">
			<form class="erp-time-account__form" @submit.prevent="addOvertimeAction">
				<label>Stunden <input v-model.number="newOvertime.hours" type="number" step="0.25" min="0.25" required></label>
				<label>Art
					<select v-model="newOvertime.actionType" required>
						<option value="compensate">Abbummeln</option>
						<option value="payout">Auszahlung</option>
					</select>
				</label>
				<label>Notiz <input v-model="newOvertime.notes"></label>
				<button type="submit">Beantragen</button>
			</form>

			<h3>Eigene Anträge</h3>
			<table class="erp-time-account__table">
				<thead><tr><th>Stunden</th><th>Art</th><th>Status</th><th>Notiz</th></tr></thead>
				<tbody>
					<tr v-for="a in overtimeActions" :key="a.id">
						<td>{{ a.hours }} h</td>
						<td>{{ a.actionType === 'compensate' ? 'Abbummeln' : 'Auszahlung' }}</td>
						<td><span class="erp-status-badge" :class="`is-${a.status}`">{{ a.status }}</span></td>
						<td>{{ a.notes }}</td>
					</tr>
				</tbody>
			</table>

			<template v-if="pendingOvertimeActions !== null">
				<h3>Offene Anträge zur Freigabe</h3>
				<table class="erp-time-account__table">
					<thead><tr><th>User</th><th>Stunden</th><th>Art</th><th></th></tr></thead>
					<tbody>
						<tr v-for="a in pendingOvertimeActions" :key="a.id">
							<td>{{ a.userId }}</td>
							<td>{{ a.hours }} h</td>
							<td>{{ a.actionType === 'compensate' ? 'Abbummeln' : 'Auszahlung' }}</td>
							<td>
								<button @click="approveOvertime(a)">Genehmigen</button>
								<button @click="rejectOvertime(a)">Ablehnen</button>
							</td>
						</tr>
					</tbody>
				</table>
			</template>
		</section>
	</div>
</template>

<script>
import {
	fetchTimeEntries, createTimeEntry, deleteTimeEntry,
	fetchTimeAccount,
	fetchAbsenceTypes, fetchAbsenceRequests, createAbsenceRequest, approveAbsenceRequest, rejectAbsenceRequest,
	fetchOvertimeActions, createOvertimeAction, approveOvertimeAction, rejectOvertimeAction,
} from '../services/timeAccountApi.js'
import { fetchWorkTypes } from '../services/settingsApi.js'

function today() {
	return new Date().toISOString().slice(0, 10)
}

function startOfWeek() {
	const d = new Date()
	const day = (d.getDay() + 6) % 7 // 0 = Montag
	d.setDate(d.getDate() - day)
	return d.toISOString().slice(0, 10)
}

export default {
	name: 'StundenZeitkontoView',
	data() {
		return {
			tab: 'Zeiterfassung',
			tabs: ['Zeiterfassung', 'Zeitkonto', 'Urlaub & Abwesenheit', 'Überstunden'],
			loadError: null,
			workTypes: [],
			timeEntries: [],
			newEntry: { entryDate: today(), workTypeId: null, durationMinutes: 60, breakMinutes: 0, billable: true, notes: '' },
			account: null,
			accountRange: { fromDate: startOfWeek(), toDate: today() },
			absenceTypes: [],
			absenceRequests: [],
			pendingAbsenceRequests: null,
			newAbsence: { absenceTypeId: null, startDate: today(), endDate: today(), notes: '' },
			overtimeActions: [],
			pendingOvertimeActions: null,
			newOvertime: { hours: 1, actionType: 'compensate', notes: '' },
		}
	},
	async mounted() {
		await this.loadAll()
	},
	methods: {
		errorMessage(e) {
			return e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
		},
		workTypeName(id) {
			return this.workTypes.find((w) => w.id === id)?.name ?? id
		},
		absenceTypeName(id) {
			return this.absenceTypes.find((a) => a.id === id)?.name ?? id
		},
		async loadAll() {
			try {
				this.workTypes = await fetchWorkTypes()
				if (this.workTypes.length > 0) {
					this.newEntry.workTypeId = this.workTypes[0].id
				}
				this.timeEntries = await fetchTimeEntries()
				this.absenceTypes = await fetchAbsenceTypes()
				if (this.absenceTypes.length > 0) {
					this.newAbsence.absenceTypeId = this.absenceTypes[0].id
				}
				this.absenceRequests = await fetchAbsenceRequests()
				this.overtimeActions = await fetchOvertimeActions()
				await this.loadAccount()
				// Freigabe-Listen sind optional (erfordern Approve-Recht) —
				// ein 403 blendet den Abschnitt einfach aus statt die Seite
				// abzubrechen.
				this.pendingAbsenceRequests = await fetchAbsenceRequests({ status: 'requested' }).catch(() => null)
				this.pendingOvertimeActions = await fetchOvertimeActions({ status: 'requested' }).catch(() => null)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async loadAccount() {
			try {
				this.account = await fetchTimeAccount(this.accountRange.fromDate, this.accountRange.toDate)
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async addTimeEntry() {
			try {
				await createTimeEntry({ ...this.newEntry, notes: this.newEntry.notes || null })
				this.newEntry.notes = ''
				this.timeEntries = await fetchTimeEntries()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async removeTimeEntry(entry) {
			await deleteTimeEntry(entry.id)
			this.timeEntries = this.timeEntries.filter((e) => e.id !== entry.id)
		},
		async addAbsenceRequest() {
			try {
				await createAbsenceRequest({ ...this.newAbsence, notes: this.newAbsence.notes || null })
				this.newAbsence.notes = ''
				this.absenceRequests = await fetchAbsenceRequests()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async approveAbsence(request) {
			await approveAbsenceRequest(request.id)
			this.pendingAbsenceRequests = await fetchAbsenceRequests({ status: 'requested' }).catch(() => null)
		},
		async rejectAbsence(request) {
			await rejectAbsenceRequest(request.id)
			this.pendingAbsenceRequests = await fetchAbsenceRequests({ status: 'requested' }).catch(() => null)
		},
		async addOvertimeAction() {
			try {
				await createOvertimeAction({ ...this.newOvertime, notes: this.newOvertime.notes || null })
				this.newOvertime.notes = ''
				this.overtimeActions = await fetchOvertimeActions()
			} catch (e) {
				this.loadError = this.errorMessage(e)
			}
		},
		async approveOvertime(action) {
			await approveOvertimeAction(action.id)
			this.pendingOvertimeActions = await fetchOvertimeActions({ status: 'requested' }).catch(() => null)
		},
		async rejectOvertime(action) {
			await rejectOvertimeAction(action.id)
			this.pendingOvertimeActions = await fetchOvertimeActions({ status: 'requested' }).catch(() => null)
		},
	},
}
</script>

<style scoped>
.erp-time-account {
	padding: 20px;
	max-width: 900px;
}
.erp-time-account__error {
	color: var(--color-error-text, #c00);
}
.erp-time-account__tabs {
	display: flex;
	gap: 4px;
	margin: 16px 0;
	border-bottom: 1px solid var(--color-border);
}
.erp-time-account__tabs button {
	background: none;
	border: none;
	padding: 8px 12px;
	cursor: pointer;
}
.erp-time-account__tabs button.is-active {
	border-bottom: 2px solid var(--color-primary-element);
	font-weight: bold;
}
.erp-time-account__form {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	align-items: flex-end;
	margin-bottom: 16px;
}
.erp-time-account__form label {
	display: flex;
	flex-direction: column;
	font-size: 12px;
}
.erp-time-account__checkbox {
	flex-direction: row !important;
	align-items: center;
	gap: 4px;
}
.erp-time-account__table {
	width: 100%;
	border-collapse: collapse;
}
.erp-time-account__table th,
.erp-time-account__table td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}
.erp-time-account__balance {
	display: flex;
	flex-direction: column;
	gap: 4px;
}
.erp-time-account__balance-row {
	display: flex;
	gap: 16px;
	min-width: 260px;
}
.erp-time-account__balance-row span {
	flex: 1 0 auto;
	color: var(--color-text-maxcontrast);
}
.erp-time-account__balance-row strong {
	min-width: 60px;
	text-align: right;
}
.erp-time-account__balance .is-negative {
	color: var(--color-error-text, #c00);
}
.erp-time-account__balance .is-positive {
	color: var(--color-success-text, #2d7d46);
}
.erp-status-badge {
	font-size: 11px;
	padding: 2px 8px;
	border-radius: 10px;
	background: var(--color-background-dark);
}
</style>
