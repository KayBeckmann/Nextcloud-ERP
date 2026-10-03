<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\ContactPerson;
use OCA\ERP\Db\ContactPersonMapper;

/**
 * Ansprechpartner einer Firma (ADR-0041) — reine ERP-Metadaten, hängen am
 * `erp_contact_links`-Eintrag statt an einer eigenen Nextcloud-Contact-UID.
 * Eigener, schlanker Dienst statt Erweiterung von ContactsService (das
 * bereits vCard-/CardDAV-Logik bündelt und fachlich unverwandt ist) —
 * dasselbe Trennungsmuster wie CustomerContractService neben
 * ContactsService (ADR-0037).
 */
class ContactPersonService {
	public function __construct(
		private ContactPersonMapper $mapper,
		private ContactLinkMapper $linkMapper,
	) {
	}

	/** @return ContactPerson[] */
	public function listForLink(int $contactLinkId): array {
		return $this->mapper->findByContactLink($contactLinkId);
	}

	/**
	 * Rolle (customer/supplier) der Firma, zu der dieser Ansprechpartner
	 * gehört — für das Rechte-Gate im Controller, analog
	 * ContactsService::getLinkRole(). `null`, wenn der Ansprechpartner
	 * nicht existiert.
	 */
	public function getRoleFor(int $personId): ?ContactRole {
		$person = $this->mapper->findById($personId);
		if ($person === null) {
			return null;
		}
		$link = $this->linkMapper->findById($person->getContactLinkId());
		return $link === null ? null : ContactRole::from($link->getRole());
	}

	/**
	 * @throws \OutOfBoundsException wenn die Kunden-/Lieferanten-Verknüpfung nicht existiert
	 * @throws \InvalidArgumentException wenn name leer ist
	 */
	public function create(int $contactLinkId, string $name, ?string $position, ?string $email, ?string $phone, ?string $notes): ContactPerson {
		if ($this->linkMapper->findById($contactLinkId) === null) {
			throw new \OutOfBoundsException("Contact link $contactLinkId not found");
		}
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('name must not be empty');
		}

		$now = time();
		$person = new ContactPerson();
		$person->setContactLinkId($contactLinkId);
		$person->setName($name);
		$person->setPosition($this->nullIfBlank($position));
		$person->setEmail($this->nullIfBlank($email));
		$person->setPhone($this->nullIfBlank($phone));
		$person->setNotes($this->nullIfBlank($notes));
		$person->setCreatedAt($now);
		$person->setUpdatedAt($now);
		return $this->mapper->insert($person);
	}

	/**
	 * @throws \OutOfBoundsException wenn der Ansprechpartner nicht existiert
	 * @throws \InvalidArgumentException wenn name leer ist
	 */
	public function update(int $id, string $name, ?string $position, ?string $email, ?string $phone, ?string $notes): ContactPerson {
		$person = $this->mapper->findById($id);
		if ($person === null) {
			throw new \OutOfBoundsException("Contact person $id not found");
		}
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('name must not be empty');
		}

		$person->setName($name);
		$person->setPosition($this->nullIfBlank($position));
		$person->setEmail($this->nullIfBlank($email));
		$person->setPhone($this->nullIfBlank($phone));
		$person->setNotes($this->nullIfBlank($notes));
		$person->setUpdatedAt(time());
		return $this->mapper->update($person);
	}

	/** @throws \OutOfBoundsException wenn der Ansprechpartner nicht existiert */
	public function delete(int $id): void {
		$person = $this->mapper->findById($id);
		if ($person === null) {
			throw new \OutOfBoundsException("Contact person $id not found");
		}
		$this->mapper->delete($person);
	}

	/** Cascade beim Löschen der ganzen Kunden-/Lieferanten-Verknüpfung (vom Controller aufgerufen). */
	public function deleteAllForLink(int $contactLinkId): void {
		foreach ($this->mapper->findByContactLink($contactLinkId) as $person) {
			$this->mapper->delete($person);
		}
	}

	private function nullIfBlank(?string $value): ?string {
		if ($value === null || trim($value) === '') {
			return null;
		}
		return trim($value);
	}
}
