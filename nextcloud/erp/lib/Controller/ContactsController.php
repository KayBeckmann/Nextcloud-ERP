<?php

declare(strict_types=1);

namespace OCA\ERP\Controller;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\ResourceType;
use OCA\ERP\Service\ContactPersonService;
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
		private ContactPersonService $contactPersonService,
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

	/**
	 * Phase-14-Fix (ControllerRightsGateTest): `search()` durchsucht ALLE
	 * Nextcloud-Adressbücher (Name/E-Mail), unabhängig von ERP-Links — ohne
	 * Gate könnte jeder eingeloggte User damit die komplette Kontaktliste
	 * durchsuchen, auch ohne jedes ERP-Recht auf `kunden`/`lieferanten`.
	 * Reicht bewusst "read auf irgendeine der beiden Rollen" statt einer
	 * bestimmten Rolle, da die Suche selbst rollenunabhängig ist.
	 *
	 * @throws OCSForbiddenException
	 */
	private function requireReadOnAnyContactResource(): void {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new OCSForbiddenException('No active user session');
		}
		$hasKunden = $this->permissionService->getEffectivePermission($user, ResourceType::Kunden)->atLeast(PermissionLevel::Read);
		$hasLieferanten = $this->permissionService->getEffectivePermission($user, ResourceType::Lieferanten)->atLeast(PermissionLevel::Read);
		if (!$hasKunden && !$hasLieferanten) {
			throw new OCSForbiddenException("Requires at least 'read' on 'kunden' or 'lieferanten'");
		}
	}

	/** @throws OCSForbiddenException */
	#[NoAdminRequired]
	public function search(string $q = ''): DataResponse {
		$this->requireReadOnAnyContactResource();
		return new DataResponse($this->contactsService->search($q));
	}

	/**
	 * Anzeigename für eine bekannte Contact-UID nachschlagen — z. B. für
	 * Projekte, deren Kunde nicht zwingend als erp_contact_links-Eintrag
	 * existiert (ADR-0010).
	 */
	#[NoAdminRequired]
	public function resolve(string $uid): DataResponse {
		return new DataResponse(['uid' => $uid, 'displayName' => $this->contactsService->displayNameFor($uid)]);
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
	 * Liefert die live Karten des dedizierten Kunden- bzw. Lieferanten-
	 * Adressbuchs. Die Rollenprüfung läuft vor dem nativen CardDAV-Zugriff.
	 *
	 * @throws OCSBadRequestException|OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function cards(string $role): DataResponse {
		$parsedRole = self::parseRole($role);
		$this->requireLevel(self::resourceForRole($parsedRole), PermissionLevel::Read);
		try {
			return new DataResponse($this->contactsService->listCards($parsedRole));
		} catch (\OutOfBoundsException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
	}

	/**
	 * Erstellt eine vCard im rollenfesten, dedizierten Nextcloud-Adressbuch.
	 * Die Kontaktfelder werden ausschließlich an die öffentliche Contacts-API
	 * weitergereicht; im ERP bleibt nur eine optionale spätere Rollenverknüpfung.
	 *
	 * @throws OCSBadRequestException|OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function createCard(
		string $role,
		string $fullName,
		string $email = '',
		string $phone = '',
		string $address = '',
	): DataResponse {
		$parsedRole = self::parseRole($role);
		$this->requireLevel(self::resourceForRole($parsedRole), PermissionLevel::Write);
		try {
			$card = $this->contactsService->createCard($parsedRole, [
				'fullName' => $fullName,
				'email' => $email,
				'phone' => $phone,
				'address' => $address,
			]);
		} catch (\InvalidArgumentException|\OutOfBoundsException|\RuntimeException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
		return new DataResponse($card);
	}

	/**
	 * @throws OCSBadRequestException|OCSForbiddenException
	 */
	#[NoAdminRequired]
	public function updateCard(
		string $role,
		string $contactUid,
		string $fullName,
		string $email = '',
		string $phone = '',
		string $address = '',
	): DataResponse {
		$parsedRole = self::parseRole($role);
		$this->requireLevel(self::resourceForRole($parsedRole), PermissionLevel::Write);
		try {
			$card = $this->contactsService->updateCard($parsedRole, $contactUid, [
				'fullName' => $fullName,
				'email' => $email,
				'phone' => $phone,
				'address' => $address,
			]);
		} catch (\InvalidArgumentException|\OutOfBoundsException|\RuntimeException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
		return new DataResponse($card);
	}

	/** Native-card deletion is deliberately separate from unlinking metadata. */
	#[NoAdminRequired]
	public function deleteCard(string $role, string $contactUid): DataResponse {
		$parsedRole = self::parseRole($role);
		$this->requireLevel(self::resourceForRole($parsedRole), PermissionLevel::Write);
		try {
			$this->contactsService->deleteCard($parsedRole, $contactUid);
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		} catch (\RuntimeException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
		return new DataResponse([]);
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
			// Ansprechpartner (ADR-0041) sind reine Metadaten dieser
			// Verknüpfung ohne eigene vCard — sie verwaisen sonst.
			$this->contactPersonService->deleteAllForLink($id);
			$this->contactsService->deleteLink($id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Contact link $id not found");
		}

		return new DataResponse([]);
	}

	/** @throws OCSNotFoundException|OCSForbiddenException */
	private function requireReadOnExistingLink(int $contactLinkId): void {
		$role = $this->contactsService->getLinkRole($contactLinkId);
		if ($role === null) {
			throw new OCSNotFoundException("Contact link $contactLinkId not found");
		}
		$this->requireLevel(self::resourceForRole($role), PermissionLevel::Read);
	}

	/** @throws OCSNotFoundException|OCSForbiddenException */
	private function requireWriteOnExistingPerson(int $personId): void {
		$role = $this->contactPersonService->getRoleFor($personId);
		if ($role === null) {
			throw new OCSNotFoundException("Contact person $personId not found");
		}
		$this->requireLevel(self::resourceForRole($role), PermissionLevel::Write);
	}

	/**
	 * Ansprechpartner einer Firma (ADR-0041) — z. B. mehrere benannte
	 * Kontakte (Geschäftsführung, Buchhaltung, Projektleitung) unter
	 * demselben Kunden-/Lieferanten-Eintrag.
	 *
	 * @throws OCSForbiddenException|OCSNotFoundException
	 */
	#[NoAdminRequired]
	public function listPersons(int $contactLinkId): DataResponse {
		$this->requireReadOnExistingLink($contactLinkId);
		return new DataResponse($this->contactPersonService->listForLink($contactLinkId));
	}

	/** @throws OCSBadRequestException|OCSForbiddenException|OCSNotFoundException */
	#[NoAdminRequired]
	public function createPerson(
		int $contactLinkId,
		string $name,
		?string $position = null,
		?string $email = null,
		?string $phone = null,
		?string $notes = null,
	): DataResponse {
		$this->requireReadOnExistingLink($contactLinkId);
		$this->requireLevel(self::resourceForRole($this->contactsService->getLinkRole($contactLinkId)), PermissionLevel::Write);

		try {
			$person = $this->contactPersonService->create($contactLinkId, $name, $position, $email, $phone, $notes);
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		}

		return new DataResponse($person);
	}

	/** @throws OCSBadRequestException|OCSForbiddenException|OCSNotFoundException */
	#[NoAdminRequired]
	public function updatePerson(
		int $id,
		string $name,
		?string $position = null,
		?string $email = null,
		?string $phone = null,
		?string $notes = null,
	): DataResponse {
		$this->requireWriteOnExistingPerson($id);

		try {
			$person = $this->contactPersonService->update($id, $name, $position, $email, $phone, $notes);
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		} catch (\OutOfBoundsException $e) {
			throw new OCSNotFoundException($e->getMessage());
		}

		return new DataResponse($person);
	}

	/** @throws OCSForbiddenException|OCSNotFoundException */
	#[NoAdminRequired]
	public function deletePerson(int $id): DataResponse {
		$this->requireWriteOnExistingPerson($id);

		try {
			$this->contactPersonService->delete($id);
		} catch (\OutOfBoundsException) {
			throw new OCSNotFoundException("Contact person $id not found");
		}

		return new DataResponse([]);
	}
}
