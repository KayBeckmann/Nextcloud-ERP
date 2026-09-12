export function beginContactRoleReload(previousRevision) {
	return previousRevision + 1
}

export function acceptsContactRoleReload(requestRevision, activeRevision) {
	return requestRevision === activeRevision
}
