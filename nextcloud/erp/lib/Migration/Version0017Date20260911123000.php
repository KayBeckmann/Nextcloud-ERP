<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Lieferantenbestellungen und auditierbarer Wareneingang (Roadmap P1). */
class Version0017Date20260911123000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$this->createPurchaseOrders($schema);
		$this->createPositions($schema);
		$this->createStatusChanges($schema);
		$this->createReceipts($schema);
		return $schema;
	}

	private function createPurchaseOrders(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_purchase_orders')) {
			return;
		}
		$table = $schema->createTable('erp_purchase_orders');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('supplier_contact_uid', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('supplier_reference', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 24, 'default' => 'draft']);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['supplier_contact_uid'], 'erp_po_supplier_idx');
		$table->addIndex(['status'], 'erp_po_status_idx');
	}

	private function createPositions(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_purchase_order_positions')) {
			return;
		}
		$table = $schema->createTable('erp_purchase_order_positions');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('purchase_order_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('article_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('description', Types::STRING, ['notnull' => true, 'length' => 500]);
		$table->addColumn('quantity_ordered', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
		$table->addColumn('quantity_received', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2, 'default' => '0']);
		$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'Stk']);
		$table->addColumn('supplier_article_no', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('unit_purchase_price', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('currency', Types::STRING, ['notnull' => true, 'length' => 3, 'default' => 'EUR']);
		$table->addColumn('project_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('warehouse_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('position_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['purchase_order_id'], 'erp_pop_order_idx');
		$table->addIndex(['article_id'], 'erp_pop_article_idx');
	}

	private function createStatusChanges(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_purchase_order_status_changes')) {
			return;
		}
		$table = $schema->createTable('erp_purchase_order_status_changes');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('purchase_order_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('from_status', Types::STRING, ['notnull' => false, 'length' => 24]);
		$table->addColumn('to_status', Types::STRING, ['notnull' => true, 'length' => 24]);
		$table->addColumn('changed_by', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('changed_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['purchase_order_id'], 'erp_posc_order_idx');
	}

	private function createReceipts(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_purchase_order_receipts')) {
			return;
		}
		$table = $schema->createTable('erp_purchase_order_receipts');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('purchase_order_position_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('warehouse_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
		$table->addColumn('received_by', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('received_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['purchase_order_position_id'], 'erp_por_position_idx');
	}
}
