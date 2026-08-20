<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<StockMovement>
 */
class StockMovementMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_stock_movements', StockMovement::class);
	}

	/** @return StockMovement[] */
	public function findByArticleAndWarehouse(int $articleId, int $warehouseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('article_id', $qb->createNamedParameter($articleId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('warehouse_id', $qb->createNamedParameter($warehouseId, \PDO::PARAM_INT)))
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC');
		return $this->findEntities($qb);
	}
}
