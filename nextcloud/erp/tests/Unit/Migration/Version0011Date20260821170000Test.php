<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0011Date20260821170000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 *
 * Nur der Schema-Diff wird geprüft (wie bei allen anderen Migrationstests
 * in diesem Projekt) — ausschließlich neue, nullable Spalten/Tabellen.
 */
class Version0011Date20260821170000Test extends TestCase {
	public function testChangeSchemaCreatesGroupTablesAndGroupIdColumns(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0011Date20260821170000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		foreach ([
			'erp_order_groups' => 'order_id',
			'erp_invoice_groups' => 'invoice_id',
			'erp_delivery_note_groups' => 'delivery_note_id',
		] as $table => $parentColumn) {
			$this->assertTrue($resultSchema->hasTable($table), "missing table $table");
			foreach ([$parentColumn, 'title', 'position'] as $column) {
				$this->assertTrue($resultSchema->getTable($table)->hasColumn($column), "missing column $column on $table");
			}
		}

		foreach (['erp_order_positions', 'erp_invoice_positions', 'erp_delivery_note_positions'] as $table) {
			$this->assertTrue($resultSchema->getTable($table)->hasColumn('group_id'), "missing group_id on $table");
			$this->assertFalse($resultSchema->getTable($table)->getColumn('group_id')->getNotnull());
		}
	}
}
