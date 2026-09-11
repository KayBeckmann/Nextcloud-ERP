<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<PurchaseOrder> */
class PurchaseOrderMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_purchase_orders', PurchaseOrder::class); }
	public function findById(int $id): ?PurchaseOrder { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('id',$qb->createNamedParameter($id,\PDO::PARAM_INT))); try { return $this->findEntity($qb); } catch (DoesNotExistException) { return null; } }
	/** @return PurchaseOrder[] */
	public function findAll(): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->orderBy('created_at','DESC'); return $this->findEntities($qb); }
}
