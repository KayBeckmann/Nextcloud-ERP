<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0010Date20260821140000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 *
 * Nur der Schema-Diff wird geprüft (wie bei allen anderen Migrationstests in
 * diesem Projekt) — diese Migration fügt ausschließlich neue, nullable
 * Spalten/Tabellen hinzu, es gibt keine preSchemaChange()/postSchemaChange()-
 * Datenmanipulation zu testen.
 */
class Version0010Date20260821140000Test extends TestCase {
	public function testChangeSchemaCreatesOrderPositionsAndNewColumns(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0010Date20260821140000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$this->assertTrue($resultSchema->hasTable('erp_order_positions'));
		foreach (['order_id', 'position_type', 'reference_id', 'description', 'quantity', 'unit', 'unit_price_net', 'vat_rate_percent', 'position_order'] as $column) {
			$this->assertTrue($resultSchema->getTable('erp_order_positions')->hasColumn($column), "missing column $column");
		}

		$this->assertTrue($resultSchema->getTable('erp_orders')->hasColumn('customer_contact_uid'));
		$this->assertTrue($resultSchema->getTable('erp_orders')->hasColumn('quote_id'));
		$this->assertTrue($resultSchema->getTable('erp_invoices')->hasColumn('delivery_note_id'));
		$this->assertTrue($resultSchema->getTable('erp_invoice_positions')->hasColumn('order_position_id'));
		$this->assertTrue($resultSchema->getTable('erp_delivery_note_positions')->hasColumn('order_position_id'));

		$this->assertFalse($resultSchema->getTable('erp_orders')->getColumn('customer_contact_uid')->getNotnull());
		$this->assertFalse($resultSchema->getTable('erp_order_positions')->getColumn('reference_id')->getNotnull());
	}
}
