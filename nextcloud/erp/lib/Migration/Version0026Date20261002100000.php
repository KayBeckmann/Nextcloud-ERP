<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Automatische Bestandsreservierung für Auftragspositionen (ADR-0039).
 * `warehouse_id` ist nullable und nur für `position_type = 'article'`
 * fachlich relevant (siehe OrderService) — Positionen ohne Lagerauswahl
 * bleiben wie bisher ohne jede Reservierungslogik.
 */
class Version0026Date20261002100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('erp_order_positions');
		if (!$table->hasColumn('warehouse_id')) {
			$table->addColumn('warehouse_id', Types::BIGINT, ['notnull' => false]);
		}

		return $schema;
	}
}
