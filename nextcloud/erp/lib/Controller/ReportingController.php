<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\ReportingService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Dashboard-Summary und Projekt-Gewinn/Verlust (Roadmap Phase 11, ADR-0019).
 * `summary()` gated über ResourceType::Dashboard (wie alle bisherigen
 * "aktivierten" Ressourcen aus dem seit Phase 1 reservierten Enum).
 * `projectProfitLoss()` gated bewusst über ResourceType::Projekte statt
 * Dashboard — dieselbe Berechtigung, mit der auch das Projekt selbst
 * sichtbar ist, deshalb hier manuell statt über resource()/requireLevel().
 */
class ReportingController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ReportingService $reportingService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Dashboard;
	}

	#[NoAdminRequired]
	public function summary(): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->reportingService->dashboardSummary($user->getUID()));
	}

	/** @throws OCSForbiddenException|OCSNotFoundException */
	#[NoAdminRequired]
	public function projectProfitLoss(int $projectId): DataResponse {
		$user = $this->requireUser();
		$level = $this->permissionService->getEffectivePermission($user, ResourceType::Projekte);
		if (!$level->atLeast(PermissionLevel::Read)) {
			throw new OCSForbiddenException("Requires at least 'read' on 'projekte'");
		}

		try {
			return new DataResponse($this->reportingService->projectProfitLoss($projectId));
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		}
	}
}
