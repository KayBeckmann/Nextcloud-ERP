import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchPurchaseOrders() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/purchase-orders'))
	return data.ocs.data
}

export async function fetchPurchaseOrder(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/purchase-orders/{id}', { id }))
	return data.ocs.data
}

export async function createPurchaseOrder(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/purchase-orders'), payload)
	return data.ocs.data
}

export async function transitionPurchaseOrder(id, status, notes = null) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/purchase-orders/{id}/status', { id }), { status, notes })
	return data.ocs.data
}

export async function receivePurchaseOrderPosition(positionId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/purchase-order-positions/{positionId}/receipts', { positionId }), payload)
	return data.ocs.data
}
