import test from 'node:test'
import assert from 'node:assert/strict'
import { beginContactRoleReload, acceptsContactRoleReload } from './contactRoleReload.mjs'

test('a newer role selection rejects a late response from the previous role', () => {
	const customerRequest = beginContactRoleReload(0)
	const supplierRequest = beginContactRoleReload(customerRequest)

	assert.equal(acceptsContactRoleReload(customerRequest, supplierRequest), false)
	assert.equal(acceptsContactRoleReload(supplierRequest, supplierRequest), true)
})
