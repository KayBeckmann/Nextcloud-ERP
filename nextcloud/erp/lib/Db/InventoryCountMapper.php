<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<InventoryCount>
 */
class InventoryCountMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_inventory_counts', InventoryCount::class);
	}

	/** @return InventoryCount[] */
	public function findByInventory(int $inventoryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('inventory_id', $qb->createNamedParameter($inventoryId, \PDO::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findOne(int $inventoryId, int $articleId): ?InventoryCount {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('inventory_id', $qb->createNamedParameter($inventoryId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('article_id', $qb->createNamedParameter($articleId, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
