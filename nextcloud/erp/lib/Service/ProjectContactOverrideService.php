<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\ContactPersonMapper;
use OCA\ERP\Db\ProjectContactOverride;
use OCA\ERP\Db\ProjectContactOverrideMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Documents\DocumentType;

/**
 * Projektspezifische Abweichung vom Ansprechpartner-Standard des Kunden
 * (ADR-0042) — "in diesem Projekt geht die Rechnung ausnahmsweise an
 * Herrn X statt an die Buchhaltung". Fehlt eine Zeile für einen
 * Belegtyp, gilt der Kundenstandard (`ContactPersonDefaultService`) —
 * es gibt bewusst keinen expliziten "niemand"-Zustand auf Projektebene.
 */
class ProjectContactOverrideService {
	public function __construct(
		private ProjectContactOverrideMapper $mapper,
		private ProjectMapper $projectMapper,
		private ContactLinkMapper $linkMapper,
		private ContactPersonMapper $personMapper,
	) {
	}

	/** @return array<string, int|null> documentType => contactPersonId, alle Belegtypen immer als Key vorhanden */
	public function getForProject(int $projectId): array {
		$result = self::emptyMap();
		foreach ($this->mapper->findByProject($projectId) as $override) {
			$result[$override->getDocumentType()] = $override->getContactPersonId();
		}
		return $result;
	}

	/**
	 * @throws \OutOfBoundsException wenn Projekt oder Ansprechpartner nicht existiert
	 * @throws \InvalidArgumentException wenn der Ansprechpartner nicht zum Kunden des Projekts gehört
	 *     (kein verknüpfter Kunde am Projekt zählt dabei auch als "gehört nicht dazu")
	 */
	public function set(int $projectId, DocumentType $documentType, ?int $contactPersonId): void {
		$project = $this->projectMapper->findById($projectId);
		if ($project === null) {
			throw new \OutOfBoundsException("Project $projectId not found");
		}
		$existing = $this->mapper->findOneByProjectAndType($projectId, $documentType->value);

		if ($contactPersonId === null) {
			if ($existing !== null) {
				$this->mapper->delete($existing);
			}
			return;
		}
		$this->assertPersonBelongsToProjectCustomer($contactPersonId, $project->getCustomerContactUid());

		$now = time();
		if ($existing !== null) {
			$existing->setContactPersonId($contactPersonId);
			$existing->setUpdatedAt($now);
			$this->mapper->update($existing);
			return;
		}
		$override = new ProjectContactOverride();
		$override->setProjectId($projectId);
		$override->setDocumentType($documentType->value);
		$override->setContactPersonId($contactPersonId);
		$override->setCreatedAt($now);
		$override->setUpdatedAt($now);
		$this->mapper->insert($override);
	}

	/** @throws \InvalidArgumentException */
	private function assertPersonBelongsToProjectCustomer(int $contactPersonId, ?string $customerContactUid): void {
		$person = $this->personMapper->findById($contactPersonId);
		$link = $customerContactUid === null ? null : $this->linkMapper->findOneByContactAndRole($customerContactUid, ContactRole::Customer->value);
		if ($person === null || $link === null || $person->getContactLinkId() !== $link->getId()) {
			throw new \InvalidArgumentException("Contact person $contactPersonId does not belong to this project's customer");
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
