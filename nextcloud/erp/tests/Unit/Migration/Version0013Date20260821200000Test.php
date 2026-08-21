<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0013Date20260821200000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 *
 * Nur der Schema-Diff wird geprüft (wie bei allen anderen Migrationstests
 * in diesem Projekt) — ausschließlich neue Tabellen.
 */
class Version0013Date20260821200000Test extends TestCase {
	public function testChangeSchemaCreatesCostTables(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0013Date20260821200000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$this->assertTrue($resultSchema->hasTable('erp_cost_entries'));
		foreach (['category', 'title', 'monthly_amount', 'year', 'month', 'notes'] as $column) {
			$this->assertTrue($resultSchema->getTable('erp_cost_entries')->hasColumn($column), "missing column $column");
		}

		$this->assertTrue($resultSchema->hasTable('erp_cost_settings'));
		foreach (['year', 'productive_hours_per_year', 'material_surcharge_percent', 'product_surcharge_percent'] as $column) {
			$this->assertTrue($resultSchema->getTable('erp_cost_settings')->hasColumn($column), "missing column $column");
		}
		$this->assertTrue($resultSchema->getTable('erp_cost_settings')->getIndex('erp_cost_settings_year_idx')->isUnique());
	}
}
