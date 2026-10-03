import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function searchContacts(q) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contacts/search'), { params: { q } })
	return data.ocs.data
}

export async function resolveContactName(uid) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contacts/resolve'), { params: { uid } })
	return data.ocs.data
}

export async function fetchContactLinks(role) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contacts/links/{role}', { role }))
	return data.ocs.data
}

export async function fetchContactCards(role) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contacts/cards/{role}', { role }))
	return data.ocs.data
}

export async function createContactCard(role, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/contacts/cards/{role}', { role }), payload)
	return data.ocs.data
}

export async function updateContactCard(role, contactUid, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/contacts/cards/{role}/{contactUid}', { role, contactUid }), payload)
	return data.ocs.data
}

export async function deleteContactCard(role, contactUid) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/contacts/cards/{role}/{contactUid}', { role, contactUid }))
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

export async function fetchContactPersons(contactLinkId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contacts/links/{contactLinkId}/persons', { contactLinkId }))
	return data.ocs.data
}

export async function createContactPerson(contactLinkId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/contacts/links/{contactLinkId}/persons', { contactLinkId }), payload)
	return data.ocs.data
}

export async function updateContactPerson(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/contacts/links/persons/{id}', { id }), payload)
	return data.ocs.data
}

export async function deleteContactPerson(id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/contacts/links/persons/{id}', { id }))
}

export async function fetchContactPersonDefaults(contactLinkId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/contacts/links/{contactLinkId}/person-defaults', { contactLinkId }))
	return data.ocs.data
}

export async function setContactPersonDefault(contactLinkId, documentType, contactPersonId) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/contacts/links/{contactLinkId}/person-defaults/{documentType}', { contactLinkId, documentType }), { contactPersonId })
	return data.ocs.data
}
