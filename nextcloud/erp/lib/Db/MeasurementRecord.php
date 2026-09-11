<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/** @method string getUuid() @method void setUuid(string $uuid) @method int getProjectId() @method void setProjectId(int $projectId) @method string getTitle() @method void setTitle(string $title) @method string getStatus() @method void setStatus(string $status) @method int getVersion() @method void setVersion(int $version) @method string getCreatedBy() @method void setCreatedBy(string $createdBy) @method int getCreatedAt() @method void setCreatedAt(int $createdAt) @method int getUpdatedAt() @method void setUpdatedAt(int $updatedAt) @method int|null getDeletedAt() @method void setDeletedAt(?int $deletedAt) */
class MeasurementRecord extends Entity implements \JsonSerializable {
	protected string $uuid = '';
	protected int $projectId = 0;
	protected string $title = '';
	protected string $status = 'draft';
	protected int $version = 1;
	protected string $createdBy = '';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;
	protected ?int $deletedAt = null;

	public function __construct() {
		foreach (['id', 'projectId', 'version', 'createdAt', 'updatedAt', 'deletedAt'] as $field) $this->addType($field, 'integer');
	}
	public function jsonSerialize(): array { return ['id' => $this->getId(), 'uuid' => $this->getUuid(), 'projectId' => $this->getProjectId(), 'title' => $this->getTitle(), 'status' => $this->getStatus(), 'version' => $this->getVersion(), 'createdBy' => $this->getCreatedBy(), 'createdAt' => $this->getCreatedAt(), 'updatedAt' => $this->getUpdatedAt(), 'deletedAt' => $this->getDeletedAt()]; }
}
