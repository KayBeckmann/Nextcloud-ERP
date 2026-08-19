<template>
	<div class="erp-settings">
		<h2>Einstellungen</h2>

		<section class="erp-settings__section">
			<h3>Dateien &amp; Ordner</h3>
			<p class="erp-settings__hint">
				Legt die ERP-Ordnerstruktur in deinem persönlichen Nextcloud-Dateibereich an
				(<code>ERP/Projekte</code>, <code>ERP/Artikel</code>, …). Mehrfach ausführbar,
				bestehende Ordner werden nicht verändert. Bekannte Einschränkung: aktuell pro User,
				siehe <code>docs/adr/0009-contacts-calendar-files-integration.md</code>.
			</p>
			<button :disabled="loadingFolders" @click="loadFolders">
				{{ folders.length ? 'Erneut prüfen' : 'ERP-Ordnerstruktur anlegen/prüfen' }}
			</button>
			<p v-if="folderError" class="erp-settings__error">{{ folderError }}</p>
			<ul v-if="folders.length" class="erp-settings__folders">
				<li v-for="f in folders" :key="f.fileId">
					<a :href="openInFilesUrl(f.fileId)" target="_blank" rel="noopener">{{ f.name }}</a>
					<span class="erp-settings__path">{{ f.path }}</span>
				</li>
			</ul>
		</section>

		<section class="erp-settings__section">
			<h3>Weitere Einstellungen</h3>
			<p class="erp-settings__hint">
				MwSt.-Sätze, Nextcloud-Integrationsstatus und Lizenzinfos folgen in späteren
				Roadmap-Phasen (Phase 5 bzw. Phase 12).
			</p>
		</section>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { ensureErpFolder } from '../services/filesApi.js'

export default {
	name: 'EinstellungenView',
	data() {
		return {
			folders: [],
			loadingFolders: false,
			folderError: null,
		}
	},
	methods: {
		openInFilesUrl(fileId) {
			// Nextclouds generischer "öffne per Datei-ID"-Redirect.
			return generateUrl(`/f/${fileId}`)
		},
		async loadFolders() {
			this.loadingFolders = true
			this.folderError = null
			try {
				this.folders = await ensureErpFolder()
			} catch (e) {
				this.folderError = e?.response?.data?.ocs?.meta?.message ?? e.message ?? String(e)
			} finally {
				this.loadingFolders = false
			}
		},
	},
}
</script>

<style scoped>
.erp-settings {
	padding: 20px;
	max-width: 720px;
}
.erp-settings__section {
	margin-bottom: 28px;
}
.erp-settings__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 12px;
}
.erp-settings__error {
	color: var(--color-error-text, #c00);
}
.erp-settings__folders {
	list-style: none;
	margin: 12px 0 0;
	padding: 0;
}
.erp-settings__folders li {
	display: flex;
	gap: 12px;
	padding: 3px 0;
}
.erp-settings__path {
	color: var(--color-text-maxcontrast);
	font-size: 12px;
}
</style>
