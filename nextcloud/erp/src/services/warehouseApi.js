import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchWarehouses() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/warehouses'))
	return data.ocs.data
}

export async function createWarehouse(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/warehouses'), payload)
	return data.ocs.data
}

export async function updateWarehouse(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/warehouses/{id}', { id }), payload)
	return data.ocs.data
}

export async function fetchStock(warehouseId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/stock'), { params: { warehouseId } })
	return data.ocs.data
}

export async function fetchStockMovements(articleId, warehouseId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/stock/movements'), { params: { articleId, warehouseId } })
	return data.ocs.data
}

export async function setMinQuantity(articleId, warehouseId, minQuantity) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/stock/min-quantity'), { articleId, warehouseId, minQuantity })
	return data.ocs.data
}

export async function recordMovement(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/stock/movements'), payload)
	return data.ocs.data
}

export async function transferStock(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/stock/transfer'), payload)
	return data.ocs.data
}

export async function reserveStock(articleId, warehouseId, quantity) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/stock/reserve'), { articleId, warehouseId, quantity })
	return data.ocs.data
}

export async function releaseStock(articleId, warehouseId, quantity) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/stock/release'), { articleId, warehouseId, quantity })
	return data.ocs.data
}

export async function fetchPurchaseSuggestions(warehouseId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/stock/purchase-suggestions'), { params: { warehouseId } })
	return data.ocs.data
}

export async function fetchInventories(warehouseId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/inventories'), { params: { warehouseId } })
	return data.ocs.data
}

export async function fetchInventory(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/inventories/{id}', { id }))
	return data.ocs.data
}

export async function startInventory(warehouseId, notes) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/inventories'), { warehouseId, notes })
	return data.ocs.data
}

export async function recordInventoryCount(inventoryId, articleId, countedQuantity) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/inventories/{inventoryId}/counts', { inventoryId }), { articleId, countedQuantity })
	return data.ocs.data
}

export async function closeInventory(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/inventories/{id}/close', { id }))
	return data.ocs.data
}
