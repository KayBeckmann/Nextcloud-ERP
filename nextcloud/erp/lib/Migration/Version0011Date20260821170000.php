<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Gruppen für Aufträge/Rechnungen/Lieferscheine (Nutzerwunsch 2026-08-21):
 * beim Wandeln zwischen Belegtypen sollen Positionsgruppen erhalten
 * bleiben. Bisher hatte nur `erp_quote_groups` ein Gruppen-Konzept — die
 * anderen drei Belegtypen ziehen hier nach, identisches Schema. Nur neue,
 * nullable Spalten/Tabellen, keine Datenmigration nötig.
 */
class Version0011Date20260821170000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createGroupTable($schema, 'erp_order_groups', 'order_id', 'erp_og_order_idx');
		$this->createGroupTable($schema, 'erp_invoice_groups', 'invoice_id', 'erp_ig_invoice_idx');
		$this->createGroupTable($schema, 'erp_delivery_note_groups', 'delivery_note_id', 'erp_dng_delivery_note_idx');

		$this->addGroupIdColumn($schema, 'erp_order_positions');
		$this->addGroupIdColumn($schema, 'erp_invoice_positions');
		$this->addGroupIdColumn($schema, 'erp_delivery_note_positions');

		return $schema;
	}

	private function createGroupTable(ISchemaWrapper $schema, string $tableName, string $parentColumn, string $indexName): void {
		if ($schema->hasTable($tableName)) {
			return;
		}
		$table = $schema->createTable($tableName);
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn($parentColumn, Types::BIGINT, ['notnull' => true]);
		$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex([$parentColumn], $indexName);
	}

	private function addGroupIdColumn(ISchemaWrapper $schema, string $tableName): void {
		$table = $schema->getTable($tableName);
		if (!$table->hasColumn('group_id')) {
			$table->addColumn('group_id', Types::BIGINT, ['notnull' => false]);
		}
	}
}
