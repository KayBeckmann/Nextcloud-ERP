<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Projektpflicht für Angebote/Rechnungen/Gutschriften + Lieferscheine
 * (ADR-0015).
 */
class Version0009Date20260821100000 extends SimpleMigrationStep {
	/**
	 * Entfernt Angebote/Rechnungen ohne Projekt (samt Positionen und davon
	 * abhängigen Gutschriften) VOR der NOT-NULL-Umstellung — ausschließlich
	 * Testdaten aus der lokalen Entwicklungsumgebung, siehe ADR-0015.
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$db = \OC::$server->get(IDBConnection::class);

		$qb = $db->getQueryBuilder();
		$orphanQuoteIds = array_map('intval', $qb->select('id')->from('erp_quotes')
			->where($qb->expr()->isNull('project_id'))
			->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));

		foreach ($orphanQuoteIds as $quoteId) {
			$this->deleteWhere($db, 'erp_quote_positions', 'quote_id', $quoteId);
			$this->deleteWhere($db, 'erp_quote_groups', 'quote_id', $quoteId);
		}
		$this->deleteByIds($db, 'erp_quotes', $orphanQuoteIds);

		$qb2 = $db->getQueryBuilder();
		$orphanInvoiceIds = array_map('intval', $qb2->select('id')->from('erp_invoices')
			->where($qb2->expr()->isNull('project_id'))
			->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));

		foreach ($orphanInvoiceIds as $invoiceId) {
			$cn = $db->getQueryBuilder();
			$creditNoteIds = array_map('intval', $cn->select('id')->from('erp_credit_notes')
				->where($cn->expr()->eq('invoice_id', $cn->createNamedParameter($invoiceId, \PDO::PARAM_INT)))
				->executeQuery()->fetchAll(\PDO::FETCH_COLUMN));
			foreach ($creditNoteIds as $creditNoteId) {
				$this->deleteWhere($db, 'erp_credit_note_positions', 'credit_note_id', $creditNoteId);
			}
			$this->deleteByIds($db, 'erp_credit_notes', $creditNoteIds);
			$this->deleteWhere($db, 'erp_invoice_positions', 'invoice_id', $invoiceId);
		}
		$this->deleteByIds($db, 'erp_invoices', $orphanInvoiceIds);
	}

	private function deleteWhere(IDBConnection $db, string $table, string $column, int $value): void {
		$qb = $db->getQueryBuilder();
		$qb->delete($table)->where($qb->expr()->eq($column, $qb->createNamedParameter($value, \PDO::PARAM_INT)))->executeStatement();
	}

	/** @param list<int> $ids */
	private function deleteByIds(IDBConnection $db, string $table, array $ids): void {
		if ($ids === []) {
			return;
		}
		$qb = $db->getQueryBuilder();
		$qb->delete($table)->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))->executeStatement();
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$quotes = $schema->getTable('erp_quotes');
		$quotes->getColumn('project_id')->setNotnull(true);

		$invoices = $schema->getTable('erp_invoices');
		$invoices->getColumn('project_id')->setNotnull(true);

		$creditNotes = $schema->getTable('erp_credit_notes');
		if (!$creditNotes->hasColumn('project_id')) {
			// default => 0 befüllt bestehende Zeilen automatisch beim
			// ALTER TABLE — postSchemaChange() korrigiert danach auf den
			// echten Wert aus der jeweiligen Rechnung.
			$creditNotes->addColumn('project_id', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$creditNotes->addIndex(['project_id'], 'erp_cn_project_idx');
		}

		$this->createDeliveryNotes($schema);
		$this->createDeliveryNotePositions($schema);

		return $schema;
	}

	/** Backfill von erp_credit_notes.project_id aus der jeweiligen Rechnung. */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$db = \OC::$server->get(IDBConnection::class);

		$select = $db->getQueryBuilder();
		$rows = $select->select('id', 'invoice_id')->from('erp_credit_notes')->executeQuery()->fetchAll();

		foreach ($rows as $row) {
			$invSelect = $db->getQueryBuilder();
			$projectId = $invSelect->select('project_id')->from('erp_invoices')
				->where($invSelect->expr()->eq('id', $invSelect->createNamedParameter((int)$row['invoice_id'], \PDO::PARAM_INT)))
				->executeQuery()->fetchOne();
			if ($projectId === false) {
				continue;
			}
			$update = $db->getQueryBuilder();
			$update->update('erp_credit_notes')
				->set('project_id', $update->createNamedParameter((int)$projectId, \PDO::PARAM_INT))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], \PDO::PARAM_INT)))
				->executeStatement();
		}
	}

	private function createDeliveryNotes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_delivery_notes')) {
			return;
		}
		$table = $schema->createTable('erp_delivery_notes');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('delivery_note_number', Types::STRING, ['notnull' => false, 'length' => 20]);
		$table->addColumn('project_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('order_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft']);
		$table->addColumn('delivered_at', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['delivery_note_number'], 'erp_dn_number_idx');
		$table->addIndex(['project_id'], 'erp_dn_project_idx');
	}

	private function createDeliveryNotePositions(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_delivery_note_positions')) {
			return;
		}
		$table = $schema->createTable('erp_delivery_note_positions');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('delivery_note_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('position_type', Types::STRING, ['notnull' => true, 'length' => 16]);
		$table->addColumn('reference_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('description', Types::STRING, ['notnull' => true, 'length' => 500]);
		$table->addColumn('quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '1']);
		$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'Stk']);
		$table->addColumn('position_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['delivery_note_id'], 'erp_dnp_delivery_note_idx');
	}
}
