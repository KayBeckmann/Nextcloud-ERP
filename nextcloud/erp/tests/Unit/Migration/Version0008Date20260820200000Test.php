<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0008Date20260820200000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version0008Date20260820200000Test extends TestCase {
	public function testCreatesAllPhase8Tables(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0008Date20260820200000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$expected = [
			'erp_warehouses' => ['name', 'type', 'project_id', 'active'],
			'erp_stock_levels' => ['article_id', 'warehouse_id', 'quantity_on_hand', 'quantity_reserved', 'min_quantity'],
			'erp_stock_movements' => ['article_id', 'warehouse_id', 'quantity_delta', 'movement_type', 'reference_type', 'reference_id', 'user_id'],
			'erp_inventories' => ['warehouse_id', 'status', 'started_at', 'closed_at', 'created_by'],
			'erp_inventory_counts' => ['inventory_id', 'article_id', 'counted_quantity', 'expected_quantity'],
		];

		foreach ($expected as $tableName => $columns) {
			$this->assertTrue($resultSchema->hasTable($tableName), "missing table $tableName");
			$table = $resultSchema->getTable($tableName);
			foreach ($columns as $column) {
				$this->assertTrue($table->hasColumn($column), "missing column $tableName.$column");
			}
		}

		$this->assertTrue($resultSchema->getTable('erp_stock_levels')->getIndex('erp_sl_article_warehouse_idx')->isUnique());
		$this->assertTrue($resultSchema->getTable('erp_inventory_counts')->getIndex('erp_ic_inventory_article_idx')->isUnique());
	}
}
