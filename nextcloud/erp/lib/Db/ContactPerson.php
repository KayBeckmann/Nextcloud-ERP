<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Ansprechpartner einer Firma, hängt an einem `erp_contact_links`-Eintrag
 * statt direkt an einer Nextcloud-Contact-UID (ADR-0041) — reine
 * ERP-Metadaten ohne eigene vCard.
 *
 * @method int getContactLinkId()
 * @method void setContactLinkId(int $contactLinkId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getPosition()
 * @method void setPosition(?string $position)
 * @method string|null getEmail()
 * @method void setEmail(?string $email)
 * @method string|null getPhone()
 * @method void setPhone(?string $phone)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ContactPerson extends Entity implements \JsonSerializable {
	protected int $contactLinkId = 0;
	protected string $name = '';
	protected ?string $position = null;
	protected ?string $email = null;
	protected ?string $phone = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('contactLinkId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'contactLinkId' => $this->getContactLinkId(),
			'name' => $this->getName(),
			'position' => $this->getPosition(),
			'email' => $this->getEmail(),
			'phone' => $this->getPhone(),
			'notes' => $this->getNotes(),
		];
	}
}
