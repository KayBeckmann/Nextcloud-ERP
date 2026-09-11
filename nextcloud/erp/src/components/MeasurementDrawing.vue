<template>
	<div class="measurement-drawing">
		<svg ref="surface" class="measurement-drawing__surface" viewBox="0 0 600 360" role="img" aria-label="Zeichnung" @pointerdown="start" @pointermove="move" @pointerup="end" @pointercancel="end" @lostpointercapture="end">
			<polyline v-for="(stroke, index) in strokes" :key="index" :points="stroke.map(point => point.join(',')).join(' ')" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
		</svg>
		<div class="measurement-drawing__actions"><button type="button" @click="undo" :disabled="!strokes.length">Letzten Strich löschen</button><button type="button" @click="clear" :disabled="!strokes.length">Zeichnung leeren</button></div>
	</div>
</template>
<script>
import { appendPoint, boundedPoint } from './measurementDrawing.mjs'
export default {
	name: 'MeasurementDrawing',
	props: { modelValue: { type: Array, default: () => [] } },
	emits: ['update:modelValue'],
	computed: { strokes() { return this.modelValue } },
	methods: {
		point(event) { const rect = this.$refs.surface.getBoundingClientRect(); const [x, y] = boundedPoint(event, rect); return [x * 600 / rect.width, y * 360 / rect.height] },
		start(event) { if (event.button !== 0 && event.pointerType === 'mouse') return; event.preventDefault(); this.$refs.surface.setPointerCapture?.(event.pointerId); this.$emit('update:modelValue', [...this.strokes, [this.point(event)]]) },
		move(event) { if (!this.strokes.length || !this.$refs.surface.hasPointerCapture?.(event.pointerId)) return; this.$emit('update:modelValue', appendPoint(this.strokes, this.point(event))) },
		end(event) { if (this.$refs.surface.hasPointerCapture?.(event.pointerId)) this.$refs.surface.releasePointerCapture(event.pointerId) },
		undo() { this.$emit('update:modelValue', this.strokes.slice(0, -1)) }, clear() { this.$emit('update:modelValue', []) },
	},
}
</script>
<style scoped>
.measurement-drawing__surface { display:block; width:100%; max-width:600px; aspect-ratio:5/3; touch-action:none; border:1px solid var(--color-border); border-radius:var(--border-radius,4px); background:var(--color-background-dark); cursor:crosshair; }
.measurement-drawing__actions { display:flex; gap:8px; margin-top:8px; }
</style>
