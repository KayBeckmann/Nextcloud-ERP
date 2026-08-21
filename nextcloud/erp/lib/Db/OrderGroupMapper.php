<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<OrderGroup>
 */
class OrderGroupMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_order_groups', OrderGroup::class);
	}

	/** @return OrderGroup[] */
	public function findByOrder(int $orderId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('order_id', $qb->createNamedParameter($orderId, \PDO::PARAM_INT)))
			->orderBy('position', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOne(int $orderId, int $id): ?OrderGroup {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('order_id', $qb->createNamedParameter($orderId, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
