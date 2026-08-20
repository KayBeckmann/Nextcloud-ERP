import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchProducts() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/products'))
	return data.ocs.data
}

export async function fetchProduct(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/products/{id}', { id }))
	return data.ocs.data
}

export async function createProduct(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/products'), payload)
	return data.ocs.data
}

export async function updateProduct(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/products/{id}', { id }), payload)
	return data.ocs.data
}

export async function addComponent(productId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/products/{productId}/components', { productId }), payload)
	return data.ocs.data
}

export async function removeComponent(productId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/products/{productId}/components/{id}', { productId, id }))
}

export async function addLabor(productId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/products/{productId}/labor', { productId }), payload)
	return data.ocs.data
}

export async function removeLabor(productId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/products/{productId}/labor/{id}', { productId, id }))
}
