export function selectedPurchaseSuggestionPayload(suggestions, selectedKeys, selectedSupplierByKey) {
	return suggestions
		.filter((suggestion) => selectedKeys.includes(`${suggestion.articleId}-${suggestion.warehouseId}`))
		.map((suggestion) => ({
			articleId: suggestion.articleId,
			warehouseId: suggestion.warehouseId,
			supplierContactUid: selectedSupplierByKey[`${suggestion.articleId}-${suggestion.warehouseId}`],
		}))
}
