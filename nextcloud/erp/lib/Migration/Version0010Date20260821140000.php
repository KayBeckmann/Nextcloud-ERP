<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Belegkette Angebot -> Auftrag -> Lieferschein/Rechnung, Teilrechnungen
 * (ADR-0016). Ausschließlich neue, nullable Spalten/Tabellen — keine
 * Datenbereinigung nötig, deshalb kein preSchemaChange()/postSchemaChange()
 * wie bei Version0009.
 */
class Version0010Date20260821140000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createOrderPositions($schema);

		$orders = $schema->getTable('erp_orders');
		if (!$orders->hasColumn('customer_contact_uid')) {
			$orders->addColumn('customer_contact_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
		}
		if (!$orders->hasColumn('quote_id')) {
			$orders->addColumn('quote_id', Types::BIGINT, ['notnull' => false]);
		}

		$invoices = $schema->getTable('erp_invoices');
		if (!$invoices->hasColumn('delivery_note_id')) {
			$invoices->addColumn('delivery_note_id', Types::BIGINT, ['notnull' => false]);
		}

		$invoicePositions = $schema->getTable('erp_invoice_positions');
		if (!$invoicePositions->hasColumn('order_position_id')) {
			$invoicePositions->addColumn('order_position_id', Types::BIGINT, ['notnull' => false]);
		}

		$deliveryNotePositions = $schema->getTable('erp_delivery_note_positions');
		if (!$deliveryNotePositions->hasColumn('order_position_id')) {
			$deliveryNotePositions->addColumn('order_position_id', Types::BIGINT, ['notnull' => false]);
		}

		return $schema;
	}

	private function createOrderPositions(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_order_positions')) {
			return;
		}
		$table = $schema->createTable('erp_order_positions');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('order_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('position_type', Types::STRING, ['notnull' => true, 'length' => 16]);
		$table->addColumn('reference_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('description', Types::STRING, ['notnull' => true, 'length' => 500]);
		$table->addColumn('quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '1']);
		$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'Stk']);
		$table->addColumn('unit_price_net', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('vat_rate_percent', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2, 'default' => '0']);
		$table->addColumn('position_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['order_id'], 'erp_op_order_idx');
	}
}
