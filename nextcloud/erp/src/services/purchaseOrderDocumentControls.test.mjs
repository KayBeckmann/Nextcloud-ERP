import test from 'node:test'
import assert from 'node:assert/strict'

import { purchaseOrderDocumentControls } from './purchaseOrderDocumentControls.js'

test('purchase-order controls offer preparation until the server reports a prepared document', () => {
	assert.deepEqual(purchaseOrderDocumentControls({ documentPrepared: false }), {
		canPrepare: true,
		canDownload: false,
	})
	assert.deepEqual(purchaseOrderDocumentControls({ documentPrepared: true }), {
		canPrepare: false,
		canDownload: true,
	})
})