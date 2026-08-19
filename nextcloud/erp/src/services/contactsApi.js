import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function searchContacts(q) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contacts/search'), { params: { q } })
	return data.ocs.data
}

export async function fetchContactLinks(role) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contacts/links/{role}', { role }))
	return data.ocs.data
}

export async function createContactLink(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/contacts/links'), payload)
	return data.ocs.data
}

export async function updateContactLink(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/contacts/links/{id}', { id }), payload)
	return data.ocs.data
}

export async function deleteContactLink(id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/contacts/links/{id}', { id }))
}
