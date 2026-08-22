<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string|null getCustomerContactUid()
 * @method void setCustomerContactUid(?string $customerContactUid)
 * @method string|null getAssignedUserId()
 * @method void setAssignedUserId(?string $assignedUserId)
 * @method int|null getQuoteId()
 * @method void setQuoteId(?int $quoteId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Order extends Entity implements \JsonSerializable {
	protected int $projectId = 0;
	protected string $title = '';
	protected string $status = 'draft';
	protected ?string $description = null;
	protected ?string $customerContactUid = null;
	// Zugewiesener Mitarbeiter (ADR-0020), analog zu
	// Project::responsibleUserId — kein technischer Zusammenhang zur
	// Kalender-Zuweisung, bewusst getrennt.
	protected ?string $assignedUserId = null;
	protected ?int $quoteId = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('quoteId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'projectId' => $this->getProjectId(),
			'title' => $this->getTitle(),
			'status' => $this->getStatus(),
			'description' => $this->getDescription(),
			'customerContactUid' => $this->getCustomerContactUid(),
			'assignedUserId' => $this->getAssignedUserId(),
			'quoteId' => $this->getQuoteId(),
		];
	}
}
