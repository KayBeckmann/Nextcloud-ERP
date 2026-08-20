<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string getType()
 * @method void setType(string $type)
 * @method int|null getProjectId()
 * @method void setProjectId(?int $projectId)
 * @method bool getActive()
 * @method void setActive(bool $active)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Warehouse extends Entity implements \JsonSerializable {
	protected string $name = '';
	protected string $type = 'central';
	protected ?int $projectId = null;
	protected bool $active = true;
	protected ?string $notes = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('active', 'boolean');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'type' => $this->getType(),
			'projectId' => $this->getProjectId(),
			'active' => $this->getActive(),
			'notes' => $this->getNotes(),
		];
	}
}
