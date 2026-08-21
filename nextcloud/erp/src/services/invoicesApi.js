import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchInvoices(status, projectId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/invoices'), { params: { status, projectId } })
	return data.ocs.data
}

export async function fetchInvoice(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/invoices/{id}', { id }))
	return data.ocs.data
}

export async function createInvoice(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/invoices'), payload)
	return data.ocs.data
}

export async function createInvoiceFromQuote(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/invoices/from-quote'), payload)
	return data.ocs.data
}

// Rechnung aus ausgewählten Auftragspositionen (ADR-0016) — mit type:'partial'
// und einer Teilauswahl entsteht eine Teilrechnung.
export async function createInvoiceFromOrder(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/invoices/from-order'), payload)
	return data.ocs.data
}

// Rechnung aus ausgewählten Lieferscheinpositionen (ADR-0016).
export async function createInvoiceFromDeliveryNote(payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/invoices/from-delivery-note'), payload)
	return data.ocs.data
}

export async function addInvoicePosition(invoiceId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/invoices/{invoiceId}/positions', { invoiceId }), payload)
	return data.ocs.data
}

export async function removeInvoicePosition(invoiceId, id) {
	await axios.delete(generateOcsUrl('apps/erp/api/v1/invoices/{invoiceId}/positions/{id}', { invoiceId, id }))
}

export async function issueInvoice(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/invoices/{id}/issue', { id }))
	return data.ocs.data
}

export async function recordInvoicePayment(id, amount) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/invoices/{id}/payments', { id }), { amount })
	return data.ocs.data
}

export async function fetchCreditNotes(invoiceId, projectId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/credit-notes'), { params: { invoiceId, projectId } })
	return data.ocs.data
}

export async function fetchCreditNote(id) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/credit-notes/{id}', { id }))
	return data.ocs.data
}

export async function createFullCancellation(invoiceId, reason) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/credit-notes/full-cancellation'), { invoiceId, reason })
	return data.ocs.data
}

export async function createPartialCreditNote(invoiceId, reason) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/credit-notes/partial'), { invoiceId, reason })
	return data.ocs.data
}

export async function addCreditNotePosition(creditNoteId, payload) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/credit-notes/{creditNoteId}/positions', { creditNoteId }), payload)
	return data.ocs.data
}

export async function issueCreditNote(id) {
	const { data } = await axios.post(generateOcsUrl('apps/erp/api/v1/credit-notes/{id}/issue', { id }))
	return data.ocs.data
}
