<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Rechte-Matrix-API (Roadmap Phase 2, ADR-0008).
 *
 * principals()/matrix()/setMatrixEntry() bleiben bewusst OHNE
 * #[NoAdminRequired] — Nextclouds Standardverhalten verlangt dann einen
 * Nextcloud-Instanz-Admin und lehnt alle anderen Requests automatisch mit
 * einer standardisierten 403-OCS-Antwort ab (kein eigener Rechte-Check nötig).
 * Nur me() ist bewusst für jeden eingeloggten User freigegeben.
 */
class PermissionsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private PermissionService $permissionService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	public function principals(): DataResponse {
		return new DataResponse($this->permissionService->listPrincipals());
	}

	/**
	 * Nextcloud-User suchen, für Auswahl-Dropdowns wie "Verantwortlicher" im
	 * Projekt (ADR-0015) — bewusst mit #[NoAdminRequired], jeder eingeloggte
	 * User darf andere User zum Zuweisen suchen (analog zu
	 * ContactsController::search()).
	 */
	#[NoAdminRequired]
	public function users(string $q = ''): DataResponse {
		return new DataResponse($this->permissionService->searchUsers($q));
	}

	public function matrix(): DataResponse {
		return new DataResponse([
			'resourceTypes' => ResourceType::values(),
			'permissionLevels' => array_map(static fn (PermissionLevel $l) => $l->value, PermissionLevel::cases()),
			'entries' => $this->permissionService->listMatrix(),
		]);
	}

	/**
	 * @throws OCSBadRequestException
	 */
	public function setMatrixEntry(string $principalType, string $principalId, string $resourceType, string $permission): DataResponse {
		$resource = ResourceType::tryFrom($resourceType);
		if ($resource === null) {
			throw new OCSBadRequestException("Unknown resourceType: $resourceType");
		}
		$level = PermissionLevel::tryFrom($permission);
		if ($level === null) {
			throw new OCSBadRequestException("Unknown permission level: $permission");
		}
		if ($principalType !== 'user' && $principalType !== 'group') {
			throw new OCSBadRequestException("principalType must be 'user' or 'group'");
		}
		if ($principalId === '') {
			throw new OCSBadRequestException('principalId must not be empty');
		}

		$this->permissionService->setPermission($principalType, $principalId, $resource, $level);

		return new DataResponse([
			'principalType' => $principalType,
			'principalId' => $principalId,
			'resourceType' => $resource->value,
			'permission' => $level->value,
		]);
	}

	/**
	 * @throws OCSBadRequestException
	 */
	#[NoAdminRequired]
	public function me(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			// Sollte durch die Nextcloud-Auth vor Erreichen dieser Methode
			// bereits abgefangen sein — Absicherung gegen inkonsistenten Zustand.
			throw new OCSBadRequestException('No active user session');
		}

		return new DataResponse([
			'userId' => $user->getUID(),
			'permissions' => $this->permissionService->getEffectivePermissions($user),
		]);
	}
}
