<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\ContactPersonDefaultMapper;
use OCA\ERP\Db\ContactPersonMapper;
use OCA\ERP\Db\ProjectContactOverrideMapper;
use OCA\ERP\Documents\DocumentType;

/**
 * Löst beim Erzeugen eines Belegs auf, welcher Ansprechpartner als
 * "z. Hd."-Zeile erscheinen soll (ADR-0042): zuerst die projektspezifische
 * Abweichung, sonst der Kundenstandard, sonst keiner. Reine Lesesicht —
 * Schreiben läuft über `ProjectContactOverrideService`/
 * `ContactPersonDefaultService`.
 *
 * Wird optional in QuoteService/OrderService/DeliveryNoteService/
 * InvoiceService injiziert (nullable, wie andere optionale Dienste in
 * diesem Projekt, z. B. ContactsService::$addressBookProvisioner) — ohne
 * ihn verhält sich die Beleg-Erzeugung exakt wie vor ADR-0042.
 */
class DocumentContactPersonResolver {
	public function __construct(
		private ProjectContactOverrideMapper $overrideMapper,
		private ContactPersonDefaultMapper $defaultMapper,
		private ContactLinkMapper $linkMapper,
		private ContactPersonMapper $personMapper,
	) {
	}

	/** Anzeigename des aufzulösenden Ansprechpartners, oder null wenn keiner greift. */
	public function resolveName(int $projectId, ?string $customerContactUid, DocumentType $documentType): ?string {
		$override = $this->overrideMapper->findOneByProjectAndType($projectId, $documentType->value);
		if ($override !== null) {
			$person = $this->personMapper->findById($override->getContactPersonId());
			if ($person !== null) {
				return $person->getName();
			}
		}

		if ($customerContactUid === null) {
			return null;
		}
		$link = $this->linkMapper->findOneByContactAndRole($customerContactUid, ContactRole::Customer->value);
		if ($link === null) {
			return null;
		}
		$default = $this->defaultMapper->findOneByLinkAndType($link->getId(), $documentType->value);
		if ($default === null) {
			return null;
		}
		$person = $this->personMapper->findById($default->getContactPersonId());
		return $person?->getName();
	}
}
