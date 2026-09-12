/** Merge live role addressbook cards with optional ERP role metadata. */
export function mergeRoleContacts(cards, links) {
	const cardsByUid = new Map(cards.map((card) => [card.uid, card]))
	const rows = cards.map((card) => ({ ...card, link: links.find((link) => link.contactUid === card.uid) ?? null }))
	for (const link of links) {
		if (!cardsByUid.has(link.contactUid)) rows.push({ uid: link.contactUid, displayName: link.displayName ?? link.contactUid, email: '', link, missingNativeCard: true })
	}
	return rows
}
