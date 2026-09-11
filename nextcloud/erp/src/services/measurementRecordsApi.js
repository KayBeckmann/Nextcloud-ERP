import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
const url = (path, values = {}) => generateOcsUrl(`apps/erp/api/v1/${path}`, values)
const unwrap = ({ data }) => data.ocs.data
export const fetchMeasurementRecords = (projectId) => axios.get(url('projects/{projectId}/measurement-records', { projectId })).then(unwrap)
export const createMeasurementRecord = (projectId, title) => axios.post(url('projects/{projectId}/measurement-records', { projectId }), { title }).then(unwrap)
export const fetchMeasurementRecord = (id) => axios.get(url('measurement-records/{id}', { id })).then(unwrap)
export const createMeasurementBlock = (id, type, payload) => axios.post(url('measurement-records/{id}/blocks', { id }), { type, payload }).then(unwrap)
export const updateMeasurementBlock = (recordId, id, payload) => axios.put(url('measurement-records/{recordId}/blocks/{id}', { recordId, id }), { payload }).then(unwrap)
export const deleteMeasurementBlock = (recordId, id) => axios.delete(url('measurement-records/{recordId}/blocks/{id}', { recordId, id })).then(unwrap)
export const reorderMeasurementBlocks = (id, blockIds) => axios.put(url('measurement-records/{id}/blocks/order', { id }), { blockIds }).then(unwrap)
export const uploadMeasurementAsset = (projectId, content) => axios.post(url('projects/{projectId}/measurement-assets', { projectId }), { content }).then(unwrap)
