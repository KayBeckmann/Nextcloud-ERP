import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchArticles() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/articles'))
	return data.ocs.data
}

export async function fetchArticle(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/articles/{id}', { id }))
	return data.ocs.data
}

export async function createArticle(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/articles'), payload)
	return data.ocs.data
}

export async function updateArticle(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/articles/{id}', { id }), payload)
	return data.ocs.data
}

export async function addSupplierPrice(articleId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/articles/{articleId}/supplier-prices', { articleId }), payload)
	return data.ocs.data
}

export async function removeSupplierPrice(articleId, priceId) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/articles/{articleId}/supplier-prices/{priceId}', { articleId, priceId }))
}
