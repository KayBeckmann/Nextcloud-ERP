<template>
	<div class="erp-user-picker">
		<input
			v-model="query"
			type="text"
			:placeholder="placeholder"
			@focus="onFocus"
			@input="onInput"
			@blur="onBlur"
		>
		<button v-if="modelValue" type="button" class="erp-user-picker__clear" title="Auswahl entfernen" @click="clear">✕</button>
		<ul v-if="showDropdown && results.length" class="erp-user-picker__dropdown">
			<li v-for="u in results" :key="u.uid" @mousedown.prevent="select(u)">
				{{ u.displayName }}
				<small>({{ u.uid }})</small>
			</li>
		</ul>
	</div>
</template>

<script>
import { searchUsers, resolveUserName } from '../services/permissionsApi.js'

// Wiederverwendbarer Nextcloud-User-Picker mit Suchfeld (ADR-0015) — z. B.
// für "Verantwortlicher" im Projekt.
export default {
	name: 'UserPicker',
	props: {
		modelValue: { type: String, default: null },
		placeholder: { type: String, default: 'User suchen …' },
	},
	emits: ['update:modelValue'],
	data() {
		return {
			query: '',
			results: [],
			showDropdown: false,
			debounceTimer: null,
		}
	},
	watch: {
		async modelValue(newValue) {
			await this.syncQueryWithModelValue(newValue)
		},
	},
	async mounted() {
		await this.syncQueryWithModelValue(this.modelValue)
	},
	methods: {
		async syncQueryWithModelValue(uid) {
			if (!uid) {
				this.query = ''
				return
			}
			try {
				const resolved = await resolveUserName(uid)
				this.query = resolved.displayName
			} catch {
				this.query = uid
			}
		},
		onFocus() {
			if (this.results.length) {
				this.showDropdown = true
			}
		},
		onInput() {
			clearTimeout(this.debounceTimer)
			if (this.query.trim().length < 1) {
				this.results = []
				this.showDropdown = false
				return
			}
			this.debounceTimer = setTimeout(async () => {
				this.results = await searchUsers(this.query.trim())
				this.showDropdown = true
			}, 250)
		},
		onBlur() {
			setTimeout(() => {
				this.showDropdown = false
			}, 150)
		},
		select(user) {
			this.query = user.displayName
			this.showDropdown = false
			this.$emit('update:modelValue', user.uid)
		},
		clear() {
			this.query = ''
			this.results = []
			this.$emit('update:modelValue', null)
		},
	},
}
</script>

<style scoped>
.erp-user-picker {
	position: relative;
	display: inline-flex;
	align-items: center;
	width: 100%;
	max-width: 320px;
}
.erp-user-picker input {
	width: 100%;
}
.erp-user-picker__clear {
	position: absolute;
	right: 4px;
	background: none;
	border: none;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
}
.erp-user-picker__dropdown {
	position: absolute;
	top: 100%;
	left: 0;
	right: 0;
	z-index: 50;
	margin: 2px 0 0;
	padding: 4px 0;
	list-style: none;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: 4px;
	max-height: 220px;
	overflow-y: auto;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
.erp-user-picker__dropdown li {
	padding: 6px 10px;
	cursor: pointer;
	font-size: 13px;
}
.erp-user-picker__dropdown li:hover {
	background: var(--color-background-hover);
}
.erp-user-picker__dropdown small {
	color: var(--color-text-maxcontrast);
}
</style>
