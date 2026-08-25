<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/** Firmenprofil — Singleton unter Einstellungen (ADR-0022). */
class CompanyProfileController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private CompanyProfileService $companyProfileService,
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
		return new DataResponse($this->companyProfileService->get());
	}

	#[NoAdminRequired]
	public function update(
		?string $name = null,
		?string $addressLine = null,
		?string $postalCode = null,
		?string $city = null,
		?string $country = null,
		?string $taxId = null,
		?string $email = null,
		?string $phone = null,
		?string $footerText = null,
	): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		return new DataResponse($this->companyProfileService->update(
			$name,
			$addressLine,
			$postalCode,
			$city,
			$country,
			$taxId,
			$email,
			$phone,
			$footerText,
		));
	}
}
