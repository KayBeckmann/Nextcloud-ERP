export function boundedPoint(event, rect) {
	const x = Math.max(0, Math.min(rect.width, event.clientX - rect.left))
	const y = Math.max(0, Math.min(rect.height, event.clientY - rect.top))
	return [Math.round(x * 100) / 100, Math.round(y * 100) / 100]
}

export function appendPoint(strokes, point) {
	if (!strokes.length) return [[point]]
	const previous = strokes.at(-1)
	if (previous.length && previous.at(-1)[0] === point[0] && previous.at(-1)[1] === point[1]) return strokes
	return [...strokes.slice(0, -1), [...previous, point]]
}
