<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0009Date20260821100000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 *
 * Nur der Schema-Diff wird hier geprüft (wie bei allen anderen
 * Migrationstests in diesem Projekt) — preSchemaChange()/postSchemaChange()
 * manipulieren echte Daten für eine einmalige Übergangssituation
 * (Waisen-Bereinigung, project_id-Backfill) und wurden während der
 * Entwicklung manuell gegen die echte Docker-DB verifiziert (siehe
 * docs/status.md), lassen sich aber nach einer bereits angewendeten
 * Migration nicht sinnvoll erneut gegen den "Vorher"-Zustand testen, weil
 * die NOT-NULL-Constraints dann schon real durchgesetzt werden.
 */
class Version0009Date20260821100000Test extends TestCase {
	public function testChangeSchemaCreatesDeliveryNoteTablesAndNotNullColumns(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0009Date20260821100000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$this->assertTrue($resultSchema->hasTable('erp_delivery_notes'));
		$this->assertTrue($resultSchema->hasTable('erp_delivery_note_positions'));
		foreach (['delivery_note_number', 'project_id', 'order_id', 'status', 'delivered_at'] as $column) {
			$this->assertTrue($resultSchema->getTable('erp_delivery_notes')->hasColumn($column), "missing column $column");
		}
		foreach (['delivery_note_id', 'position_type', 'reference_id', 'description', 'quantity', 'unit'] as $column) {
			$this->assertTrue($resultSchema->getTable('erp_delivery_note_positions')->hasColumn($column), "missing column $column");
		}

		$this->assertTrue($resultSchema->getTable('erp_delivery_notes')->getIndex('erp_dn_number_idx')->isUnique());
		$this->assertTrue($resultSchema->getTable('erp_quotes')->getColumn('project_id')->getNotnull());
		$this->assertTrue($resultSchema->getTable('erp_invoices')->getColumn('project_id')->getNotnull());
		$this->assertTrue($resultSchema->getTable('erp_credit_notes')->hasColumn('project_id'));
	}
}
