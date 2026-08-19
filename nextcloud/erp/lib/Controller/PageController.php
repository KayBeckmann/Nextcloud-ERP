<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Liefert die SPA-Hülle aus. Alle Unterpfade laufen client-seitig über
	 * vue-router (siehe appinfo/routes.php, catch-all '/{path}').
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'index');
	}
}
