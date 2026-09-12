import test from 'node:test'
import assert from 'node:assert/strict'
import { contactCardDraft, contactCardPayload, emptyContactCard, userFacingContactCardError } from './contactCards.mjs'

test('emptyContactCard starts a native contact-card draft without ERP fields', () => {
	assert.deepEqual(emptyContactCard(), {
		fullName: '',
		email: '',
		phone: '',
		street: '',
		postalCode: '',
		city: '',
		country: '',
	})
})

test('contactCardPayload trims native contact fields and keeps address in vCard ADR order', () => {
	assert.deepEqual(contactCardPayload({
		fullName: '  ACME GmbH  ',
		email: '  kontakt@acme.test ',
		phone: '  +49 30 123 ',
		street: '  Musterstraße 1 ',
		postalCode: ' 10115 ',
		city: ' Berlin ',
		country: ' Deutschland ',
	}), {
		fullName: 'ACME GmbH',
		email: 'kontakt@acme.test',
		phone: '+49 30 123',
		address: ';;Musterstraße 1;Berlin;;10115;Deutschland',
	})
})

test('contactCardPayload rejects a contact without a name', () => {
	assert.throws(() => contactCardPayload(emptyContactCard()), /Name/i)
})

test('contactCardDraft exposes editable native vCard fields without ERP metadata', () => {
	assert.deepEqual(contactCardDraft({
		uid: 'customer-1',
		displayName: 'ACME GmbH',
		email: 'kontakt@acme.test',
		phone: '+49 30 123',
		address: ';;Musterstraße 1;Berlin;;10115;Deutschland',
	}), {
		fullName: 'ACME GmbH',
		email: 'kontakt@acme.test',
		phone: '+49 30 123',
		street: 'Musterstraße 1',
		postalCode: '10115',
		city: 'Berlin',
		country: 'Deutschland',
	})
})

test('userFacingContactCardError distinguishes an unavailable or read-only address book', () => {
	assert.match(userFacingContactCardError({ response: { status: 403 } }), /Berechtigung/i)
	assert.match(userFacingContactCardError({ response: { status: 409 } }), /Adressbuch/i)
})
