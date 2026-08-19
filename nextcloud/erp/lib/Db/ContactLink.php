<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getContactUid()
 * @method void setContactUid(string $contactUid)
 * @method string getRole()
 * @method void setRole(string $role)
 * @method string|null getReferenceNumber()
 * @method void setReferenceNumber(?string $referenceNumber)
 * @method int|null getPaymentTermsDays()
 * @method void setPaymentTermsDays(?int $paymentTermsDays)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ContactLink extends Entity implements \JsonSerializable {
	protected string $contactUid = '';
	protected string $role = '';
	protected ?string $referenceNumber = null;
	protected ?int $paymentTermsDays = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('paymentTermsDays', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'contactUid' => $this->getContactUid(),
			'role' => $this->getRole(),
			'referenceNumber' => $this->getReferenceNumber(),
			'paymentTermsDays' => $this->getPaymentTermsDays(),
			'notes' => $this->getNotes(),
		];
	}
}
