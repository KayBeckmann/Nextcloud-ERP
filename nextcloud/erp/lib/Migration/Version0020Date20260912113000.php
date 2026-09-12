<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Historical native-contact values retained when a role card is deleted. */
class Version0020Date20260912113000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('erp_contact_history_snapshots')) return $schema;
		$table = $schema->createTable('erp_contact_history_snapshots');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('contact_uid', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('role', Types::STRING, ['notnull' => true, 'length' => 16]);
		$table->addColumn('entity_type', Types::STRING, ['notnull' => true, 'length' => 32]);
		$table->addColumn('entity_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('display_name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('postal_address', Types::TEXT, ['notnull' => true]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['contact_uid', 'role'], 'erp_contact_history_lookup_idx');
		$table->addUniqueIndex(['entity_type', 'entity_id', 'contact_uid', 'role'], 'erp_contact_history_entity_uq');
		return $schema;
	}
}
