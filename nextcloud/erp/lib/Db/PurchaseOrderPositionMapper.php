<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<PurchaseOrderPosition> */
class PurchaseOrderPositionMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_purchase_order_positions', PurchaseOrderPosition::class); }
	public function findById(int $id): ?PurchaseOrderPosition { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('id',$qb->createNamedParameter($id,\PDO::PARAM_INT))); try { return $this->findEntity($qb); } catch (DoesNotExistException) { return null; } }
	/** @return PurchaseOrderPosition[] */
	public function findByPurchaseOrder(int $purchaseOrderId): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('purchase_order_id',$qb->createNamedParameter($purchaseOrderId,\PDO::PARAM_INT)))->orderBy('position_order','ASC'); return $this->findEntities($qb); }
}
