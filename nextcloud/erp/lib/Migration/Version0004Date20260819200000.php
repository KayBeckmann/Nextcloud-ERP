<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Projektkern (Roadmap Phase 4, ADR-0010): Projekte, Projektaufgaben,
 * Aufträge. Termine nutzen die bestehende erp_calendar_links-Tabelle aus
 * Phase 3 (resourceType='projekte') statt einer eigenen Tabelle.
 */
class Version0004Date20260819200000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('erp_projects')) {
			$table = $schema->createTable('erp_projects');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('project_number', Types::STRING, [
				'notnull' => false, // erst nach dem Insert bekannt, siehe ADR-0010
				'length' => 32,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('customer_contact_uid', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('responsible_user_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 16,
				'default' => 'draft',
			]);
			$table->addColumn('files_folder_id', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('notes', Types::TEXT, [
				'notnull' => false,
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
			$table->addUniqueIndex(['project_number'], 'erp_project_number_idx');
			$table->addIndex(['status'], 'erp_project_status_idx');
		}

		if (!$schema->hasTable('erp_project_tasks')) {
			$table = $schema->createTable('erp_project_tasks');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('project_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('done', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
			$table->addColumn('position', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
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
			$table->addIndex(['project_id'], 'erp_task_project_idx');
		}

		if (!$schema->hasTable('erp_orders')) {
			$table = $schema->createTable('erp_orders');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('project_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 16,
				'default' => 'draft',
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
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
			$table->addIndex(['project_id'], 'erp_order_project_idx');
		}

		return $schema;
	}
}
