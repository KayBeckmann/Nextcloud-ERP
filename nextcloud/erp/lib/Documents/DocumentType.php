<?php

declare(strict_types=1);

namespace OCA\ERP\Documents;

/**
 * Die kundenseitigen Belegtypen, für die ein Ansprechpartner-Standard
 * hinterlegt werden kann (ADR-0042). Bewusst ohne `purchase_order` — das
 * ist ein interner Beschaffungsbeleg an den Lieferanten, kein
 * Kunden-Beleg. Dieselben String-Werte wie
 * `DocumentLayoutService::DOCUMENT_TYPES` (ohne `purchase_order`), um
 * nicht ein zweites, inkompatibles Vokabular einzuführen.
 */
enum DocumentType: string {
	case Quote = 'quote';
	case Order = 'order';
	case DeliveryNote = 'delivery_note';
	case Invoice = 'invoice';
	case CreditNote = 'credit_note';
}
