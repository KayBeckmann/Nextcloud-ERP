import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchVehicles(status) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/vehicles'), { params: { status } })
	return data.ocs.data
}

export async function fetchVehicle(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/vehicles/{id}', { id }))
	return data.ocs.data
}

export async function createVehicle(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/vehicles'), payload)
	return data.ocs.data
}

export async function updateVehicle(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/vehicles/{id}', { id }), payload)
	return data.ocs.data
}

export async function addFuelLog(vehicleId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/vehicles/{vehicleId}/fuel-logs', { vehicleId }), payload)
	return data.ocs.data
}

export async function removeFuelLog(vehicleId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/vehicles/{vehicleId}/fuel-logs/{id}', { vehicleId, id }))
}

// content = Base64-String (ohne data:-Präfix), siehe ADR-0017.
export async function uploadFuelReceipt(vehicleId, fuelLogId, fileName, content) {
	const { data } = await axios.post(
		generateOcsUrl('apps/erp/api/v1/vehicles/{vehicleId}/fuel-logs/{fuelLogId}/receipt', { vehicleId, fuelLogId }),
		{ fileName, content },
	)
	return data.ocs.data
}
