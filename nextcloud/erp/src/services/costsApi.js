import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchYearOverview(year) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/costs/overview'), { params: { year } })
	return data.ocs.data
}

export async function createCostEntry(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/costs/entries'), payload)
	return data.ocs.data
}

export async function updateCostEntry(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/costs/entries/{id}', { id }), payload)
	return data.ocs.data
}

export async function removeCostEntry(id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/costs/entries/{id}', { id }))
}

export async function updateCostSettings(payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/costs/settings'), payload)
	return data.ocs.data
}
