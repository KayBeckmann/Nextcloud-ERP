<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Durable server-side references and immutable rendering data for purchase-order PDFs. */
class Version0019Date20260912100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('erp_purchase_orders');
		if (!$table->hasColumn('document_file_id')) {
			$table->addColumn('document_file_id', Types::BIGINT, ['notnull' => false]);
		}
		if (!$table->hasColumn('layout_snapshot')) {
			$table->addColumn('layout_snapshot', Types::TEXT, ['notnull' => false]);
		}
		return $schema;
	}
}
