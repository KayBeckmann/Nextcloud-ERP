import test from 'node:test'
import assert from 'node:assert/strict'
import { mergeRoleContacts } from './contactRoleList.mjs'

test('mergeRoleContacts presents native cards and their ERP link as one row', () => {
	assert.deepEqual(mergeRoleContacts(
		[{ uid: 'native-1', displayName: 'ACME', email: 'a@example.test' }],
		[{ id: 7, contactUid: 'native-1', referenceNumber: 'C-1' }],
	), [{ uid: 'native-1', displayName: 'ACME', email: 'a@example.test', link: { id: 7, contactUid: 'native-1', referenceNumber: 'C-1' } }])
})
