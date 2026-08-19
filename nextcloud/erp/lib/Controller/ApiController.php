<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * Erster Baustein der API v1 (Roadmap Phase 2). Bewusst minimal: beweist,
 * dass Web-UI und API dieselbe App-Struktur/Auth teilen, ohne bereits die
 * volle Rechte-/Ressourcen-Struktur vorwegzunehmen.
 */
class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[CORS]
	public function status(): DataResponse {
		return new DataResponse([
			'app' => Application::APP_ID,
			'apiVersion' => 'v1',
			'appVersion' => '0.1.0',
			'status' => 'ok',
		]);
	}
}
