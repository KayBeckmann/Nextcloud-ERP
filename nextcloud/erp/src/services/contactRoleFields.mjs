export function contactRoleFields(role) {
	return role === 'supplier'
		? [
			{ key: 'referenceNumber', label: 'Kundennummer bei Lieferant' },
			{ key: 'notes', label: 'Memo' },
		]
		: [{ key: 'notes', label: 'Memo' }]
}
