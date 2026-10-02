import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

// Standard-Verrechnungssätze + Kundenverträge (ADR-0012, Gate:
// BerechtigungenSaetze) — Backend existierte bereits, diese Wrapper sind
// neu für das Web-UI (ADR-0037).

export async function fetchStandardRates() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/rates/standard'))
	return data.ocs.data
}

export async function setStandardRate(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/rates/standard'), payload)
	return data.ocs.data
}

export async function resolveRate(params) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/rates/resolve'), { params })
	return data.ocs.data
}

export async function fetchContracts(customerContactUid) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contracts'), { params: { customerContactUid } })
	return data.ocs.data
}

export async function fetchContract(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contracts/{id}', { id }))
	return data.ocs.data
}

export async function createContract(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/contracts'), payload)
	return data.ocs.data
}

export async function addContractRate(contractId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/contracts/{contractId}/rates', { contractId }), payload)
	return data.ocs.data
}

export async function removeContractRate(contractId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/contracts/{contractId}/rates/{id}', { contractId, id }))
}
