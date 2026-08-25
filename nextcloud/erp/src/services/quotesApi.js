import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchQuotes(status, projectId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/quotes'), { params: { status, projectId } })
	return data.ocs.data
}

export async function fetchQuote(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/quotes/{id}', { id }))
	return data.ocs.data
}

export async function createQuote(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/quotes'), payload)
	return data.ocs.data
}

export async function updateQuote(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/quotes/{id}', { id }), payload)
	return data.ocs.data
}

export async function addGroup(quoteId, title) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/quotes/{quoteId}/groups', { quoteId }), { title })
	return data.ocs.data
}

export async function addPosition(quoteId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/quotes/{quoteId}/positions', { quoteId }), payload)
	return data.ocs.data
}

export async function updatePosition(quoteId, id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/quotes/{quoteId}/positions/{id}', { quoteId, id }), payload)
	return data.ocs.data
}

export async function removePosition(quoteId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/quotes/{quoteId}/positions/{id}', { quoteId, id }))
}
