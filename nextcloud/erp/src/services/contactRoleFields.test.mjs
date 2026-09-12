import test from 'node:test'
import assert from 'node:assert/strict'
import { contactRoleFields } from './contactRoleFields.mjs'

test('supplier metadata offers customer number and memo while customer offers memo only', () => {
	assert.deepEqual(contactRoleFields('supplier'), [
		{ key: 'referenceNumber', label: 'Kundennummer bei Lieferant' },
		{ key: 'notes', label: 'Memo' },
	])
	assert.deepEqual(contactRoleFields('customer'), [
		{ key: 'notes', label: 'Memo' },
	])
})
