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
	],
];
