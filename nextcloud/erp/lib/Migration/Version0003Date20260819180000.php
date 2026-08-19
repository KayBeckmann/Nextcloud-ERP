<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Contacts-/Calendar-Verknüpfungen (Roadmap Phase 3, ADR-0009). Files braucht
 * keine eigene Tabelle — Ordner werden idempotent über die Files-API
 * sichergestellt, nicht in der DB nachgehalten.
 */
class Version0003Date20260819180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('erp_contact_links')) {
			$table = $schema->createTable('erp_contact_links');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('contact_uid', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('role', Types::STRING, [
				'notnull' => true,
				'length' => 10, // 'customer' | 'supplier'
			]);
			$table->addColumn('reference_number', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('payment_terms_days', Types::SMALLINT, [
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
			$table->addUniqueIndex(['contact_uid', 'role'], 'erp_contact_link_idx');
		}

		if (!$schema->hasTable('erp_calendar_links')) {
			$table = $schema->createTable('erp_calendar_links');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('resource_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('resource_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('calendar_uri', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('event_uri', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('summary', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['resource_type', 'resource_id'], 'erp_cal_link_resource_idx');
		}

		return $schema;
	}
}
