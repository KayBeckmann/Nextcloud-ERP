<template>
	<div class="erp-contacts">
		<h2>{{ title }}</h2>
		<p class="erp-contacts__hint">
			Referenziert Nextcloud Contacts (ADR-0009) — es werden nur Contact-UID + ERP-Metadaten
			gespeichert, keine Kopie von Name/Adresse.
		</p>

		<p v-if="loadError" class="erp-contacts__error">
			Fehler: {{ loadError }}
			<span v-if="isForbidden"> — dir fehlt mindestens Lesen auf "{{ title }}".</span>
		</p>

		<template v-else>
			<section class="erp-contacts__search">
				<input v-model="query" type="text" :placeholder="`Contacts durchsuchen…`" @input="onSearch">
				<ul v-if="searchResults.length" class="erp-contacts__results">
					<li v-for="c in searchResults" :key="c.uid">
						<span>{{ c.displayName }}</span>
						<span class="erp-contacts__email">{{ c.emails[0] }}</span>
						<button :disabled="isLinked(c.uid) || saving" @click="link(c)">
							{{ isLinked(c.uid) ? 'bereits verknüpft' : 'Verknüpfen' }}
						</button>
					</li>
				</ul>
			</section>

			<table v-if="links.length" class="erp-contacts__table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Referenznummer</th>
						<th>Zahlungsziel (Tage)</th>
						<th>Notizen</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="l in links" :key="l.id">
						<td>{{ l.displayName }}</td>
						<td><input v-model="l.referenceNumber" @change="save(l)"></td>
						<td><input v-model.number="l.paymentTermsDays" type="number" @change="save(l)"></td>
						<td><input v-model="l.notes" @change="save(l)"></td>
						<td><button @click="unlink(l)">Entfernen</button></td>
					</tr>
				</tbody>
			</table>
			<p v-else>Noch keine {{ title }} verknüpft.</p>
		</template>
	</div>
</template>

<script>
import { createContactLink, deleteContactLink, fetchContactLinks, searchContacts, updateContactLink } from '../services/contactsApi.js'

export default {
	name: 'ContactLinksView',
	props: {
		role: { type: String, required: true }, // 'customer' | 'supplier'
		title: { type: String, required: true },
	},
	data() {
		return {
			query: '',
			searchResults: [],
			links: [],
			loadError: null,
			isForbidden: false,
			saving: false,
			searchTimeout: null,
		}
	},
	async mounted() {
		await this.loadLinks()
	},
	methods: {
		async loadLinks() {
			try {
				this.links = await fetchContactLinks(this.role)
			} catch (e) {
				this.isForbidden = e?.response?.status === 403
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		isLinked(uid) {
			return this.links.some((l) => l.contactUid === uid)
		},
		onSearch() {
			clearTimeout(this.searchTimeout)
			this.searchTimeout = setTimeout(async () => {
				this.searchResults = this.query.length >= 1 ? await searchContacts(this.query) : []
			}, 250)
		},
		async link(contact) {
			this.saving = true
			try {
				await createContactLink({ contactUid: contact.uid, role: this.role })
				await this.loadLinks()
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			} finally {
				this.saving = false
			}
		},
		async save(link) {
			try {
				await updateContactLink(link.id, {
					referenceNumber: link.referenceNumber,
					paymentTermsDays: link.paymentTermsDays,
					notes: link.notes,
				})
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async unlink(link) {
			try {
				await deleteContactLink(link.id)
				this.links = this.links.filter((l) => l.id !== link.id)
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
	},
}
</script>

<style scoped>
.erp-contacts {
	padding: 20px;
	max-width: 720px;
}
.erp-contacts__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}
.erp-contacts__error {
	color: var(--color-error-text, #c00);
}
.erp-contacts__search input[type="text"] {
	width: 100%;
	max-width: 360px;
	padding: 6px 10px;
}
.erp-contacts__results {
	list-style: none;
	margin: 8px 0 20px;
	padding: 0;
}
.erp-contacts__results li {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 4px 0;
}
.erp-contacts__email {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}
.erp-contacts__table {
	border-collapse: collapse;
	width: 100%;
}
.erp-contacts__table th,
.erp-contacts__table td {
	text-align: left;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}
.erp-contacts__table input {
	width: 100%;
	border: 1px solid transparent;
	background: transparent;
}
.erp-contacts__table input:hover,
.erp-contacts__table input:focus {
	border-color: var(--color-border);
}
</style>
