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
	],
];
