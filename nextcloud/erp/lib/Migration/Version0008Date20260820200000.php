<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Lager, Inventur, Bestellvorschläge (Roadmap Phase 8, ADR-0014).
 */
class Version0008Date20260820200000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createWarehouses($schema);
		$this->createStockLevels($schema);
		$this->createStockMovements($schema);
		$this->createInventories($schema);
		$this->createInventoryCounts($schema);

		return $schema;
	}

	private function createWarehouses(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_warehouses')) {
			return;
		}
		$table = $schema->createTable('erp_warehouses');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
		$table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'central']);
		$table->addColumn('project_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
	}

	private function createStockLevels(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_stock_levels')) {
			return;
		}
		$table = $schema->createTable('erp_stock_levels');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('article_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('warehouse_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('quantity_on_hand', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2, 'default' => '0']);
		$table->addColumn('quantity_reserved', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2, 'default' => '0']);
		$table->addColumn('min_quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2, 'default' => '0']);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['article_id', 'warehouse_id'], 'erp_sl_article_warehouse_idx');
		$table->addIndex(['warehouse_id'], 'erp_sl_warehouse_idx');
	}

	private function createStockMovements(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_stock_movements')) {
			return;
		}
		$table = $schema->createTable('erp_stock_movements');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('article_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('warehouse_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('quantity_delta', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2]);
		$table->addColumn('movement_type', Types::STRING, ['notnull' => true, 'length' => 20]);
		$table->addColumn('reference_type', Types::STRING, ['notnull' => false, 'length' => 32]);
		$table->addColumn('reference_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['article_id', 'warehouse_id'], 'erp_sm_article_warehouse_idx');
	}

	private function createInventories(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_inventories')) {
			return;
		}
		$table = $schema->createTable('erp_inventories');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('warehouse_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'open']);
		$table->addColumn('started_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('closed_at', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['warehouse_id'], 'erp_inv2_warehouse_idx');
	}

	private function createInventoryCounts(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_inventory_counts')) {
			return;
		}
		$table = $schema->createTable('erp_inventory_counts');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('inventory_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('article_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('counted_quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2, 'default' => '0']);
		$table->addColumn('expected_quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 12, 'scale' => 2, 'default' => '0']);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['inventory_id', 'article_id'], 'erp_ic_inventory_article_idx');
	}
}
