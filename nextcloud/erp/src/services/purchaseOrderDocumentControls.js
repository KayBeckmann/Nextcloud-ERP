export function purchaseOrderDocumentControls(order) {
	const documentPrepared = order?.documentPrepared === true
	return {
		canPrepare: !documentPrepared,
		canDownload: documentPrepared,
	}
}