import test from 'node:test'
import assert from 'node:assert/strict'
import {
	activeProjects,
	buildProjectEventPayload,
	defaultWritableCalendarUri,
	isValidEventInterval,
	userFacingCalendarError,
} from './calendarPersonal.mjs'

test('activeProjects keeps projects that can still receive planning events', () => {
	const projects = activeProjects([
		{ id: 1, title: 'Entwurf', status: 'draft' },
		{ id: 2, title: 'Baustelle', status: 'in_progress' },
		{ id: 3, title: 'Fertig', status: 'done' },
		{ id: 4, title: 'Archiv', status: 'archived' },
	])

	assert.deepEqual(projects.map((project) => project.id), [1, 2])
})

test('defaultWritableCalendarUri selects the first calendar that accepts events', () => {
	assert.equal(defaultWritableCalendarUri([
		{ uri: 'read-only', writable: false },
		{ uri: 'erp', writable: true },
	]), 'erp')
	assert.equal(defaultWritableCalendarUri([{ uri: 'read-only', writable: false }]), null)
})

test('buildProjectEventPayload creates a project-bound native Calendar request', () => {
	assert.deepEqual(buildProjectEventPayload({
		calendarUri: 'erp',
		project: { id: 42 },
		summary: '  Baustellenbesprechung  ',
		start: '2026-09-11T09:00',
		end: '2026-09-11T10:00',
		description: '  Mit dem Team  ',
	}), {
		calendarUri: 'erp',
		resourceType: 'projekte',
		resourceId: '42',
		summary: 'Baustellenbesprechung',
		start: '2026-09-11T09:00',
		end: '2026-09-11T10:00',
		description: 'Mit dem Team',
	})
})

test('userFacingCalendarError distinguishes denied Calendar access', () => {
	assert.match(userFacingCalendarError({ response: { status: 403 } }), /Berechtigung/i)
	assert.match(userFacingCalendarError(new Error('network unavailable')), /Kalender/i)
})

test('isValidEventInterval rejects an end that is not after the start', () => {
	assert.equal(isValidEventInterval('2026-09-11T09:00', '2026-09-11T10:00'), true)
	assert.equal(isValidEventInterval('2026-09-11T09:00', '2026-09-11T09:00'), false)
	assert.equal(isValidEventInterval('2026-09-11T10:00', '2026-09-11T09:00'), false)
})
