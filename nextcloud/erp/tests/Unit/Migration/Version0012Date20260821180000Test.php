<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0012Date20260821180000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 *
 * Nur der Schema-Diff wird geprüft (wie bei allen anderen Migrationstests
 * in diesem Projekt) — ausschließlich neue Tabellen/eine neue nullable
 * Spalte.
 */
class Version0012Date20260821180000Test extends TestCase {
	public function testChangeSchemaCreatesVehicleTablesAndWarehouseLink(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0012Date20260821180000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$this->assertTrue($resultSchema->hasTable('erp_vehicles'));
		foreach (['license_plate', 'brand_model', 'vehicle_type', 'status', 'assigned_user_id', 'current_mileage_km', 'next_inspection_date'] as $column) {
			$this->assertTrue($resultSchema->getTable('erp_vehicles')->hasColumn($column), "missing column $column");
		}
		$this->assertTrue($resultSchema->getTable('erp_vehicles')->getIndex('erp_vehicles_plate_idx')->isUnique());

		$this->assertTrue($resultSchema->hasTable('erp_vehicle_fuel_logs'));
		foreach (['vehicle_id', 'entry_date', 'liters', 'amount', 'mileage_km', 'receipt_file_id'] as $column) {
			$this->assertTrue($resultSchema->getTable('erp_vehicle_fuel_logs')->hasColumn($column), "missing column $column");
		}

		$this->assertTrue($resultSchema->getTable('erp_warehouses')->hasColumn('vehicle_id'));
		$this->assertFalse($resultSchema->getTable('erp_warehouses')->getColumn('vehicle_id')->getNotnull());
	}
}
