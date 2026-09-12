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
				<p>Neue Stammdaten liegen ausschließlich in Nextcloud Contacts. Du verwaltest sie hier im dedizierten {{ title }}-Adressbuch; die ERP-Rolle ergibt sich aus diesem Adressbuch. <a :href="contactsUrl" target="_blank" rel="noopener">In Contacts öffnen</a> ist nur eine zusätzliche Ansicht.</p>
				<form class="erp-contacts__new-card" @submit.prevent="createCard">
					<input v-model="newContact.fullName" required placeholder="Name / Firma">
					<input v-model="newContact.email" type="email" placeholder="E-Mail">
					<input v-model="newContact.phone" placeholder="Telefon">
					<input v-model="newContact.street" placeholder="Straße / Hausnummer">
					<input v-model="newContact.postalCode" placeholder="PLZ">
					<input v-model="newContact.city" placeholder="Ort">
					<input v-model="newContact.country" placeholder="Land">
					<button :disabled="saving">Kontakt anlegen und verknüpfen</button>
				</form>

				<h3>{{ title }}</h3>
				<p v-if="!roleContacts.length">Noch keine Kontakte in diesem {{ title }}-Adressbuch.</p>
				<ul v-else class="erp-contacts__cards">
					<li v-for="card in roleContacts" :key="card.uid">
						<div>
							<strong>{{ card.displayName }}</strong>
							<span v-if="card.link" class="erp-contacts__email">{{ card.link.referenceNumber || 'ERP-verknüpft' }}</span>
						</div>
						<button :disabled="saving" @click="startEdit(card)">Bearbeiten</button>
						<button :disabled="saving" @click="confirmDeleteCard(card)">Löschen</button>
					</li>
				</ul>
				<form v-if="editingCard" class="erp-contacts__new-card erp-contacts__edit-card" @submit.prevent="saveCard">
					<h4>Kontakt bearbeiten: {{ editingCard.displayName }}</h4>
					<input v-model="editContact.fullName" required placeholder="Name / Firma">
					<input v-model="editContact.email" type="email" placeholder="E-Mail">
					<input v-model="editContact.phone" placeholder="Telefon">
					<input v-model="editContact.street" placeholder="Straße / Hausnummer">
					<input v-model="editContact.postalCode" placeholder="PLZ">
					<input v-model="editContact.city" placeholder="Ort">
					<input v-model="editContact.country" placeholder="Land">
					<template v-for="field in metadataFields" :key="field.key">
						<input v-if="field.key === 'referenceNumber'" v-model="editMetadata.referenceNumber" :placeholder="field.label">
						<textarea v-else v-model="editMetadata.notes" :placeholder="field.label" rows="3" />
					</template>
					<button :disabled="saving">Änderungen speichern</button>
					<button type="button" :disabled="saving" @click="cancelEdit">Abbrechen</button>
				</form>

				<input v-model="query" type="text" :placeholder="`Bestehende Contacts durchsuchen…`" @input="onSearch">
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
		</template>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { createContactCard, createContactLink, deleteContactCard, deleteContactLink, fetchContactCards, fetchContactLinks, searchContacts, updateContactCard, updateContactLink } from '../services/contactsApi.js'
import { contactCardDraft, contactCardPayload, emptyContactCard, userFacingContactCardError } from '../services/contactCards.mjs'
import { acceptsContactRoleReload, beginContactRoleReload } from '../services/contactRoleReload.mjs'
import { mergeRoleContacts } from '../services/contactRoleList.mjs'
import { contactRoleFields } from '../services/contactRoleFields.mjs'

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
			cards: [],
			editingCard: null,
			editContact: emptyContactCard(),
			editMetadata: { referenceNumber: '', notes: '' },
			links: [],
			loadError: null,
			isForbidden: false,
			saving: false,
			searchTimeout: null,
			reloadRevision: 0,
			contactsUrl: generateUrl('/apps/contacts'),
			newContact: emptyContactCard(),
		}
	},
	async mounted() {
		await this.reloadRole()
	},
	watch: {
		role() {
			this.reloadRole()
		},
	},
	computed: {
		roleContacts() { return mergeRoleContacts(this.cards, this.links) },
		metadataFields() { return contactRoleFields(this.role) },
	},
	methods: {
		async reloadRole() {
			const revision = this.reloadRevision = beginContactRoleReload(this.reloadRevision)
			this.cards = []
			this.links = []
			this.searchResults = []
			this.query = ''
			this.loadError = null
			this.isForbidden = false
			this.cancelEdit()
			await Promise.all([this.loadCards(revision), this.loadLinks(revision)])
		},
		async loadCards(revision = this.reloadRevision) {
			try {
				const cards = await fetchContactCards(this.role)
				if (acceptsContactRoleReload(revision, this.reloadRevision)) this.cards = cards
			} catch (e) {
				if (!acceptsContactRoleReload(revision, this.reloadRevision)) return
				this.isForbidden = e?.response?.status === 403
				this.loadError = userFacingContactCardError(e)
			}
		},
		async loadLinks(revision = this.reloadRevision) {
			try {
				const links = await fetchContactLinks(this.role)
				if (acceptsContactRoleReload(revision, this.reloadRevision)) this.links = links
			} catch (e) {
				if (!acceptsContactRoleReload(revision, this.reloadRevision)) return
				this.isForbidden = e?.response?.status === 403
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		isLinked(uid) {
			return this.links.some((l) => l.contactUid === uid)
		},
		async createCard() {
			this.saving = true
			try {
				const card = await createContactCard(this.role, contactCardPayload(this.newContact))
				await createContactLink({ contactUid: card.uid, role: this.role })
				this.newContact = emptyContactCard()
				await Promise.all([this.loadCards(), this.loadLinks()])
			} catch (e) {
				this.loadError = userFacingContactCardError(e)
			} finally {
				this.saving = false
			}
		},
		startEdit(card) {
			this.editingCard = card
			this.editContact = contactCardDraft(card)
			this.editMetadata = { referenceNumber: card.link?.referenceNumber ?? '', notes: card.link?.notes ?? '' }
		},
		cancelEdit() {
			this.editingCard = null
			this.editContact = emptyContactCard()
			this.editMetadata = { referenceNumber: '', notes: '' }
		},
		async saveCard() {
			if (!this.editingCard) return
			this.saving = true
			try {
				await updateContactCard(this.role, this.editingCard.uid, contactCardPayload(this.editContact))
				const metadata = {
					referenceNumber: this.role === 'supplier' ? this.editMetadata.referenceNumber.trim() || null : null,
					notes: this.editMetadata.notes.trim() || null,
				}
				if (this.editingCard.link) await updateContactLink(this.editingCard.link.id, metadata)
				else await createContactLink({ contactUid: this.editingCard.uid, role: this.role, ...metadata })
				await Promise.all([this.loadCards(), this.loadLinks()])
				this.cancelEdit()
			} catch (e) {
				this.loadError = userFacingContactCardError(e)
			} finally {
				this.saving = false
			}
		},
		async confirmDeleteCard(card) {
			if (!window.confirm(`Kontakt „${card.displayName}“ endgültig löschen? Historische Belege behalten Name und Anschrift.`)) return
			this.saving = true
			try {
				await deleteContactCard(this.role, card.uid)
				await Promise.all([this.loadCards(), this.loadLinks()])
				if (this.editingCard?.uid === card.uid) this.cancelEdit()
			} catch (e) {
				this.loadError = userFacingContactCardError(e)
			} finally {
				this.saving = false
			}
		},
		onSearch() {
			clearTimeout(this.searchTimeout)
			this.searchTimeout = setTimeout(async () => {
				try {
					this.searchResults = this.query.length >= 1 ? await searchContacts(this.query) : []
				} catch (e) {
					this.searchResults = []
					this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
				}
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
.erp-contacts__new-card {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 8px;
	margin: 12px 0;
}
.erp-contacts__new-card input:first-child,
.erp-contacts__new-card button {
	grid-column: 1 / -1;
}
@media (max-width: 520px) {
	.erp-contacts__new-card {
		grid-template-columns: 1fr;
	}
}
.erp-contacts__results {
	list-style: none;
	margin: 8px 0 20px;
	padding: 0;
}
.erp-contacts__cards {
	list-style: none;
	margin: 8px 0 20px;
	padding: 0;
}
.erp-contacts__cards li {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 10px;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
}
.erp-contacts__cards strong,
.erp-contacts__cards .erp-contacts__email {
	display: block;
}
.erp-contacts__edit-card {
	padding-top: 12px;
	border-top: 1px solid var(--color-border);
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
