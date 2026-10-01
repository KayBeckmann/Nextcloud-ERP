import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchCalendars() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/calendar/calendars'))
	return data.ocs.data
}

export async function createCalendarEvent(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/calendar/events'), payload)
	return data.ocs.data
}

export async function fetchCalendarLinks(resourceType, resourceId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/calendar/links'), {
		params: { resourceType, resourceId },
	})
	return data.ocs.data
}

// Bearbeiten/Verschieben eines bereits angelegten Termins (ADR-0031).
export async function updateCalendarEvent(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/calendar/events/{id}', { id }), payload)
	return data.ocs.data
}

export async function deleteCalendarEvent(id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/calendar/events/{id}', { id }))
}
