import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

export async function fetchDashboardSummary() {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/dashboard/summary'))
	return data.ocs.data
}

export async function fetchProjectProfitLoss(projectId) {
	const { data } = await axios.get(generateOcsUrl('apps/erp/api/v1/reports/projects/{projectId}/profit-loss', { projectId }))
	return data.ocs.data
}

// CSV-Export ist bewusst kein OCS-Endpunkt (roher Datei-Download,
// ADR-0019) — die URL wird direkt verlinkt statt per axios abgerufen,
// damit der Browser den nativen Download-Dialog übernimmt.
export function invoicesCsvExportUrl(from, to, status) {
	const params = new URLSearchParams()
	if (from) {
		params.set('from', from)
	}
	if (to) {
		params.set('to', to)
	}
	if (status) {
		params.set('status', status)
	}
	const query = params.toString()
	return generateUrl('/apps/erp/export/invoices.csv') + (query ? `?${query}` : '')
}

// DATEV-Buchungsstapel-Export (ADR-0026) — derselbe Download-statt-axios-
// Grund wie invoicesCsvExportUrl.
export function datevBuchungsstapelExportUrl(from, to, beraternummer, mandantennummer, debtorAccount) {
	const params = new URLSearchParams()
	params.set('from', from)
	params.set('to', to)
	params.set('beraternummer', beraternummer)
	params.set('mandantennummer', mandantennummer)
	if (debtorAccount) {
		params.set('debtorAccount', debtorAccount)
	}
	return generateUrl('/apps/erp/export/datev-buchungsstapel.csv') + `?${params.toString()}`
}
