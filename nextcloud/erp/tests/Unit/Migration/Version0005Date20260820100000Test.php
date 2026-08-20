<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0005Date20260820100000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version0005Date20260820100000Test extends TestCase {
	public function testCreatesAllPhase5Tables(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0005Date20260820100000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$expected = [
			'erp_vat_rates' => ['name', 'percentage', 'is_default', 'active'],
			'erp_work_types' => ['name', 'hourly_rate', 'vat_rate_id'],
			'erp_articles' => ['name', 'manufacturer', 'manufacturer_article_no', 'unit', 'vat_rate_id'],
			'erp_article_supplier_prices' => ['article_id', 'supplier_contact_uid', 'purchase_price'],
			'erp_products' => ['name', 'vat_rate_id'],
			'erp_product_components' => ['product_id', 'article_id', 'quantity'],
			'erp_product_labor' => ['product_id', 'work_type_id', 'hours'],
			'erp_quotes' => ['quote_number', 'title', 'project_id', 'status', 'sent_at'],
			'erp_quote_groups' => ['quote_id', 'title', 'position'],
			'erp_quote_positions' => ['quote_id', 'group_id', 'position_type', 'unit_price_net', 'vat_rate_percent'],
		];

		foreach ($expected as $tableName => $columns) {
			$this->assertTrue($resultSchema->hasTable($tableName), "missing table $tableName");
			$table = $resultSchema->getTable($tableName);
			foreach ($columns as $column) {
				$this->assertTrue($table->hasColumn($column), "missing column $tableName.$column");
			}
		}

		$this->assertTrue($resultSchema->getTable('erp_quotes')->getIndex('erp_quote_number_idx')->isUnique());
	}
}
