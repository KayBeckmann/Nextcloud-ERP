<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Rechnungen, Gutschriften, Zahlungsstatus (Roadmap Phase 7, ADR-0013).
 */
class Version0007Date20260820180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createInvoices($schema);
		$this->createInvoicePositions($schema);
		$this->createCreditNotes($schema);
		$this->createCreditNotePositions($schema);
		$this->createInvoiceCounters($schema);

		return $schema;
	}

	private function createInvoices(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_invoices')) {
			return;
		}
		$table = $schema->createTable('erp_invoices');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		// Erst bei issue() vergeben (ADR-0013) — deshalb kein DB-Default, echte
		// Nummern kollidieren nie mit dem PHP-Entity-Default '' (siehe
		// Invoice::$invoiceNumber-Kommentar für die Dirty-Tracking-Begründung).
		$table->addColumn('invoice_number', Types::STRING, ['notnull' => false, 'length' => 20]);
		$table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'invoice']);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft']);
		$table->addColumn('project_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('order_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('quote_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('customer_contact_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('issued_at', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('due_date', Types::STRING, ['notnull' => false, 'length' => 10]);
		$table->addColumn('paid_amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 1000]);
		$table->addColumn('document_file_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['invoice_number'], 'erp_inv_number_idx');
		$table->addIndex(['project_id'], 'erp_inv_project_idx');
		$table->addIndex(['status'], 'erp_inv_status_idx');
	}

	private function createInvoicePositions(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_invoice_positions')) {
			return;
		}
		$table = $schema->createTable('erp_invoice_positions');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('invoice_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('position_type', Types::STRING, ['notnull' => true, 'length' => 16]);
		$table->addColumn('reference_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('description', Types::STRING, ['notnull' => true, 'length' => 500]);
		$table->addColumn('quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '1']);
		$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'Stk']);
		$table->addColumn('unit_price_net', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('vat_rate_percent', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2, 'default' => '0']);
		$table->addColumn('position_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['invoice_id'], 'erp_invp_invoice_idx');
	}

	private function createCreditNotes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_credit_notes')) {
			return;
		}
		$table = $schema->createTable('erp_credit_notes');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('credit_note_number', Types::STRING, ['notnull' => false, 'length' => 20]);
		$table->addColumn('invoice_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft']);
		$table->addColumn('reason', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('cancels_invoice', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		$table->addColumn('issued_at', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['credit_note_number'], 'erp_cn_number_idx');
		$table->addIndex(['invoice_id'], 'erp_cn_invoice_idx');
	}

	private function createCreditNotePositions(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_credit_note_positions')) {
			return;
		}
		$table = $schema->createTable('erp_credit_note_positions');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('credit_note_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('description', Types::STRING, ['notnull' => true, 'length' => 500]);
		$table->addColumn('quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '1']);
		$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'Stk']);
		$table->addColumn('unit_price_net', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('vat_rate_percent', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2, 'default' => '0']);
		$table->addColumn('position_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['credit_note_id'], 'erp_cnp_credit_note_idx');
	}

	private function createInvoiceCounters(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_invoice_counters')) {
			return;
		}
		$table = $schema->createTable('erp_invoice_counters');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('year', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16]);
		$table->addColumn('next_sequence', Types::INTEGER, ['notnull' => true, 'default' => 1]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['year', 'kind'], 'erp_invc_year_kind_idx');
	}
}
