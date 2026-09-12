export function emptyContactCard() {
	return {
		fullName: '',
		email: '',
		phone: '',
		street: '',
		postalCode: '',
		city: '',
		country: '',
	}
}

export function contactCardDraft(card) {
	const address = String(card.address ?? '').split(';')
	return {
		fullName: String(card.displayName ?? ''),
		email: String(card.email ?? ''),
		phone: String(card.phone ?? ''),
		street: String(address[2] ?? ''),
		postalCode: String(address[5] ?? ''),
		city: String(address[3] ?? ''),
		country: String(address[6] ?? ''),
	}
}

export function contactCardPayload(draft) {
	const fullName = String(draft.fullName ?? '').trim()
	if (!fullName) {
		throw new Error('Ein Name ist für den Kontakt erforderlich.')
	}

	return {
		fullName,
		email: String(draft.email ?? '').trim(),
		phone: String(draft.phone ?? '').trim(),
		address: [
			'',
			'',
			String(draft.street ?? '').trim(),
			String(draft.city ?? '').trim(),
			'',
			String(draft.postalCode ?? '').trim(),
			String(draft.country ?? '').trim(),
		].join(';'),
	}
}

export function userFacingContactCardError(error) {
	if (error?.response?.status === 403) {
		return 'Du hast keine Berechtigung, Kontakte in diesem Adressbuch zu bearbeiten.'
	}
	if (error?.response?.status === 409) {
		return 'Das dedizierte Adressbuch ist nicht verfügbar oder nicht beschreibbar.'
	}
	return error?.response?.data?.ocs?.meta?.message ?? error?.message ?? 'Kontakt konnte nicht gespeichert werden.'
}
