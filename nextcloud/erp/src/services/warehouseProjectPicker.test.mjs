import test from 'node:test'
import assert from 'node:assert/strict'
import { activeProjects, projectLabel } from './warehouseProjectPicker.js'

test('activeProjects exposes only current projects for a Baustellenlager picker', () => {
	const projects = [
		{ id: 11, projectNumber: 'P-00011', title: 'Rohbau', status: 'in_progress' },
		{ id: 12, projectNumber: 'P-00012', title: 'Freigabe ausstehend', status: 'waiting' },
		{ id: 13, projectNumber: 'P-00013', title: 'Erledigt', status: 'done' },
		{ id: 14, projectNumber: 'P-00014', title: 'Archiv', status: 'archived' },
	]

	assert.deepEqual(activeProjects(projects).map((project) => project.id), [11, 12])
})

test('projectLabel gives users a human-readable project number and title', () => {
	assert.equal(projectLabel({ projectNumber: 'P-00011', title: 'Rohbau' }), 'P-00011 · Rohbau')
})
