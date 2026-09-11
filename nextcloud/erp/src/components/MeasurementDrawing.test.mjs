import test from 'node:test'
import assert from 'node:assert/strict'
import { boundedPoint, appendPoint } from './measurementDrawing.mjs'

test('boundedPoint maps pointer coordinates into the drawing bounds', () => {
	assert.deepEqual(boundedPoint({ clientX: 250, clientY: 50 }, { left: 100, top: 20, width: 100, height: 100 }), [100, 30])
	assert.deepEqual(boundedPoint({ clientX: 20, clientY: 500 }, { left: 100, top: 20, width: 100, height: 100 }), [0, 100])
})

test('appendPoint does not mutate prior strokes and ignores duplicate points', () => {
	const strokes = [[[1, 2]]]
	assert.deepEqual(appendPoint(strokes, [3, 4]), [[[1, 2], [3, 4]]])
	assert.deepEqual(appendPoint(strokes, [1, 2]), strokes)
	assert.deepEqual(strokes, [[[1, 2]]])
})
