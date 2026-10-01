<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<InvoiceDunningStep> */
class InvoiceDunningStepMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_invoice_dunning_steps', InvoiceDunningStep::class); }
	/** @return InvoiceDunningStep[] */
	public function findByInvoice(int $invoiceId): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('invoice_id',$qb->createNamedParameter($invoiceId,\PDO::PARAM_INT)))->orderBy('created_at','ASC')->addOrderBy('id','ASC'); return $this->findEntities($qb); }
}
