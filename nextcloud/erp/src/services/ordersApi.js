import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchOrders(projectId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/orders', { projectId }))
	return data.ocs.data
}

export async function createOrder(projectId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/orders', { projectId }), payload)
	return data.ocs.data
}

export async function updateOrder(projectId, id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/orders/{id}', { projectId, id }), payload)
	return data.ocs.data
}

// Flache Detailansicht (Positionen + Berechnung) — ADR-0016, analog zu fetchQuote/fetchInvoice.
export async function fetchOrder(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/orders/{id}', { id }))
	return data.ocs.data
}

export async function createOrderFromQuote(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/orders/from-quote'), payload)
	return data.ocs.data
}

export async function addOrderGroup(orderId, title) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/orders/{orderId}/groups', { orderId }), { title })
	return data.ocs.data
}

export async function addOrderPosition(orderId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/orders/{orderId}/positions', { orderId }), payload)
	return data.ocs.data
}

export async function removeOrderPosition(orderId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/orders/{orderId}/positions/{id}', { orderId, id }))
}
