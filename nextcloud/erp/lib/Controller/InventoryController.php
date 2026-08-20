<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\InventoryService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\IRequest;
use OCP\IUserSession;

/** Inventuren (ADR-0014, Gate: Lager). */
class InventoryController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private InventoryService $inventoryService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Lager;
	}

	#[NoAdminRequired]
	public function index(int $warehouseId): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->inventoryService->listForWarehouse($warehouseId));
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->inventoryService->getFull($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Inventory $id not found");
		}
	}

	#[NoAdminRequired]
	public function start(int $warehouseId, ?string $notes = null): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		return new DataResponse($this->inventoryService->start($warehouseId, $user->getUID(), $notes));
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function recordCount(int $inventoryId, int $articleId, float $countedQuantity): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->inventoryService->recordCount($inventoryId, $articleId, $countedQuantity));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Inventory $inventoryId not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}

	/** @throws OCSNotFoundException|OCSPreconditionFailedException */
	#[NoAdminRequired]
	public function close(int $id): DataResponse {
		$user = $this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->inventoryService->close($id, $user->getUID()));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Inventory $id not found");
		} catch (\DomainException $e) {
			throw new OCSPreconditionFailedException($e->getMessage());
		}
	}
}
