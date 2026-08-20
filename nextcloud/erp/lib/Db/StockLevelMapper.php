<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<StockLevel>
 */
class StockLevelMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_stock_levels', StockLevel::class);
	}

	public function findOne(int $articleId, int $warehouseId): ?StockLevel {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('article_id', $qb->createNamedParameter($articleId, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('warehouse_id', $qb->createNamedParameter($warehouseId, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return StockLevel[] */
	public function findByWarehouse(int $warehouseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('warehouse_id', $qb->createNamedParameter($warehouseId, \PDO::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/** @return StockLevel[] */
	public function findByArticle(int $articleId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('article_id', $qb->createNamedParameter($articleId, \PDO::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/** @return StockLevel[] Alle Zeilen — Grundlage für PurchaseSuggestionService. */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}
}
