<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Fuhrpark-Erweiterungen (ADR-0028): Fahrtenbuch und
 * Fahrer-Zuweisungs-Historie — zwei der in ADR-0017 "Nicht Teil dieser
 * Phase" zurückgestellten Punkte.
 */
class Version0022Date20261001150000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$this->createTrips($schema);
		$this->createAssignmentHistory($schema);
		return $schema;
	}

	private function createTrips(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_vehicle_trips')) {
			return;
		}
		$table = $schema->createTable('erp_vehicle_trips');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('vehicle_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('trip_date', Types::STRING, ['notnull' => true, 'length' => 10]);
		$table->addColumn('driver_user_id', Types::STRING, ['notnull' => false, 'length' => 64]);
		// 'business' (dienstlich) / 'private' (privat) — Fahrtenbuchmethode
		// nach § 6 Abs. 1 Nr. 4 EStG für die Versteuerung privater
		// Pkw-Nutzung braucht genau diese Unterscheidung.
		// Default 'business' spiegelt den Entity-Default (VehicleTrip::$purpose)
		// — Nextcloud-Entities überspringen Felder beim Insert, deren Wert
		// gleich dem PHP-Property-Default ist (Entity::setter() markiert sie
		// dann nicht als "updated"), daher braucht die Spalte denselben
		// Default, sonst schlägt der NOT-NULL-Constraint beim häufigsten Fall
		// (purpose = 'business') fehl.
		$table->addColumn('purpose', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'business']);
		$table->addColumn('start_location', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('destination', Types::STRING, ['notnull' => true, 'length' => 255]);
		// Default 0 aus demselben Grund wie bei 'purpose' oben: die
		// Entity-Properties starten bei 0, Inserts mit genau diesem Wert
		// würden sonst die Spalte auslassen.
		$table->addColumn('start_mileage_km', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('end_mileage_km', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['vehicle_id'], 'erp_vt_vehicle_idx');
	}

	private function createAssignmentHistory(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_vehicle_assignments')) {
			return;
		}
		$table = $schema->createTable('erp_vehicle_assignments');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('vehicle_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('assigned_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		// NULL = diese Zuweisung ist aktuell noch aktiv.
		$table->addColumn('unassigned_at', Types::BIGINT, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['vehicle_id'], 'erp_va_vehicle_idx');
	}
}
