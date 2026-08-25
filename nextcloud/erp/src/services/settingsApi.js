import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchCompanyProfile() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/company-profile'))
	return data.ocs.data
}

export async function updateCompanyProfile(payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/company-profile'), payload)
	return data.ocs.data
}

export async function fetchVatRates() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/vat-rates'))
	return data.ocs.data
}

export async function createVatRate(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/vat-rates'), payload)
	return data.ocs.data
}

export async function updateVatRate(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/vat-rates/{id}', { id }), payload)
	return data.ocs.data
}

export async function fetchWorkTypes() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/work-types'))
	return data.ocs.data
}

export async function createWorkType(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/work-types'), payload)
	return data.ocs.data
}

export async function updateWorkType(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/work-types/{id}', { id }), payload)
	return data.ocs.data
}
