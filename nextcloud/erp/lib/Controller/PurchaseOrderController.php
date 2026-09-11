<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\PurchaseOrderService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\IRequest;
use OCP\IUserSession;

/** Lieferantenbestellungen und bewusste Wareneingänge (Roadmap P1). */
class PurchaseOrderController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private PurchaseOrderService $purchaseOrderService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Lager;
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->purchaseOrderService->listAll());
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->purchaseOrderService->get($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Purchase order $id not found");
		}
	}

	/** @param list<array<string,mixed>> $positions @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(string $supplierContactUid, array $positions, ?string $supplierReference = null, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->purchaseOrderService->createDraft($supplierContactUid, $positions, $user->getUID(), $supplierReference, $notes));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function transition(int $id, string $status, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->purchaseOrderService->transitionStatus($id, $status, $user->getUID(), $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Purchase order $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function receive(int $positionId, float $quantity, int $warehouseId, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->purchaseOrderService->receive($positionId, $quantity, $warehouseId, $user->getUID(), $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Purchase order position $positionId not found");
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}
}
