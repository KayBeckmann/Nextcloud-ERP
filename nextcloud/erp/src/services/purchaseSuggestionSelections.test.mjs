import assert from 'node:assert/strict'
import test from 'node:test'
import { selectedPurchaseSuggestionPayload } from './purchaseSuggestionSelections.js'

test('selected suggestion payload keeps each explicit supplier choice and excludes unselected suggestions', () => {
	const suggestions = [
		{ articleId: 10, warehouseId: 1, supplierOptions: [{ supplierContactUid: 'supplier-a' }, { supplierContactUid: 'supplier-b' }] },
		{ articleId: 11, warehouseId: 1, supplierOptions: [{ supplierContactUid: 'supplier-b' }] },
	]

	assert.deepEqual(selectedPurchaseSuggestionPayload(suggestions, ['10-1', '11-1'], { '10-1': 'supplier-b', '11-1': 'supplier-b' }), [
		{ articleId: 10, warehouseId: 1, supplierContactUid: 'supplier-b' },
		{ articleId: 11, warehouseId: 1, supplierContactUid: 'supplier-b' },
	])
})
