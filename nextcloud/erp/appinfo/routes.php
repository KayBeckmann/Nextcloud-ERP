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
	],
];
