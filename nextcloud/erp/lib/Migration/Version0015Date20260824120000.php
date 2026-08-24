<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * PDF-Export für Belege (ADR-0021): document_file_id analog zur
 * bestehenden Spalte auf erp_invoices, jetzt auch auf erp_quotes,
 * erp_orders, erp_delivery_notes, erp_credit_notes. Reine
 * Spaltenergänzung, kein Backfill nötig — bestehende Zeilen hatten nie
 * ein Dokument, bleiben also korrekt bei document_file_id = null.
 */
class Version0015Date20260824120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		foreach (['erp_quotes', 'erp_orders', 'erp_delivery_notes', 'erp_credit_notes'] as $tableName) {
			$table = $schema->getTable($tableName);
			if (!$table->hasColumn('document_file_id')) {
				$table->addColumn('document_file_id', Types::BIGINT, ['notnull' => false]);
			}
		}

		return $schema;
	}
}
