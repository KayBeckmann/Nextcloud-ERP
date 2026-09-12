const ACTIVE_PROJECT_STATUSES = new Set(['in_progress', 'waiting'])

export function activeProjects(projects) {
	return projects.filter((project) => ACTIVE_PROJECT_STATUSES.has(project.status))
}

export function projectLabel(project) {
	return [project.projectNumber, project.title].filter(Boolean).join(' · ')
}
