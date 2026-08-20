import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchProjects(status) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/projects'), { params: { status } })
	return data.ocs.data
}

export async function fetchProject(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/projects/{id}', { id }))
	return data.ocs.data
}

export async function createProject(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/projects'), payload)
	return data.ocs.data
}

export async function updateProject(id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/projects/{id}', { id }), payload)
	return data.ocs.data
}

export async function fetchTasks(projectId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/tasks', { projectId }))
	return data.ocs.data
}

export async function createTask(projectId, title) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/tasks', { projectId }), { title })
	return data.ocs.data
}

export async function updateTask(projectId, id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/tasks/{id}', { projectId, id }), payload)
	return data.ocs.data
}

export async function deleteTask(projectId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/tasks/{id}', { projectId, id }))
}

export async function fetchOrders(projectId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/orders', { projectId }))
	return data.ocs.data
}

export async function createOrder(projectId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/orders', { projectId }), payload)
	return data.ocs.data
}

export async function updateOrder(projectId, id, payload) {
	const { data } = await axios.put(generateOcsUrl('apps/erp/api/v1/projects/{projectId}/orders/{id}', { projectId, id }), payload)
	return data.ocs.data
}
