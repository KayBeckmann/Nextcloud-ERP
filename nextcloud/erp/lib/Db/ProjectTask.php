<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method bool getDone()
 * @method void setDone(bool $done)
 * @method int getPosition()
 * @method void setPosition(int $position)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class ProjectTask extends Entity implements \JsonSerializable {
	protected int $projectId = 0;
	protected string $title = '';
	protected bool $done = false;
	protected int $position = 0;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('projectId', 'integer');
		$this->addType('done', 'boolean');
		$this->addType('position', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'projectId' => $this->getProjectId(),
			'title' => $this->getTitle(),
			'done' => $this->getDone(),
			'position' => $this->getPosition(),
		];
	}
}
