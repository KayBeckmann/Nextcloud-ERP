const WRITE_RANK = { none: 0, read: 1, write: 2, approve: 3, admin: 4 }

function canWrite(permissions, resource) {
	return (WRITE_RANK[permissions?.[resource] ?? 'none'] ?? 0) >= WRITE_RANK.write
}

function restricted(resourceLabel) {
	return {
		state: 'restricted',
		detail: `Sie haben für ${resourceLabel} Leserecht, aber kein Schreibrecht. Wenden Sie sich an die Projektleitung.`,
		action: null,
	}
}

function stage(key, title, resource, resourceLabel, permissions, completed, waitingDetail, todoDetail, action, prerequisite = true) {
	if (!canWrite(permissions, resource)) {
		return { key, title, ...restricted(resourceLabel) }
	}
	if (!prerequisite) {
		return { key, title, state: 'blocked', detail: todoDetail, action: null }
	}
	if (completed) {
		return { key, title, state: 'complete', detail: waitingDetail, action }
	}
	return { key, title, state: 'todo', detail: todoDetail, action }
}

export function buildWorkflowStages({ project, quotes = [], orders = [], purchaseOrders = [], deliveryNotes = [], invoices = [], permissions = {} }) {
	const hasContact = Boolean(project?.customerContactUid)
	const acceptedQuote = quotes.some((quote) => quote.status === 'accepted')
	const hasQuote = quotes.length > 0
	const confirmedOrder = orders.some((order) => ['confirmed', 'in_progress', 'done'].includes(order.status))
	const hasOrder = orders.length > 0
	const projectPurchaseOrders = purchaseOrders.filter((detail) => detail.positions?.some((position) => String(position.projectId) === String(project?.id)))
	const receivedPurchaseOrder = projectPurchaseOrders.some((detail) => ['partially_received', 'received'].includes(detail.order?.status ?? detail.status))
	const sentPurchaseOrder = projectPurchaseOrders.some((detail) => (detail.order?.status ?? detail.status) === 'sent')
	const hasPurchaseOrder = projectPurchaseOrders.length > 0
	const issuedDeliveryNote = deliveryNotes.some((note) => note.status === 'issued')
	const hasDeliveryNote = deliveryNotes.length > 0
	const partialInvoices = invoices.filter((invoice) => invoice.type === 'partial')
	const issuedPartialInvoice = partialInvoices.some((invoice) => invoice.status === 'issued' || invoice.status === 'paid')
	const paidPartialInvoice = partialInvoices.some((invoice) => invoice.status === 'paid' || Number(invoice.paidAmount) > 0)
	const finalInvoices = invoices.filter((invoice) => invoice.type === 'final' || invoice.type === 'invoice')
	const issuedFinalInvoice = finalInvoices.some((invoice) => invoice.status === 'issued' || invoice.status === 'paid')
	const paidFinalInvoice = finalInvoices.some((invoice) => invoice.status === 'paid' || Number(invoice.paidAmount) > 0)

	const stages = [
		stage('contact', '1. Kunde verknüpfen', 'projekte', 'Projekte', permissions, hasContact, 'Kunde ist mit dem Projekt verknüpft.', 'Verknüpfen Sie zuerst einen Kunden in der Projektübersicht. Ohne Kunde können keine kundenbezogenen Belege erstellt werden.', { label: 'Projektübersicht öffnen', tab: 'Übersicht' }),
		{ key: 'project', title: '2. Projekt', state: project ? 'complete' : 'blocked', detail: project ? 'Projekt ist angelegt.' : 'Das Projekt konnte nicht geladen werden.', action: null },
		stage('quote', '3. Angebot', 'angebote', 'Angebote', permissions, acceptedQuote, acceptedQuote ? 'Angebot wurde angenommen.' : hasQuote ? 'Angebot vorhanden; bestätigen Sie Annahme oder Ablehnung im Angebotsdetail.' : 'Noch kein Angebot. Legen Sie ein Angebot für dieses Projekt an.', { label: 'Zu Angeboten', tab: 'Angebote' }, hasContact),
		stage('order', '4. Auftrag', 'auftraege', 'Aufträge', permissions, confirmedOrder, confirmedOrder ? 'Auftrag ist bestätigt bzw. in Bearbeitung.' : hasOrder ? 'Auftrag ist noch ein Entwurf. Bestätigen Sie ihn im Auftragsdetail.' : 'Ein angenommener Auftrag ist Voraussetzung. Erstellen Sie ihn aus dem angenommenen Angebot oder direkt im Projekt.', { label: 'Zu Aufträgen', tab: 'Aufträge' }, acceptedQuote),
		stage('purchaseOrder', '5. Lieferant & Bestellung', 'lager', 'Lager/Bestellungen', permissions, hasPurchaseOrder && sentPurchaseOrder, hasPurchaseOrder && sentPurchaseOrder ? 'Projektbezogene Bestellung ist als versendet markiert.' : hasPurchaseOrder ? 'Bestellung vorhanden. Prüfen, freigeben und als versendet markieren Sie sie unter Lager → Bestellungen.' : 'Falls Material benötigt wird: Lieferant verknüpfen und eine Bestellung mit Projektbezug unter Lager → Bestellvorschläge oder Bestellungen anlegen.', { label: 'Lager öffnen', route: 'lager' }, confirmedOrder),
		stage('receipt', '6. Wareneingang', 'lager', 'Lager/Bestellungen', permissions, receivedPurchaseOrder, receivedPurchaseOrder ? 'Mindestens ein projektbezogener Wareneingang ist gebucht.' : sentPurchaseOrder ? 'Buchen Sie den tatsächlichen Wareneingang erst nach Lieferung unter Lager → Bestellungen.' : 'Wareneingang ist erst möglich, wenn eine projektbezogene Bestellung als versendet markiert ist.', { label: 'Bestellungen öffnen', route: 'lager' }, hasPurchaseOrder),
		stage('deliveryNote', '7. Lieferschein', 'lieferscheine', 'Lieferscheine', permissions, issuedDeliveryNote, issuedDeliveryNote ? 'Lieferschein ist ausgestellt.' : hasDeliveryNote ? 'Lieferschein als Entwurf vorhanden. Prüfen und ausstellen Sie ihn im Lieferschein-Tab.' : 'Erstellen Sie nach bestätigtem Auftrag einen Lieferschein; Materialbestellungen sind nur bei Bedarf ein zusätzlicher Arbeitsschritt.', { label: 'Zu Lieferscheinen', tab: 'Lieferscheine' }, confirmedOrder),
		stage('partialInvoice', '8. Teilrechnung', 'rechnungen', 'Rechnungen', permissions, issuedPartialInvoice, paidPartialInvoice ? 'Teilrechnung ist bereits mit Zahlung erfasst.' : 'Teilrechnung ist ausgestellt; erfassen Sie bei Zahlung den Zahlungseingang im Rechnungsdetail.', 'Optional bei Teilleistung: Teilrechnung aus Auftrag oder Lieferschein erstellen, prüfen und ausstellen.', { label: 'Zu Rechnungen', tab: 'Rechnungen' }, confirmedOrder || issuedDeliveryNote),
		stage('finalInvoice', '9. Schlussrechnung', 'rechnungen', 'Rechnungen', permissions, issuedFinalInvoice, paidFinalInvoice ? 'Schlussrechnung ist bereits mit Zahlung erfasst.' : 'Schlussrechnung ist ausgestellt; erfassen Sie anschließend den Zahlungseingang.', 'Erstellen Sie nach Leistung bzw. Lieferschein die Schlussrechnung, prüfen Sie sie und stellen Sie sie aus.', { label: 'Zu Rechnungen', tab: 'Rechnungen' }, issuedDeliveryNote || confirmedOrder),
		stage('payment', '10. Zahlung', 'rechnungen', 'Rechnungen', permissions, paidFinalInvoice, 'Zahlungseingang zur Schlussrechnung ist erfasst.', issuedFinalInvoice ? 'Öffnen Sie die ausgestellte Schlussrechnung und erfassen Sie den tatsächlich eingegangenen Betrag.' : 'Ein Zahlungseingang kann erst nach dem Ausstellen einer Rechnung erfasst werden.', { label: 'Zu Rechnungen', tab: 'Rechnungen' }, issuedFinalInvoice),
	]

	for (const key of ['partialInvoice', 'finalInvoice']) {
		const invoiceStage = stages.find((entry) => entry.key === key)
		const isPaid = key === 'partialInvoice' ? paidPartialInvoice : paidFinalInvoice
		const isIssued = key === 'partialInvoice' ? issuedPartialInvoice : issuedFinalInvoice
		if (invoiceStage?.state === 'complete' && isIssued && !isPaid) {
			invoiceStage.state = 'waiting'
		}
	}
	return stages
}
