<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\WarehouseService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/** Lagerorte (ADR-0014, Gate: Lager). */
class WarehouseController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private WarehouseService $warehouseService,
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
		return new DataResponse($this->warehouseService->listAll());
	}

	/** @throws OCSNotFoundException */
	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		try {
			return new DataResponse($this->warehouseService->get($id));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Warehouse $id not found");
		}
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(string $name, string $type = 'central', ?int $projectId = null, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($name) === '') {
			throw new OCSBadRequestException('name must not be empty');
		}
		try {
			return new DataResponse($this->warehouseService->create($name, $type, $projectId, $notes));
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(int $id, string $name, bool $active = true, ?string $notes = null): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->warehouseService->update($id, $name, $active, $notes));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Warehouse $id not found");
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}
}
