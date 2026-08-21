<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string|null getQuoteNumber()
 * @method void setQuoteNumber(?string $quoteNumber)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string|null getCustomerContactUid()
 * @method void setCustomerContactUid(?string $customerContactUid)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int|null getValidUntil()
 * @method void setValidUntil(?int $validUntil)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int|null getSentAt()
 * @method void setSentAt(?int $sentAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Quote extends Entity implements \JsonSerializable {
	protected ?string $quoteNumber = null;
	protected string $title = '';
	// project_id ist seit ADR-0015 in der DB NOT NULL ohne Default; echte
	// Projekt-IDs starten nie bei 0, der Default hier ist ungefährlich
	// (siehe WorkType::$workTypeId-Kommentar, ADR-0011/Phase 5).
	protected int $projectId = 0;
	protected ?string $customerContactUid = null;
	protected string $status = 'draft';
	protected ?int $validUntil = null;
	protected ?string $notes = null;
	protected ?int $sentAt = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('validUntil', 'integer');
		$this->addType('sentAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'quoteNumber' => $this->getQuoteNumber(),
			'title' => $this->getTitle(),
			'projectId' => $this->getProjectId(),
			'customerContactUid' => $this->getCustomerContactUid(),
			'status' => $this->getStatus(),
			'validUntil' => $this->getValidUntil(),
			'notes' => $this->getNotes(),
			'sentAt' => $this->getSentAt(),
		];
	}
}
