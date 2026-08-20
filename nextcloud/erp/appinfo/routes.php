<?php

declare(strict_types=1);

// Eine einzelne serverseitige Route liefert die SPA-Hülle aus; das
// clientseitige vue-router-Routing übernimmt alle Unterpfade (History-Mode).
// api.php trägt die versionierte OCS-/REST-API (Phase 2).
return [
	'routes' => [
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
	],
];
