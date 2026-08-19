<template>
	<div class="erp-placeholder">
		<h2>{{ title }}</h2>
		<p>{{ description }}</p>
		<p class="erp-placeholder__phase">Geplante Umsetzung: {{ phase }} (siehe <code>docs/roadmap.md</code>)</p>
	</div>
</template>

<script>
export default {
	name: 'PlaceholderView',
	props: {
		title: { type: String, required: true },
		description: { type: String, required: true },
		phase: { type: String, required: true },
	},
}
</script>

<style scoped>
.erp-placeholder {
	padding: 20px;
	max-width: 640px;
}
.erp-placeholder__phase {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
</style>
