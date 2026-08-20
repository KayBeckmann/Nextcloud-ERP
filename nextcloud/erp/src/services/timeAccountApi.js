import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

// Zeiterfassung (userId weggelassen = eigene Einträge, Backend defaultet auf den Session-User).
export async function fetchTimeEntries(params = {}) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/time-entries'), { params })
	return data.ocs.data
}

export async function createTimeEntry(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/time-entries'), payload)
	return data.ocs.data
}

export async function deleteTimeEntry(id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/time-entries/{id}', { id }))
}

// Zeitkonto + Arbeitszeitmodell.
export async function fetchTimeAccount(fromDate, toDate, userId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/time-account'), { params: { fromDate, toDate, userId } })
	return data.ocs.data
}

export async function fetchWorkSchedule(userId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/work-schedule'), { params: { userId } })
	return data.ocs.data
}

// Abwesenheiten.
export async function fetchAbsenceTypes() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/absence-types'))
	return data.ocs.data
}

export async function createAbsenceType(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/absence-types'), payload)
	return data.ocs.data
}

export async function fetchAbsenceRequests(params = {}) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/absence-requests'), { params })
	return data.ocs.data
}

export async function createAbsenceRequest(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/absence-requests'), payload)
	return data.ocs.data
}

export async function approveAbsenceRequest(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/absence-requests/{id}/approve', { id }))
	return data.ocs.data
}

export async function rejectAbsenceRequest(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/absence-requests/{id}/reject', { id }))
	return data.ocs.data
}

// Überstunden.
export async function fetchOvertimeActions(params = {}) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/overtime-actions'), { params })
	return data.ocs.data
}

export async function createOvertimeAction(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/overtime-actions'), payload)
	return data.ocs.data
}

export async function approveOvertimeAction(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/overtime-actions/{id}/approve', { id }))
	return data.ocs.data
}

export async function completeOvertimeAction(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/overtime-actions/{id}/complete', { id }))
	return data.ocs.data
}

export async function rejectOvertimeAction(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/overtime-actions/{id}/reject', { id }))
	return data.ocs.data
}
