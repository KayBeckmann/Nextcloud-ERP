<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\VatRateService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

class VatRateController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private VatRateService $vatRateService,
		PermissionService $permissionService,
		IUserSession $userSession,
	) {
		parent::__construct($appName, $request, $permissionService, $userSession);
	}

	protected function resource(): ResourceType {
		return ResourceType::Einstellungen;
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$this->requireLevel(PermissionLevel::Read);
		return new DataResponse($this->vatRateService->listAll());
	}

	/** @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function create(string $name, float $percentage, bool $isDefault = false, bool $active = true): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($name) === '') {
			throw new OCSBadRequestException('name must not be empty');
		}
		return new DataResponse($this->vatRateService->create($name, $percentage, $isDefault, $active));
	}

	/** @throws OCSBadRequestException|OCSNotFoundException */
	#[NoAdminRequired]
	public function update(int $id, string $name, float $percentage, bool $isDefault = false, bool $active = true): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		if (trim($name) === '') {
			throw new OCSBadRequestException('name must not be empty');
		}
		try {
			return new DataResponse($this->vatRateService->update($id, $name, $percentage, $isDefault, $active));
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("VAT rate $id not found");
		}
	}
}
