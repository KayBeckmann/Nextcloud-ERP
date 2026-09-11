import test from 'node:test'
import assert from 'node:assert/strict'
import { buildWorkflowStages } from './workflowProgress.mjs'

const writePermissions = {
	projekte: 'write',
	angebote: 'write',
	auftraege: 'write',
	lager: 'write',
	lieferscheine: 'write',
	rechnungen: 'write',
}

test('buildWorkflowStages explains the first missing prerequisite in German', () => {
	const stages = buildWorkflowStages({ project: { id: 7 }, permissions: writePermissions })

	assert.equal(stages[0].key, 'contact')
	assert.equal(stages[0].state, 'todo')
	assert.match(stages[0].detail, /Kunde/i)
	assert.equal(stages[0].action.tab, 'Übersicht')
})

test('buildWorkflowStages marks an issued partial invoice as waiting for payment', () => {
	const stages = buildWorkflowStages({
		project: { id: 7, customerContactUid: 'customer-1' },
		quotes: [{ status: 'accepted' }],
		orders: [{ status: 'confirmed' }],
		deliveryNotes: [{ status: 'issued' }],
		invoices: [{ type: 'partial', status: 'issued', paidAmount: 0 }],
		permissions: writePermissions,
	})

	const partialInvoice = stages.find((stage) => stage.key === 'partialInvoice')
	assert.equal(partialInvoice.state, 'waiting')
	assert.match(partialInvoice.detail, /Zahlung/i)
	assert.equal(partialInvoice.action.tab, 'Rechnungen')
})

test('buildWorkflowStages distinguishes unavailable actions from missing prerequisites', () => {
	const stages = buildWorkflowStages({
		project: { id: 7, customerContactUid: 'customer-1' },
		permissions: { ...writePermissions, angebote: 'read' },
	})

	const quote = stages.find((stage) => stage.key === 'quote')
	assert.equal(quote.state, 'restricted')
	assert.match(quote.detail, /Leserecht|Schreibrecht/i)
	assert.equal(quote.action, null)
})

test('buildWorkflowStages reads the existing purchase-order detail response shape', () => {
	const stages = buildWorkflowStages({
		project: { id: 7, customerContactUid: 'customer-1' },
		quotes: [{ status: 'accepted' }],
		orders: [{ status: 'confirmed' }],
		purchaseOrders: [{ order: { status: 'sent' }, positions: [{ projectId: 7 }] }],
		permissions: writePermissions,
	})

	assert.equal(stages.find((stage) => stage.key === 'purchaseOrder').state, 'complete')
})
