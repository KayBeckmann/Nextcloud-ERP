<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string|null getProjectNumber()
 * @method void setProjectNumber(?string $projectNumber)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string|null getCustomerContactUid()
 * @method void setCustomerContactUid(?string $customerContactUid)
 * @method string|null getResponsibleUserId()
 * @method void setResponsibleUserId(?string $responsibleUserId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int|null getFilesFolderId()
 * @method void setFilesFolderId(?int $filesFolderId)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Project extends Entity implements \JsonSerializable {
	protected ?string $projectNumber = null;
	protected string $title = '';
	protected ?string $customerContactUid = null;
	protected ?string $responsibleUserId = null;
	protected string $status = 'draft';
	protected ?int $filesFolderId = null;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('filesFolderId', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'projectNumber' => $this->getProjectNumber(),
			'title' => $this->getTitle(),
			'customerContactUid' => $this->getCustomerContactUid(),
			'responsibleUserId' => $this->getResponsibleUserId(),
			'status' => $this->getStatus(),
			'filesFolderId' => $this->getFilesFolderId(),
			'notes' => $this->getNotes(),
		];
	}
}
