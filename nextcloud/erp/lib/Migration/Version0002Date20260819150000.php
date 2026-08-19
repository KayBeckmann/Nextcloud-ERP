<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * ERP-Rechtemodell (Roadmap Phase 2, ADR-0008) — bewusst technisch getrennt
 * von den erst in Phase 6 entstehenden Verrechnungssätzen.
 */
class Version0002Date20260819150000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('erp_permissions')) {
			$table = $schema->createTable('erp_permissions');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('principal_type', Types::STRING, [
				'notnull' => true,
				'length' => 10, // 'user' | 'group'
			]);
			$table->addColumn('principal_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('resource_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('permission', Types::STRING, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(
				['principal_type', 'principal_id', 'resource_type'],
				'erp_perm_principal_res_idx'
			);
		}

		return $schema;
	}
}
