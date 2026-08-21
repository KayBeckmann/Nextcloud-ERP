<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\CostService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/** Betriebliche Kosten und Kalkulation (ADR-0018, Gate: KostenKalkulation). */
class CostController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CostService $costService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::KostenKalkulation;
	}

	/** Jahresübersicht: Kostenposten, Einstellungen, Summen, interner Stundensatz. */
	#[NoAdminRequired]
	public function overview(int $year): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->costService->getYearOverview($year));
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function createEntry(string $category, string $title, float $monthlyAmount, int $year, int $month, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->costService->createEntry($category, $title, $monthlyAmount, $year, $month, $notes));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function updateEntry(int $id, string $category, string $title, float $monthlyAmount, int $year, int $month, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->costService->updateEntry($id, $category, $title, $monthlyAmount, $year, $month, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Cost entry $id not found");
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function removeEntry(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->costService->removeEntry($id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Cost entry $id not found");
		}
		return new DataResponse([]);
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function updateSettings(int $year, float $productiveHoursPerYear, float $materialSurchargePercent, float $productSurchargePercent): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->costService->updateSettings($year, $productiveHoursPerYear, $materialSurchargePercent, $productSurchargePercent));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}
}
