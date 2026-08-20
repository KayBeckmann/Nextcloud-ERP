<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ArticleSupplierPrice>
 */
class ArticleSupplierPriceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_article_supplier_prices', ArticleSupplierPrice::class);
	}

	/** @return ArticleSupplierPrice[] */
	public function findByArticle(int $articleId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('article_id', $qb->createNamedParameter($articleId, \PDO::PARAM_INT)))
			->orderBy('purchase_price', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOne(int $articleId, int $id): ?ArticleSupplierPrice {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)))
			->andWhere($qb->expr()->eq('article_id', $qb->createNamedParameter($articleId, \PDO::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
