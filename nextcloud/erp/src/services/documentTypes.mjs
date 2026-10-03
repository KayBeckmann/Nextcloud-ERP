// Dieselben Belegtyp-Werte wie OCA\ERP\Documents\DocumentType (ADR-0042) —
// für Ansprechpartner-Standards/-Overrides. Bewusst ohne 'purchase_order',
// 'credit_note' ist dabei (symmetrisch zu Rechnungen), auch wenn sie im
// Alltag seltener einen eigenen Ansprechpartner braucht.
export const DOCUMENT_TYPES = [
	{ value: 'quote', label: 'Angebote' },
	{ value: 'order', label: 'Aufträge' },
	{ value: 'delivery_note', label: 'Lieferscheine' },
	{ value: 'invoice', label: 'Rechnungen' },
	{ value: 'credit_note', label: 'Gutschriften' },
]
