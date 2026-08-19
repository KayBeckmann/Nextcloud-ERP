<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<PermissionEntry>
 */
class PermissionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_permissions', PermissionEntry::class);
	}

	/** @return PermissionEntry[] */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 */
	public function findOneByPrincipalAndResource(string $principalType, string $principalId, string $resourceType): ?PermissionEntry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('principal_type', $qb->createNamedParameter($principalType)))
			->andWhere($qb->expr()->eq('principal_id', $qb->createNamedParameter($principalId)))
			->andWhere($qb->expr()->eq('resource_type', $qb->createNamedParameter($resourceType)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
