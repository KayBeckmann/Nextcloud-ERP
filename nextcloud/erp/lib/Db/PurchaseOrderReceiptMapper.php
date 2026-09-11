<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<PurchaseOrderReceipt> */
class PurchaseOrderReceiptMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_purchase_order_receipts', PurchaseOrderReceipt::class); }
	/** @return PurchaseOrderReceipt[] */
	public function findByPosition(int $positionId): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('purchase_order_position_id',$qb->createNamedParameter($positionId,\PDO::PARAM_INT)))->orderBy('received_at','ASC'); return $this->findEntities($qb); }
}
