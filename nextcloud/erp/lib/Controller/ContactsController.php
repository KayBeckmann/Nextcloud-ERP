<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Kunden/Lieferanten über Nextcloud Contacts (Roadmap Phase 3, ADR-0009).
 * Lesen/Schreiben wird über die ERP-Rechte-Matrix aus Phase 2 geprüft
 * (ResourceType::Kunden / ::Lieferanten je nach Rolle) statt über einen
 * separaten Admin-Check — zeigt, dass Rechte- und Integrationsschicht
 * zusammenspielen.
 */
class ContactsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ContactsService $contactsService,
		private PermissionService $permissionService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private static function resourceForRole(ContactRole $role): ResourceType {
		return $role === ContactRole::Customer ? ResourceType::Kunden : ResourceType::Lieferanten;
	}

	/**
	 * @throws OCSForbiddenException
	 */
	private function requireLevel(ResourceType $resource, PermissionLevel $required): void {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('No active user session');
		}
		$level = $this->permissionService->getEffectivePermission($user, $resource);
		if (!$level->atLeast($required)) {
			throw new OCSForbiddenException("Requires at least '{$required->value}' on '{$resource->value}'");
		}
	}

	private static function parseRole(string $role): ContactRole {
		$parsed = ContactRole::tryFrom($role);
		if ($parsed === null) {
			throw new OCSBadRequestException("role must be 'customer' or 'supplier'");
		}
		return $parsed;
	}

	#[NoAdminRequired]
	public function search(string $q = ''): DataResponse {
		return new DataResponse($this->contactsService->search($q));
	}

	/**
	 * @throws OCSBadRequestException|OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function links(string $role): DataResponse {
		$parsedRole = self::parseRole($role);
		$this->requireLevel(self::resourceForRole($parsedRole), PermissionLevel::Read);
		return new DataResponse($this->contactsService->listLinks($parsedRole));
	}

	/**
	 * @throws OCSBadRequestException|OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function createLink(
		string $contactUid,
		string $role,
		?string $referenceNumber = null,
		?int $paymentTermsDays = null,
		?string $notes = null,
	): DataResponse {
		$parsedRole = self::parseRole($role);
		$this->requireLevel(self::resourceForRole($parsedRole), PermissionLevel::Write);

		if ($contactUid === '') {
			throw new OCSBadRequestException('contactUid must not be empty');
		}

		try {
			$link = $this->contactsService->createLink($contactUid, $parsedRole, $referenceNumber, $paymentTermsDays, $notes);
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}

		return new DataResponse($link);
	}

/** @throws OCSNotFoundException|OCSForbiddenException */
	private function requireWriteOnExistingLink(int $id): void {
		$role = $this->contactsService->getLinkRole($id);
		if ($role === null) {
			throw new OCSNotFoundException("Contact link $id not found");
		}
		$this->requireLevel(self::resourceForRole($role), PermissionLevel::Write);
	}

	/**
	 * @throws OCSForbiddenException|OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function updateLink(int $id, ?string $referenceNumber = null, ?int $paymentTermsDays = null, ?string $notes = null): DataResponse {
		$this->requireWriteOnExistingLink($id);

		try {
			$link = $this->contactsService->updateLink($id, $referenceNumber, $paymentTermsDays, $notes);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Contact link $id not found");
		}

		return new DataResponse($link);
	}

	/**
	 * @throws OCSForbiddenException|OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function deleteLink(int $id): DataResponse {
		$this->requireWriteOnExistingLink($id);

		try {
			$this->contactsService->deleteLink($id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Contact link $id not found");
		}

		return new DataResponse([]);
	}
}
