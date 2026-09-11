<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\DocumentLayoutService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\IRequest;
use OCP\IUserSession;

/** Settings API for fixed document text slots; HTML/CSS templates are intentionally unsupported. */
class DocumentLayoutController extends AbstractResourceController {
	public function __construct(
		string $appName,
		IRequest $request,
		private DocumentLayoutService $documentLayoutService,
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
		return new DataResponse($this->documentLayoutService->listAll());
	}

	/** @param array<string,mixed> $layout @throws OCSBadRequestException */
	#[NoAdminRequired]
	public function update(string $documentType, array $layout): DataResponse {
		$this->requireLevel(PermissionLevel::Write);
		try {
			return new DataResponse($this->documentLayoutService->update($documentType, $layout));
		} catch (\InvalidArgumentException $exception) {
			throw new OCSBadRequestException($exception->getMessage());
		}
	}
}
