<template>
	<div class="erp-contact-picker">
		<input
			v-model="query"
			type="text"
			:placeholder="placeholder"
			@focus="onFocus"
			@input="onInput"
			@blur="onBlur"
		>
		<button v-if="modelValue" type="button" class="erp-contact-picker__clear" title="Auswahl entfernen" @click="clear">✕</button>
		<ul v-if="showDropdown && results.length" class="erp-contact-picker__dropdown">
			<li v-for="c in results" :key="c.uid" @mousedown.prevent="select(c)">
				{{ c.displayName }}
				<small v-if="c.emails && c.emails.length">({{ c.emails[0] }})</small>
			</li>
		</ul>
	</div>
</template>

<script>
import { searchContacts, resolveContactName } from '../services/contactsApi.js'

// Wiederverwendbarer Kunden-/Kontakt-Picker mit Suchfeld (ADR-0015) — nutzt
// den bestehenden GET /contacts/search-Endpunkt aus ADR-0009, kein neuer
// Backend-Code nötig.
export default {
	name: 'ContactPicker',
	props: {
		modelValue: { type: String, default: null },
		placeholder: { type: String, default: 'Kontakt suchen …' },
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
				const resolved = await resolveContactName(uid)
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
				this.results = await searchContacts(this.query.trim())
				this.showDropdown = true
			}, 250)
		},
		onBlur() {
			// Verzögert schließen, damit ein Klick auf einen Dropdown-Eintrag
			// (mousedown) noch vor dem blur-bedingten Schließen ankommt.
			setTimeout(() => {
				this.showDropdown = false
			}, 150)
		},
		select(contact) {
			this.query = contact.displayName
			this.showDropdown = false
			this.$emit('update:modelValue', contact.uid)
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
.erp-contact-picker {
	position: relative;
	display: inline-flex;
	align-items: center;
	width: 100%;
	max-width: 320px;
}
.erp-contact-picker input {
	width: 100%;
}
.erp-contact-picker__clear {
	position: absolute;
	right: 4px;
	background: none;
	border: none;
	cursor: pointer;
	color: var(--color-text-maxcontrast);
}
.erp-contact-picker__dropdown {
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
.erp-contact-picker__dropdown li {
	padding: 6px 10px;
	cursor: pointer;
	font-size: 13px;
}
.erp-contact-picker__dropdown li:hover {
	background: var(--color-background-hover);
}
.erp-contact-picker__dropdown small {
	display: block;
	color: var(--color-text-maxcontrast);
}
</style>
