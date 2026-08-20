<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\CustomerContractService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/** Kundenverträge + vertragliche Verrechnungssätze (ADR-0012, Gate: BerechtigungenSaetze). */
class CustomerContractController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CustomerContractService $contractService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::BerechtigungenSaetze;
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function index(string $customerContactUid): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		if (trim($customerContactUid) === '') {
			throw new OCSBadRequestException('customerContactUid must not be empty');
		}
		return new DataResponse($this->contractService->listForCustomer($customerContactUid));
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->contractService->getWithRates($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Customer contract $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(string $customerContactUid, string $title, ?int $validFrom = null, ?int $validUntil = null, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($customerContactUid) === '' || trim($title) === '') {
			throw new OCSBadRequestException('customerContactUid and title must not be empty');
		}
		return new DataResponse($this->contractService->create($customerContactUid, $title, $validFrom, $validUntil, $notes));
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function addRate(int $contractId, int $workTypeId, ?string $principalType, ?string $principalId, float $rate): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if ($principalType !== null && !in_array($principalType, ['user', 'group'], true)) {
			throw new OCSBadRequestException("principalType must be 'user', 'group' or null");
		}
		try {
			return new DataResponse($this->contractService->addRate($contractId, $workTypeId, $principalType, $principalId, $rate));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Customer contract $contractId not found");
		}
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function removeRate(int $contractId, int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			$this->contractService->removeRate($contractId, $id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Contract rate $id not found");
		}
		return new DataResponse([]);
	}
}
