const INACTIVE_PROJECT_STATUSES = new Set(['done', 'archived'])

export function activeProjects(projects) {
	return projects.filter((project) => !INACTIVE_PROJECT_STATUSES.has(project.status))
}

export function defaultWritableCalendarUri(calendars) {
	return calendars.find((calendar) => calendar.writable)?.uri ?? null
}

export function isValidEventInterval(start, end) {
	return Boolean(start && end && new Date(end).getTime() > new Date(start).getTime())
}

export function buildProjectEventPayload({ calendarUri, project, summary, start, end, description }) {
	return {
		calendarUri,
		resourceType: 'projekte',
		resourceId: String(project.id),
		summary: summary.trim(),
		start,
		end,
		description: description.trim(),
	}
}

export function userFacingCalendarError(error) {
	if (error?.response?.status === 403) {
		return 'Sie haben keine Berechtigung, auf die Kalenderdaten zuzugreifen.'
	}
	return `Kalender konnte nicht geladen werden: ${error?.response?.data?.ocs?.meta?.message ?? error?.message ?? String(error)}`
}
