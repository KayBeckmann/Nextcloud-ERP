<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\ContactPersonDefault;
use OCA\ERP\Db\ContactPersonDefaultMapper;
use OCA\ERP\Db\ContactPersonMapper;
use OCA\ERP\Documents\DocumentType;

/**
 * Ansprechpartner-Standard je Belegtyp eines Firmenkontakts (ADR-0042),
 * z. B. "Angebote/Aufträge/Lieferscheine gehen an den Projektleiter,
 * Rechnungen an die Buchhaltung". `ProjectContactOverrideService` kann
 * das pro Projekt überschreiben.
 */
class ContactPersonDefaultService {
	public function __construct(
		private ContactPersonDefaultMapper $mapper,
		private ContactLinkMapper $linkMapper,
		private ContactPersonMapper $personMapper,
	) {
	}

	/** @return array<string, int|null> documentType => contactPersonId, alle Belegtypen immer als Key vorhanden */
	public function getForLink(int $contactLinkId): array {
		$result = self::emptyMap();
		foreach ($this->mapper->findByContactLink($contactLinkId) as $default) {
			$result[$default->getDocumentType()] = $default->getContactPersonId();
		}
		return $result;
	}

	/**
	 * @throws \OutOfBoundsException wenn die Kunden-/Lieferanten-Verknüpfung nicht existiert
	 * @throws \InvalidArgumentException wenn der Ansprechpartner nicht zu dieser Verknüpfung gehört
	 */
	public function set(int $contactLinkId, DocumentType $documentType, ?int $contactPersonId): void {
		if ($this->linkMapper->findById($contactLinkId) === null) {
			throw new \OutOfBoundsException("Contact link $contactLinkId not found");
		}
		$existing = $this->mapper->findOneByLinkAndType($contactLinkId, $documentType->value);

		if ($contactPersonId === null) {
			if ($existing !== null) {
				$this->mapper->delete($existing);
			}
			return;
		}
		$this->assertPersonBelongsToLink($contactPersonId, $contactLinkId);

		$now = time();
		if ($existing !== null) {
			$existing->setContactPersonId($contactPersonId);
			$existing->setUpdatedAt($now);
			$this->mapper->update($existing);
			return;
		}
		$default = new ContactPersonDefault();
		$default->setContactLinkId($contactLinkId);
		$default->setDocumentType($documentType->value);
		$default->setContactPersonId($contactPersonId);
		$default->setCreatedAt($now);
		$default->setUpdatedAt($now);
		$this->mapper->insert($default);
	}

	/** @throws \InvalidArgumentException */
	private function assertPersonBelongsToLink(int $contactPersonId, int $contactLinkId): void {
		$person = $this->personMapper->findById($contactPersonId);
		if ($person === null || $person->getContactLinkId() !== $contactLinkId) {
			throw new \InvalidArgumentException("Contact person $contactPersonId does not belong to contact link $contactLinkId");
		}
	}

	/** @return array<string, null> */
	private static function emptyMap(): array {
		$map = [];
		foreach (DocumentType::cases() as $type) {
			$map[$type->value] = null;
		}
		return $map;
	}
}
