<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Contacts\ContactRole;
use OCP\IDBConnection;

/**
 * Persists immutable native-contact values at destructive deletion time.  This
 * deliberately stores one row for every business record that references the
 * UID; it never alters an already-issued document's rendering snapshot.
 */
class ContactHistorySnapshotService {
	public function __construct(private IDBConnection $db) {}

	/** @param array{displayName:string,addressLines:list<string>} $details */
	public function snapshotReferences(ContactRole $role, string $uid, array $details): void {
		$references = $this->references($role, $uid);
		foreach ($references as [$type, $id]) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('erp_contact_history_snapshots')
				->setValue('contact_uid', $qb->createNamedParameter($uid))
				->setValue('role', $qb->createNamedParameter($role->value))
				->setValue('entity_type', $qb->createNamedParameter($type))
				->setValue('entity_id', $qb->createNamedParameter($id, \PDO::PARAM_INT))
				->setValue('display_name', $qb->createNamedParameter($details['displayName']))
				->setValue('postal_address', $qb->createNamedParameter(implode("\n", $details['addressLines'])))
				->setValue('created_at', $qb->createNamedParameter(time(), \PDO::PARAM_INT));
			$qb->executeStatement();
		}
	}

	/** @return array{displayName:string,addressLines:list<string>}|null */
	public function latestFor(string $uid): ?array {
		$qb = $this->db->getQueryBuilder();
		$row = $qb->select('display_name', 'postal_address')->from('erp_contact_history_snapshots')
			->where($qb->expr()->eq('contact_uid', $qb->createNamedParameter($uid)))
			->orderBy('created_at', 'DESC')->setMaxResults(1)->executeQuery()->fetchAssociative();
		if ($row === false) return null;
		return ['displayName' => (string)$row['display_name'], 'addressLines' => array_values(array_filter(explode("\n", (string)$row['postal_address']), static fn (string $line): bool => $line !== ''))];
	}

	/** @return list<array{string,int}> */
	private function references(ContactRole $role, string $uid): array {
		$definitions = $role === ContactRole::Customer
			? [['project', 'erp_projects', 'customer_contact_uid'], ['quote', 'erp_quotes', 'customer_contact_uid'], ['order', 'erp_orders', 'customer_contact_uid'], ['invoice', 'erp_invoices', 'customer_contact_uid'], ['customer_contract', 'erp_customer_contracts', 'customer_contact_uid']]
			: [['purchase_order', 'erp_purchase_orders', 'supplier_contact_uid'], ['article_supplier_price', 'erp_article_supplier_prices', 'supplier_contact_uid']];
		$references = [];
		foreach ($definitions as [$type, $table, $column]) {
			$qb = $this->db->getQueryBuilder();
			$rows = $qb->select('id')->from($table)->where($qb->expr()->eq($column, $qb->createNamedParameter($uid)))->executeQuery()->fetchAllAssociative();
			foreach ($rows as $row) $references[] = [$type, (int)$row['id']];
		}
		if ($role === ContactRole::Customer) {
			// Delivery notes inherit their customer through the project; credit notes through the invoice.
			foreach ([['delivery_note', 'erp_delivery_notes', 'project_id', 'erp_projects'], ['credit_note', 'erp_credit_notes', 'invoice_id', 'erp_invoices']] as [$type, $table, $foreignKey, $target]) {
				$qb = $this->db->getQueryBuilder();
				$rows = $qb->select('source.id')->from($table, 'source')->innerJoin('source', $target, 'target', $qb->expr()->eq('source.' . $foreignKey, 'target.id'))
					->where($qb->expr()->eq('target.customer_contact_uid', $qb->createNamedParameter($uid)))->executeQuery()->fetchAllAssociative();
				foreach ($rows as $row) $references[] = [$type, (int)$row['id']];
			}
		}
		return $references;
	}
}
