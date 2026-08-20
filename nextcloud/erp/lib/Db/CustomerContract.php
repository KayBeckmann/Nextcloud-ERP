<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getCustomerContactUid()
 * @method void setCustomerContactUid(string $customerContactUid)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int|null getValidFrom()
 * @method void setValidFrom(?int $validFrom)
 * @method int|null getValidUntil()
 * @method void setValidUntil(?int $validUntil)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class CustomerContract extends Entity implements \JsonSerializable {
	protected string $customerContactUid = '';
	protected string $title = '';
	protected ?int $validFrom = null;
	protected ?int $validUntil = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('validFrom', 'integer');
		$this->addType('validUntil', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'customerContactUid' => $this->getCustomerContactUid(),
			'title' => $this->getTitle(),
			'validFrom' => $this->getValidFrom(),
			'validUntil' => $this->getValidUntil(),
			'notes' => $this->getNotes(),
		];
	}
}
