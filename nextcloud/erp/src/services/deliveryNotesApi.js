import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchDeliveryNotes(projectId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/delivery-notes'), { params: { projectId } })
	return data.ocs.data
}

export async function fetchDeliveryNote(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/delivery-notes/{id}', { id }))
	return data.ocs.data
}

export async function createDeliveryNote(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/delivery-notes'), payload)
	return data.ocs.data
}

// Lieferschein aus ausgewählten Auftragspositionen (ADR-0016) — nur Artikel/Produkt.
export async function createDeliveryNoteFromOrder(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/delivery-notes/from-order'), payload)
	return data.ocs.data
}

export async function addDeliveryNoteGroup(deliveryNoteId, title) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/delivery-notes/{deliveryNoteId}/groups', { deliveryNoteId }), { title })
	return data.ocs.data
}

export async function addDeliveryNotePosition(deliveryNoteId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/delivery-notes/{deliveryNoteId}/positions', { deliveryNoteId }), payload)
	return data.ocs.data
}

export async function updateDeliveryNotePosition(deliveryNoteId, id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/delivery-notes/{deliveryNoteId}/positions/{id}', { deliveryNoteId, id }), payload)
	return data.ocs.data
}

export async function removeDeliveryNotePosition(deliveryNoteId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/delivery-notes/{deliveryNoteId}/positions/{id}', { deliveryNoteId, id }))
}

export async function issueDeliveryNote(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/delivery-notes/{id}/issue', { id }))
	return data.ocs.data
}
