<?php

declare(strict_types=1);
namespace OCA\ERP\Db;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
/** @extends QBMapper<InvoicePayment> */
class InvoicePaymentMapper extends QBMapper {
	public function __construct(IDBConnection $db) { parent::__construct($db, 'erp_invoice_payments', InvoicePayment::class); }
	/** @return InvoicePayment[] */
	public function findByInvoice(int $invoiceId): array { $qb=$this->db->getQueryBuilder(); $qb->select('*')->from($this->getTableName())->where($qb->expr()->eq('invoice_id',$qb->createNamedParameter($invoiceId,\PDO::PARAM_INT)))->orderBy('paid_at','ASC')->addOrderBy('id','ASC'); return $this->findEntities($qb); }
	/** Summe aller Zahlungen einer Rechnung — Grundlage für Invoice::paidAmount statt einer eigenständig gepflegten Summe. */
	public function sumByInvoice(int $invoiceId): float {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->sum('amount'), 'total')
			->from($this->getTableName())
			->where($qb->expr()->eq('invoice_id', $qb->createNamedParameter($invoiceId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$total = $result->fetchOne();
		$result->closeCursor();
		return $total === false ? 0.0 : (float) $total;
	}
}
