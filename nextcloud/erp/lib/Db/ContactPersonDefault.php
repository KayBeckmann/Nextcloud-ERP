<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Ansprechpartner-Standard eines Firmenkontakts für einen Belegtyp
 * (ADR-0042), z. B. "Angebote gehen an den Projektleiter".
 *
 * @method int getContactLinkId()
 * @method void setContactLinkId(int $contactLinkId)
 * @method string getDocumentType()
 * @method void setDocumentType(string $documentType)
 * @method int getContactPersonId()
 * @method void setContactPersonId(int $contactPersonId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ContactPersonDefault extends Entity implements \JsonSerializable {
	protected int $contactLinkId = 0;
	protected string $documentType = '';
	protected int $contactPersonId = 0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('contactLinkId', 'integer');
		$this->addType('contactPersonId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'contactLinkId' => $this->getContactLinkId(),
			'documentType' => $this->getDocumentType(),
			'contactPersonId' => $this->getContactPersonId(),
		];
	}
}
