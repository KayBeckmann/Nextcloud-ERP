<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<PurchaseOrderStatusChange> */
class PurchaseOrderStatusChangeMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_purchase_order_status_changes', PurchaseOrderStatusChange::class); }
	/** @return PurchaseOrderStatusChange[] */
	public function findByPurchaseOrder(int $purchaseOrderId): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('purchase_order_id',$qb->createNamedParameter($purchaseOrderId,\PDO::PARAM_INT)))->orderBy('changed_at','ASC')->addOrderBy('id','ASC'); return $this->findEntities($qb); }
}
