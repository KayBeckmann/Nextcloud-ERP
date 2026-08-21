<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Fuhrpark (Roadmap Phase 9, ADR-0017). */
class Version0012Date20260821180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createVehicles($schema);
		$this->createFuelLogs($schema);

		$warehouses = $schema->getTable('erp_warehouses');
		if (!$warehouses->hasColumn('vehicle_id')) {
			$warehouses->addColumn('vehicle_id', Types::BIGINT, ['notnull' => false]);
		}

		return $schema;
	}

	private function createVehicles(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_vehicles')) {
			return;
		}
		$table = $schema->createTable('erp_vehicles');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('license_plate', Types::STRING, ['notnull' => true, 'length' => 32]);
		$table->addColumn('brand_model', Types::STRING, ['notnull' => false, 'length' => 128]);
		$table->addColumn('vehicle_type', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'car']);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'active']);
		$table->addColumn('assigned_user_id', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('current_mileage_km', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('next_inspection_date', Types::STRING, ['notnull' => false, 'length' => 10]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['license_plate'], 'erp_vehicles_plate_idx');
	}

	private function createFuelLogs(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_vehicle_fuel_logs')) {
			return;
		}
		$table = $schema->createTable('erp_vehicle_fuel_logs');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('vehicle_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('entry_date', Types::STRING, ['notnull' => true, 'length' => 10]);
		$table->addColumn('liters', Types::DECIMAL, ['notnull' => true, 'precision' => 8, 'scale' => 2, 'default' => '0']);
		$table->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('mileage_km', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('receipt_file_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['vehicle_id'], 'erp_vfl_vehicle_idx');
	}
}
