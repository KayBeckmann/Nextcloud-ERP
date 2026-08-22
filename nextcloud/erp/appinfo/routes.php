<?php

declare(strict_types=1);

// Eine einzelne serverseitige Route liefert die SPA-Hülle aus; das
// clientseitige vue-router-Routing übernimmt alle Unterpfade (History-Mode).
// api.php trägt die versionierte OCS-/REST-API (Phase 2).
return [
	'routes' => [
		// CSV-Export für Steuerberater/Buchhaltung (Roadmap Phase 11,
		// ADR-0019) — roher Datei-Download statt JSON-Envelope, deshalb
		// außerhalb des 'ocs'-Blocks. Muss VOR page#index registriert
		// werden: dessen Catch-all-Requirement '.*' würde sonst jeden
		// nachfolgenden Pfad abfangen (Routen werden in
		// Registrierungsreihenfolge geprüft).
		['name' => 'reportExport#invoicesCsv', 'url' => '/export/invoices.csv', 'verb' => 'GET'],
		// Eine einzige Route mit optionalem Pfad (Default '') statt zweier
		// gleichnamiger Routen — sonst schlägt die Navigations-URL-Generierung
		// fehl, weil Symfony für 'erp.page.index' die zuletzt registrierte
		// Route (mit Pflichtparameter 'path') verwendet.
		['name' => 'page#index', 'url' => '/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.*'], 'defaults' => ['path' => '']],
	],
	// Erster, minimaler Baustein der in Phase 2 auszubauenden API v1 —
	// beweist früh, dass Web-UI und API dieselbe App-Struktur teilen.
	'ocs' => [
		['name' => 'api#status', 'url' => '/api/v1/status', 'verb' => 'GET'],
		// Rechte-Matrix (Roadmap Phase 2, ADR-0008) — principals/matrix ohne
		// #[NoAdminRequired] (Nextcloud-Admin-only per Default), 'me' bewusst offen.
		['name' => 'permissions#principals', 'url' => '/api/v1/permissions/principals', 'verb' => 'GET'],
		['name' => 'permissions#matrix', 'url' => '/api/v1/permissions/matrix', 'verb' => 'GET'],
		['name' => 'permissions#users', 'url' => '/api/v1/permissions/users', 'verb' => 'GET'],
		['name' => 'permissions#resolveUser', 'url' => '/api/v1/permissions/users/resolve', 'verb' => 'GET'],
		['name' => 'permissions#setMatrixEntry', 'url' => '/api/v1/permissions/matrix', 'verb' => 'PUT'],
		['name' => 'permissions#me', 'url' => '/api/v1/permissions/me', 'verb' => 'GET'],
		// Contacts-Integration (Roadmap Phase 3, ADR-0009) — Rechte-Gate über
		// ResourceType::Kunden/::Lieferanten statt eigenem Admin-Check.
		['name' => 'contacts#search', 'url' => '/api/v1/contacts/search', 'verb' => 'GET'],
		['name' => 'contacts#resolve', 'url' => '/api/v1/contacts/resolve', 'verb' => 'GET'],
		['name' => 'contacts#links', 'url' => '/api/v1/contacts/links/{role}', 'verb' => 'GET'],
		['name' => 'contacts#createLink', 'url' => '/api/v1/contacts/links', 'verb' => 'POST'],
		['name' => 'contacts#updateLink', 'url' => '/api/v1/contacts/links/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'contacts#deleteLink', 'url' => '/api/v1/contacts/links/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		// Calendar-Integration (Roadmap Phase 3, ADR-0009).
		['name' => 'calendar#calendars', 'url' => '/api/v1/calendar/calendars', 'verb' => 'GET'],
		['name' => 'calendar#createEvent', 'url' => '/api/v1/calendar/events', 'verb' => 'POST'],
		['name' => 'calendar#links', 'url' => '/api/v1/calendar/links', 'verb' => 'GET'],
		// Files-Integration (Roadmap Phase 3, ADR-0009).
		['name' => 'files#erpFolder', 'url' => '/api/v1/files/erp-folder', 'verb' => 'GET'],
		// Projektkern (Roadmap Phase 4, ADR-0010).
		['name' => 'project#index', 'url' => '/api/v1/projects', 'verb' => 'GET'],
		['name' => 'project#create', 'url' => '/api/v1/projects', 'verb' => 'POST'],
		['name' => 'project#show', 'url' => '/api/v1/projects/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'project#update', 'url' => '/api/v1/projects/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'task#index', 'url' => '/api/v1/projects/{projectId}/tasks', 'verb' => 'GET', 'requirements' => ['projectId' => '\d+']],
		['name' => 'task#create', 'url' => '/api/v1/projects/{projectId}/tasks', 'verb' => 'POST', 'requirements' => ['projectId' => '\d+']],
		['name' => 'task#update', 'url' => '/api/v1/projects/{projectId}/tasks/{id}', 'verb' => 'PUT', 'requirements' => ['projectId' => '\d+', 'id' => '\d+']],
		['name' => 'task#destroy', 'url' => '/api/v1/projects/{projectId}/tasks/{id}', 'verb' => 'DELETE', 'requirements' => ['projectId' => '\d+', 'id' => '\d+']],
		['name' => 'order#index', 'url' => '/api/v1/projects/{projectId}/orders', 'verb' => 'GET', 'requirements' => ['projectId' => '\d+']],
		['name' => 'order#create', 'url' => '/api/v1/projects/{projectId}/orders', 'verb' => 'POST', 'requirements' => ['projectId' => '\d+']],
		['name' => 'order#update', 'url' => '/api/v1/projects/{projectId}/orders/{id}', 'verb' => 'PUT', 'requirements' => ['projectId' => '\d+', 'id' => '\d+']],
		// Auftragspositionen + Belegkette (ADR-0016).
		['name' => 'order#show', 'url' => '/api/v1/orders/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'order#createFromQuote', 'url' => '/api/v1/orders/from-quote', 'verb' => 'POST'],
		['name' => 'order#addGroup', 'url' => '/api/v1/orders/{orderId}/groups', 'verb' => 'POST', 'requirements' => ['orderId' => '\d+']],
		['name' => 'order#addPosition', 'url' => '/api/v1/orders/{orderId}/positions', 'verb' => 'POST', 'requirements' => ['orderId' => '\d+']],
		['name' => 'order#removePosition', 'url' => '/api/v1/orders/{orderId}/positions/{id}', 'verb' => 'DELETE', 'requirements' => ['orderId' => '\d+', 'id' => '\d+']],
		// Artikel/Produkte/Angebote (Roadmap Phase 5, ADR-0011).
		['name' => 'vat_rate#index', 'url' => '/api/v1/vat-rates', 'verb' => 'GET'],
		['name' => 'vat_rate#create', 'url' => '/api/v1/vat-rates', 'verb' => 'POST'],
		['name' => 'vat_rate#update', 'url' => '/api/v1/vat-rates/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'work_type#index', 'url' => '/api/v1/work-types', 'verb' => 'GET'],
		['name' => 'work_type#create', 'url' => '/api/v1/work-types', 'verb' => 'POST'],
		['name' => 'work_type#update', 'url' => '/api/v1/work-types/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'article#index', 'url' => '/api/v1/articles', 'verb' => 'GET'],
		['name' => 'article#create', 'url' => '/api/v1/articles', 'verb' => 'POST'],
		['name' => 'article#show', 'url' => '/api/v1/articles/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'article#update', 'url' => '/api/v1/articles/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'article#addSupplierPrice', 'url' => '/api/v1/articles/{articleId}/supplier-prices', 'verb' => 'POST', 'requirements' => ['articleId' => '\d+']],
		['name' => 'article#removeSupplierPrice', 'url' => '/api/v1/articles/{articleId}/supplier-prices/{priceId}', 'verb' => 'DELETE', 'requirements' => ['articleId' => '\d+', 'priceId' => '\d+']],
		['name' => 'product#index', 'url' => '/api/v1/products', 'verb' => 'GET'],
		['name' => 'product#create', 'url' => '/api/v1/products', 'verb' => 'POST'],
		['name' => 'product#show', 'url' => '/api/v1/products/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'product#update', 'url' => '/api/v1/products/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'product#addComponent', 'url' => '/api/v1/products/{productId}/components', 'verb' => 'POST', 'requirements' => ['productId' => '\d+']],
		['name' => 'product#removeComponent', 'url' => '/api/v1/products/{productId}/components/{id}', 'verb' => 'DELETE', 'requirements' => ['productId' => '\d+', 'id' => '\d+']],
		['name' => 'product#addLabor', 'url' => '/api/v1/products/{productId}/labor', 'verb' => 'POST', 'requirements' => ['productId' => '\d+']],
		['name' => 'product#removeLabor', 'url' => '/api/v1/products/{productId}/labor/{id}', 'verb' => 'DELETE', 'requirements' => ['productId' => '\d+', 'id' => '\d+']],
		['name' => 'quote#index', 'url' => '/api/v1/quotes', 'verb' => 'GET'],
		['name' => 'quote#create', 'url' => '/api/v1/quotes', 'verb' => 'POST'],
		['name' => 'quote#show', 'url' => '/api/v1/quotes/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'quote#update', 'url' => '/api/v1/quotes/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'quote#addGroup', 'url' => '/api/v1/quotes/{quoteId}/groups', 'verb' => 'POST', 'requirements' => ['quoteId' => '\d+']],
		['name' => 'quote#addPosition', 'url' => '/api/v1/quotes/{quoteId}/positions', 'verb' => 'POST', 'requirements' => ['quoteId' => '\d+']],
		['name' => 'quote#removePosition', 'url' => '/api/v1/quotes/{quoteId}/positions/{id}', 'verb' => 'DELETE', 'requirements' => ['quoteId' => '\d+', 'id' => '\d+']],
		// Zeitwirtschaft: Verrechnungssätze + Kundenverträge (Roadmap Phase 6, ADR-0012).
		['name' => 'rate#index', 'url' => '/api/v1/rates/standard', 'verb' => 'GET'],
		['name' => 'rate#set', 'url' => '/api/v1/rates/standard', 'verb' => 'POST'],
		['name' => 'rate#resolve', 'url' => '/api/v1/rates/resolve', 'verb' => 'GET'],
		['name' => 'customer_contract#index', 'url' => '/api/v1/contracts', 'verb' => 'GET'],
		['name' => 'customer_contract#create', 'url' => '/api/v1/contracts', 'verb' => 'POST'],
		['name' => 'customer_contract#show', 'url' => '/api/v1/contracts/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'customer_contract#addRate', 'url' => '/api/v1/contracts/{contractId}/rates', 'verb' => 'POST', 'requirements' => ['contractId' => '\d+']],
		['name' => 'customer_contract#removeRate', 'url' => '/api/v1/contracts/{contractId}/rates/{id}', 'verb' => 'DELETE', 'requirements' => ['contractId' => '\d+', 'id' => '\d+']],
		// Zeiterfassung (Roadmap Phase 6, ADR-0012).
		['name' => 'time_entry#index', 'url' => '/api/v1/time-entries', 'verb' => 'GET'],
		['name' => 'time_entry#create', 'url' => '/api/v1/time-entries', 'verb' => 'POST'],
		['name' => 'time_entry#show', 'url' => '/api/v1/time-entries/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'time_entry#update', 'url' => '/api/v1/time-entries/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'time_entry#destroy', 'url' => '/api/v1/time-entries/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		// Zeitkonto (Soll/Ist) + Arbeitszeitmodell (Roadmap Phase 6, ADR-0012).
		['name' => 'time_account#index', 'url' => '/api/v1/time-account', 'verb' => 'GET'],
		['name' => 'time_account#schedule', 'url' => '/api/v1/work-schedule', 'verb' => 'GET'],
		['name' => 'time_account#setSchedule', 'url' => '/api/v1/work-schedule', 'verb' => 'PUT'],
		// Abwesenheiten (Roadmap Phase 6, ADR-0012).
		['name' => 'absence#types', 'url' => '/api/v1/absence-types', 'verb' => 'GET'],
		['name' => 'absence#createType', 'url' => '/api/v1/absence-types', 'verb' => 'POST'],
		['name' => 'absence#index', 'url' => '/api/v1/absence-requests', 'verb' => 'GET'],
		['name' => 'absence#create', 'url' => '/api/v1/absence-requests', 'verb' => 'POST'],
		['name' => 'absence#approve', 'url' => '/api/v1/absence-requests/{id}/approve', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'absence#reject', 'url' => '/api/v1/absence-requests/{id}/reject', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'absence#calendarLinks', 'url' => '/api/v1/absence-requests/{id}/calendar-links', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		// Überstunden (Roadmap Phase 6, ADR-0012).
		['name' => 'overtime_action#index', 'url' => '/api/v1/overtime-actions', 'verb' => 'GET'],
		['name' => 'overtime_action#create', 'url' => '/api/v1/overtime-actions', 'verb' => 'POST'],
		['name' => 'overtime_action#approve', 'url' => '/api/v1/overtime-actions/{id}/approve', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'overtime_action#complete', 'url' => '/api/v1/overtime-actions/{id}/complete', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'overtime_action#reject', 'url' => '/api/v1/overtime-actions/{id}/reject', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		// Rechnungen (Roadmap Phase 7, ADR-0013).
		['name' => 'invoice#index', 'url' => '/api/v1/invoices', 'verb' => 'GET'],
		['name' => 'invoice#create', 'url' => '/api/v1/invoices', 'verb' => 'POST'],
		['name' => 'invoice#createFromQuote', 'url' => '/api/v1/invoices/from-quote', 'verb' => 'POST'],
		['name' => 'invoice#createFromOrder', 'url' => '/api/v1/invoices/from-order', 'verb' => 'POST'],
		['name' => 'invoice#createFromDeliveryNote', 'url' => '/api/v1/invoices/from-delivery-note', 'verb' => 'POST'],
		['name' => 'invoice#show', 'url' => '/api/v1/invoices/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'invoice#addGroup', 'url' => '/api/v1/invoices/{invoiceId}/groups', 'verb' => 'POST', 'requirements' => ['invoiceId' => '\d+']],
		['name' => 'invoice#addPosition', 'url' => '/api/v1/invoices/{invoiceId}/positions', 'verb' => 'POST', 'requirements' => ['invoiceId' => '\d+']],
		['name' => 'invoice#removePosition', 'url' => '/api/v1/invoices/{invoiceId}/positions/{id}', 'verb' => 'DELETE', 'requirements' => ['invoiceId' => '\d+', 'id' => '\d+']],
		['name' => 'invoice#issue', 'url' => '/api/v1/invoices/{id}/issue', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'invoice#recordPayment', 'url' => '/api/v1/invoices/{id}/payments', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		// Gutschriften (Roadmap Phase 7, ADR-0013).
		['name' => 'credit_note#index', 'url' => '/api/v1/credit-notes', 'verb' => 'GET'],
		['name' => 'credit_note#show', 'url' => '/api/v1/credit-notes/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'credit_note#createFullCancellation', 'url' => '/api/v1/credit-notes/full-cancellation', 'verb' => 'POST'],
		['name' => 'credit_note#createPartial', 'url' => '/api/v1/credit-notes/partial', 'verb' => 'POST'],
		['name' => 'credit_note#addPosition', 'url' => '/api/v1/credit-notes/{creditNoteId}/positions', 'verb' => 'POST', 'requirements' => ['creditNoteId' => '\d+']],
		['name' => 'credit_note#issue', 'url' => '/api/v1/credit-notes/{id}/issue', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		// Lieferscheine (ADR-0015).
		['name' => 'delivery_note#index', 'url' => '/api/v1/delivery-notes', 'verb' => 'GET'],
		['name' => 'delivery_note#create', 'url' => '/api/v1/delivery-notes', 'verb' => 'POST'],
		['name' => 'delivery_note#createFromOrder', 'url' => '/api/v1/delivery-notes/from-order', 'verb' => 'POST'],
		['name' => 'delivery_note#show', 'url' => '/api/v1/delivery-notes/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'delivery_note#addGroup', 'url' => '/api/v1/delivery-notes/{deliveryNoteId}/groups', 'verb' => 'POST', 'requirements' => ['deliveryNoteId' => '\d+']],
		['name' => 'delivery_note#addPosition', 'url' => '/api/v1/delivery-notes/{deliveryNoteId}/positions', 'verb' => 'POST', 'requirements' => ['deliveryNoteId' => '\d+']],
		['name' => 'delivery_note#removePosition', 'url' => '/api/v1/delivery-notes/{deliveryNoteId}/positions/{id}', 'verb' => 'DELETE', 'requirements' => ['deliveryNoteId' => '\d+', 'id' => '\d+']],
		['name' => 'delivery_note#issue', 'url' => '/api/v1/delivery-notes/{id}/issue', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		// Lager (Roadmap Phase 8, ADR-0014).
		['name' => 'warehouse#index', 'url' => '/api/v1/warehouses', 'verb' => 'GET'],
		['name' => 'warehouse#create', 'url' => '/api/v1/warehouses', 'verb' => 'POST'],
		['name' => 'warehouse#show', 'url' => '/api/v1/warehouses/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'warehouse#update', 'url' => '/api/v1/warehouses/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'stock#index', 'url' => '/api/v1/stock', 'verb' => 'GET'],
		['name' => 'stock#movements', 'url' => '/api/v1/stock/movements', 'verb' => 'GET'],
		['name' => 'stock#setMinQuantity', 'url' => '/api/v1/stock/min-quantity', 'verb' => 'PUT'],
		['name' => 'stock#recordMovement', 'url' => '/api/v1/stock/movements', 'verb' => 'POST'],
		['name' => 'stock#transfer', 'url' => '/api/v1/stock/transfer', 'verb' => 'POST'],
		['name' => 'stock#reserve', 'url' => '/api/v1/stock/reserve', 'verb' => 'POST'],
		['name' => 'stock#release', 'url' => '/api/v1/stock/release', 'verb' => 'POST'],
		['name' => 'stock#purchaseSuggestions', 'url' => '/api/v1/stock/purchase-suggestions', 'verb' => 'GET'],
		// Fuhrpark (Roadmap Phase 9, ADR-0017).
		['name' => 'vehicle#index', 'url' => '/api/v1/vehicles', 'verb' => 'GET'],
		['name' => 'vehicle#create', 'url' => '/api/v1/vehicles', 'verb' => 'POST'],
		['name' => 'vehicle#show', 'url' => '/api/v1/vehicles/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'vehicle#update', 'url' => '/api/v1/vehicles/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'vehicle#addFuelLog', 'url' => '/api/v1/vehicles/{vehicleId}/fuel-logs', 'verb' => 'POST', 'requirements' => ['vehicleId' => '\d+']],
		['name' => 'vehicle#removeFuelLog', 'url' => '/api/v1/vehicles/{vehicleId}/fuel-logs/{id}', 'verb' => 'DELETE', 'requirements' => ['vehicleId' => '\d+', 'id' => '\d+']],
		['name' => 'vehicle#uploadReceipt', 'url' => '/api/v1/vehicles/{vehicleId}/fuel-logs/{fuelLogId}/receipt', 'verb' => 'POST', 'requirements' => ['vehicleId' => '\d+', 'fuelLogId' => '\d+']],
		// Betriebliche Kosten und Kalkulation (Roadmap Phase 10, ADR-0018).
		['name' => 'cost#overview', 'url' => '/api/v1/costs/overview', 'verb' => 'GET'],
		['name' => 'cost#createEntry', 'url' => '/api/v1/costs/entries', 'verb' => 'POST'],
		['name' => 'cost#updateEntry', 'url' => '/api/v1/costs/entries/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'cost#removeEntry', 'url' => '/api/v1/costs/entries/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		['name' => 'cost#updateSettings', 'url' => '/api/v1/costs/settings', 'verb' => 'PUT'],
		['name' => 'inventory#index', 'url' => '/api/v1/inventories', 'verb' => 'GET'],
		['name' => 'inventory#start', 'url' => '/api/v1/inventories', 'verb' => 'POST'],
		['name' => 'inventory#show', 'url' => '/api/v1/inventories/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'inventory#recordCount', 'url' => '/api/v1/inventories/{inventoryId}/counts', 'verb' => 'POST', 'requirements' => ['inventoryId' => '\d+']],
		['name' => 'inventory#close', 'url' => '/api/v1/inventories/{id}/close', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		// Auswertungen, Dashboard (Roadmap Phase 11, ADR-0019) — der
		// CSV-Export liegt bewusst im 'routes'-Block oben (roher Download).
		['name' => 'reporting#summary', 'url' => '/api/v1/dashboard/summary', 'verb' => 'GET'],
		['name' => 'reporting#projectProfitLoss', 'url' => '/api/v1/reports/projects/{projectId}/profit-loss', 'verb' => 'GET', 'requirements' => ['projectId' => '\d+']],
	],
];
