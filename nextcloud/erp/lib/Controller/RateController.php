<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\RateService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

/** Standard-Verrechnungssätze + Satz-Auflösung (ADR-0012, Gate: BerechtigungenSaetze). */
class RateController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private RateService $rateService,
		private IUserManager $userManager,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::BerechtigungenSaetze;
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->rateService->listStandardRates());
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function set(int $workTypeId, ?string $principalType, ?string $principalId, float $rate): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if ($principalType !== null && !in_array($principalType, ['user', 'group'], true)) {
			throw new OCSBadRequestException("principalType must be 'user', 'group' or null");
		}
		if ($principalType !== null && ($principalId === null || trim($principalId) === '')) {
			throw new OCSBadRequestException('principalId is required when principalType is set');
		}
		return new DataResponse($this->rateService->setStandardRate($workTypeId, $principalType, $principalId, $rate));
	}

	/**
	 * Löst den effektiven Satz für einen User + Arbeitsart auf (Vorschau,
	 * z.B. für die Zeiterfassungs-UI). Gibt die Gruppen des Users selbst
	 * anhand seiner Nextcloud-Mitgliedschaften auf.
	 *
	 * @throws OCSBadRequestException
	 */
	#[NoAdminRequired]
	public function resolve(string $userId, int $workTypeId, ?string $customerContactUid = null, ?float $workTypeDefaultRate = null): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		$user = $this->userManager->get($userId);
		if ($user === null) {
			throw new OCSBadRequestException("Unknown userId '$userId'");
		}
		$groupIds = $this->permissionService->groupIdsFor($user);
		$rate = $this->rateService->resolveRate($userId, $groupIds, $workTypeId, $customerContactUid, $workTypeDefaultRate);
		return new DataResponse(['rate' => $rate]);
	}
}
