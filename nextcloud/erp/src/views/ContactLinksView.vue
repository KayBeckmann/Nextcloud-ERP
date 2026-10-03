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
					<template v-for="card in roleContacts" :key="card.uid">
						<li>
							<div>
								<strong>{{ card.displayName }}</strong>
								<span v-if="card.link" class="erp-contacts__email">{{ card.link.referenceNumber || 'ERP-verknüpft' }}</span>
							</div>
							<button :disabled="saving" @click="startEdit(card)">Bearbeiten</button>
							<button v-if="card.link" :disabled="saving" @click="togglePersons(card)">
								Ansprechpartner<template v-if="personsByLinkId[card.link.id]"> ({{ personsByLinkId[card.link.id].length }})</template>
							</button>
							<button :disabled="saving" @click="confirmDeleteCard(card)">Löschen</button>
						</li>
						<li v-if="card.link && expandedLinkId === card.link.id" class="erp-contacts__persons-panel">
							<h4>Ansprechpartner: {{ card.displayName }}</h4>
							<p v-if="!(personsByLinkId[card.link.id] || []).length">Noch keine Ansprechpartner erfasst.</p>
							<ul v-else class="erp-contacts__persons-list">
								<li v-for="person in personsByLinkId[card.link.id]" :key="person.id">
									<template v-if="editingPersonId === person.id">
										<input v-model="editPerson.name" required placeholder="Name">
										<input v-model="editPerson.position" placeholder="Position (z. B. Geschäftsführer)">
										<input v-model="editPerson.email" type="email" placeholder="E-Mail">
										<input v-model="editPerson.phone" placeholder="Telefon">
										<button type="button" :disabled="savingPerson" @click="saveEditPerson(card.link.id)">Speichern</button>
										<button type="button" :disabled="savingPerson" @click="cancelEditPerson">Abbrechen</button>
									</template>
									<template v-else>
										<span>
											<strong>{{ person.name }}</strong>
											<template v-if="person.position"> — {{ person.position }}</template>
										</span>
										<span v-if="person.email || person.phone" class="erp-contacts__email">
											{{ [person.email, person.phone].filter(Boolean).join(' · ') }}
										</span>
										<button type="button" :disabled="savingPerson" @click="startEditPerson(person)">Bearbeiten</button>
										<button type="button" :disabled="savingPerson" @click="deletePersonConfirm(card.link.id, person)">Löschen</button>
									</template>
								</li>
							</ul>
							<form class="erp-contacts__new-person" @submit.prevent="addPerson(card.link.id)">
								<input v-model="newPerson.name" required placeholder="Name">
								<input v-model="newPerson.position" placeholder="Position (z. B. Geschäftsführer)">
								<input v-model="newPerson.email" type="email" placeholder="E-Mail">
								<input v-model="newPerson.phone" placeholder="Telefon">
								<button :disabled="savingPerson">Ansprechpartner hinzufügen</button>
							</form>

							<template v-if="(personsByLinkId[card.link.id] || []).length">
								<h4>Standard je Belegtyp</h4>
								<p class="erp-contacts__hint">Wer bekommt welchen Belegtyp dieser Firma standardmäßig zugesendet (z. Hd.) — im einzelnen Projekt überschreibbar.</p>
								<ul class="erp-contacts__person-defaults">
									<li v-for="type in documentTypes" :key="type.value">
										<label>
											{{ type.label }}
											<select :value="personDefaultsByLinkId[card.link.id]?.[type.value] ?? ''" :disabled="savingPerson" @change="setPersonDefault(card.link.id, type.value, $event.target.value)">
												<option value="">— kein Standard —</option>
												<option v-for="person in personsByLinkId[card.link.id]" :key="person.id" :value="person.id">{{ person.name }}</option>
											</select>
										</label>
									</li>
								</ul>
							</template>
						</li>
					</template>
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
import { createContactCard, createContactLink, createContactPerson, deleteContactCard, deleteContactLink, deleteContactPerson, fetchContactCards, fetchContactLinks, fetchContactPersonDefaults, fetchContactPersons, searchContacts, setContactPersonDefault, updateContactCard, updateContactLink, updateContactPerson } from '../services/contactsApi.js'
import { contactCardDraft, contactCardPayload, emptyContactCard, userFacingContactCardError } from '../services/contactCards.mjs'
import { acceptsContactRoleReload, beginContactRoleReload } from '../services/contactRoleReload.mjs'
import { mergeRoleContacts } from '../services/contactRoleList.mjs'
import { contactRoleFields } from '../services/contactRoleFields.mjs'
import { DOCUMENT_TYPES } from '../services/documentTypes.mjs'

function emptyPerson() {
	return { name: '', position: '', email: '', phone: '' }
}

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
			personsByLinkId: {},
			personDefaultsByLinkId: {},
			expandedLinkId: null,
			newPerson: emptyPerson(),
			editingPersonId: null,
			editPerson: emptyPerson(),
			savingPerson: false,
			documentTypes: DOCUMENT_TYPES,
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
			this.personsByLinkId = {}
			this.personDefaultsByLinkId = {}
			this.expandedLinkId = null
			this.cancelEditPerson()
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
		async togglePersons(card) {
			if (!card.link) return
			const linkId = card.link.id
			this.cancelEditPerson()
			this.newPerson = emptyPerson()
			if (this.expandedLinkId === linkId) {
				this.expandedLinkId = null
				return
			}
			this.expandedLinkId = linkId
			if (!this.personsByLinkId[linkId]) await this.loadPersons(linkId)
			if (!this.personDefaultsByLinkId[linkId]) await this.loadPersonDefaults(linkId)
		},
		async loadPersons(linkId) {
			try {
				const persons = await fetchContactPersons(linkId)
				this.personsByLinkId = { ...this.personsByLinkId, [linkId]: persons }
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async loadPersonDefaults(linkId) {
			try {
				const defaults = await fetchContactPersonDefaults(linkId)
				this.personDefaultsByLinkId = { ...this.personDefaultsByLinkId, [linkId]: defaults }
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			}
		},
		async setPersonDefault(linkId, documentType, contactPersonId) {
			this.savingPerson = true
			try {
				const defaults = await setContactPersonDefault(linkId, documentType, contactPersonId === '' ? null : Number(contactPersonId))
				this.personDefaultsByLinkId = { ...this.personDefaultsByLinkId, [linkId]: defaults }
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			} finally {
				this.savingPerson = false
			}
		},
		async addPerson(linkId) {
			if (!this.newPerson.name.trim()) return
			this.savingPerson = true
			try {
				await createContactPerson(linkId, {
					name: this.newPerson.name.trim(),
					position: this.newPerson.position.trim() || null,
					email: this.newPerson.email.trim() || null,
					phone: this.newPerson.phone.trim() || null,
				})
				this.newPerson = emptyPerson()
				await this.loadPersons(linkId)
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			} finally {
				this.savingPerson = false
			}
		},
		startEditPerson(person) {
			this.editingPersonId = person.id
			this.editPerson = {
				name: person.name,
				position: person.position ?? '',
				email: person.email ?? '',
				phone: person.phone ?? '',
			}
		},
		cancelEditPerson() {
			this.editingPersonId = null
			this.editPerson = emptyPerson()
		},
		async saveEditPerson(linkId) {
			if (!this.editPerson.name.trim()) return
			this.savingPerson = true
			try {
				await updateContactPerson(this.editingPersonId, {
					name: this.editPerson.name.trim(),
					position: this.editPerson.position.trim() || null,
					email: this.editPerson.email.trim() || null,
					phone: this.editPerson.phone.trim() || null,
				})
				this.cancelEditPerson()
				await this.loadPersons(linkId)
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			} finally {
				this.savingPerson = false
			}
		},
		async deletePersonConfirm(linkId, person) {
			if (!window.confirm(`Ansprechpartner „${person.name}“ löschen?`)) return
			this.savingPerson = true
			try {
				await deleteContactPerson(person.id)
				await this.loadPersons(linkId)
			} catch (e) {
				this.loadError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			} finally {
				this.savingPerson = false
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
.erp-contacts__persons-panel {
	flex-direction: column;
	align-items: stretch;
	gap: 8px;
	background: var(--color-background-hover);
	border-radius: 4px;
	padding: 12px;
}
.erp-contacts__persons-panel h4 {
	margin: 0;
}
.erp-contacts__persons-list {
	list-style: none;
	margin: 0;
	padding: 0;
}
.erp-contacts__persons-list li {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 0;
	border-bottom: 1px solid var(--color-border);
}
.erp-contacts__persons-list li > span:first-child {
	flex: 1 0 auto;
}
.erp-contacts__new-person {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}
.erp-contacts__new-person input {
	flex: 1 1 140px;
}
.erp-contacts__person-defaults {
	list-style: none;
	padding: 0;
	display: flex;
	flex-wrap: wrap;
	gap: 16px;
	margin: 8px 0 0;
}
.erp-contacts__person-defaults label {
	display: flex;
	flex-direction: column;
	gap: 4px;
	font-size: 13px;
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
