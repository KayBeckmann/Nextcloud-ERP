import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

// Dünne Wrapper um die Rechte-API (Roadmap Phase 2). @nextcloud/axios setzt
// die nötigen OCS-Header/Credentials automatisch.

export async function fetchPrincipals() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/permissions/principals'))
	return data.ocs.data
}

export async function fetchMatrix() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/permissions/matrix'))
	return data.ocs.data
}

export async function setMatrixEntry({ principalType, principalId, resourceType, permission }) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/permissions/matrix'), {
		principalType,
		principalId,
		resourceType,
		permission,
	})
	return data.ocs.data
}

export async function fetchMyPermissions() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/permissions/me'))
	return data.ocs.data
}
