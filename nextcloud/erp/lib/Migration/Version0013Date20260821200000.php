<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Betriebliche Kosten und Kalkulation (Roadmap Phase 10, ADR-0018). */
class Version0013Date20260821200000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createCostEntries($schema);
		$this->createCostSettings($schema);

		return $schema;
	}

	private function createCostEntries(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_cost_entries')) {
			return;
		}
		$table = $schema->createTable('erp_cost_entries');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('category', Types::STRING, ['notnull' => true, 'length' => 32]);
		$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('monthly_amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('year', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('month', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['year', 'month'], 'erp_cost_entries_period_idx');
	}

	private function createCostSettings(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_cost_settings')) {
			return;
		}
		$table = $schema->createTable('erp_cost_settings');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('year', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('productive_hours_per_year', Types::DECIMAL, ['notnull' => true, 'precision' => 8, 'scale' => 2, 'default' => '1600']);
		$table->addColumn('material_surcharge_percent', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2, 'default' => '0']);
		$table->addColumn('product_surcharge_percent', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2, 'default' => '0']);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['year'], 'erp_cost_settings_year_idx');
	}
}
