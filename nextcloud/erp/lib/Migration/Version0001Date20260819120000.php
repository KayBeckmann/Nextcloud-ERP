<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Erste Migration — legt nur eine minimale Key/Value-Tabelle an, um den
 * Migrationsmechanismus zu beweisen (Roadmap Phase 1 Prüfkriterium). Die
 * eigentlichen fachlichen Tabellen (erp_customers, erp_projects, ...)
 * entstehen ab Phase 2/3/4/5 als eigene Migrationen.
 */
class Version0001Date20260819120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('erp_app_meta')) {
			$table = $schema->createTable('erp_app_meta');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('meta_key', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('meta_value', 'string', [
				'notnull' => false,
				'length' => 4000,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['meta_key'], 'erp_app_meta_key_idx');
		}

		return $schema;
	}
}
