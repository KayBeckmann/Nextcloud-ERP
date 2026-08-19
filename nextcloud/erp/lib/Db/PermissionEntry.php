<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getPrincipalType()
 * @method void setPrincipalType(string $principalType)
 * @method string getPrincipalId()
 * @method void setPrincipalId(string $principalId)
 * @method string getResourceType()
 * @method void setResourceType(string $resourceType)
 * @method string getPermission()
 * @method void setPermission(string $permission)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class PermissionEntry extends Entity implements \JsonSerializable {
	protected string $principalType = '';
	protected string $principalId = '';
	protected string $resourceType = '';
	protected string $permission = '';
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'principalType' => $this->getPrincipalType(),
			'principalId' => $this->getPrincipalId(),
			'resourceType' => $this->getResourceType(),
			'permission' => $this->getPermission(),
		];
	}
}
