<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Service\ErpFolderService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Files-Integration (Roadmap Phase 3, ADR-0009). Jeder eingeloggte User darf
 * seine eigene ERP-Ordnerstruktur sicherstellen/abrufen — kein zusätzliches
 * ERP-Rechte-Gate nötig, weil es (noch) keine geteilte Ablage ist.
 */
class FilesController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ErpFolderService $folderService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @throws OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function erpFolder(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('No active user session');
		}

		return new DataResponse($this->folderService->ensureStructure($user));
	}
}
