<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * P3: sichere, feste Beleggestaltung. Keine HTML-/CSS-Vorlagen werden
 * gespeichert; Einstellungen bleiben auf explizite Textfelder und Flags
 * begrenzt. Logo-Upload ist bewusst nicht Teil dieses Schritts.
 */
class Version0018Date20260911150000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$profile = $schema->getTable('erp_company_profile');
		foreach ([
			'header_text' => [Types::TEXT, null], 'footer_text' => [Types::TEXT, null],
			'legal_form' => [Types::STRING, 128], 'managing_director' => [Types::STRING, 255],
			'commercial_register' => [Types::STRING, 255], 'vat_id' => [Types::STRING, 64],
			'tax_number' => [Types::STRING, 64], 'logo_file_id' => [Types::BIGINT, null], 'bank_name' => [Types::STRING, 255],
			'iban' => [Types::STRING, 64], 'bic' => [Types::STRING, 32],
		] as $name => [$type, $length]) {
			if (!$profile->hasColumn($name)) $profile->addColumn($name, $type, ['notnull' => false, 'length' => $length]);
		}

		if (!$schema->hasTable('erp_document_layouts')) {
			$table = $schema->createTable('erp_document_layouts');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('document_type', Types::STRING, ['notnull' => true, 'length' => 32]);
			$table->addColumn('subject', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('header_text', Types::TEXT, ['notnull' => false]);
			$table->addColumn('intro_text', Types::TEXT, ['notnull' => false]);
			$table->addColumn('closing_text', Types::TEXT, ['notnull' => false]);
			$table->addColumn('payment_note', Types::TEXT, ['notnull' => false]);
			$table->addColumn('delivery_note', Types::TEXT, ['notnull' => false]);
			$table->addColumn('show_unit_price', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('show_discount', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('show_vat', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('number_prefix', Types::STRING, ['notnull' => false, 'length' => 32]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']); $table->addUniqueIndex(['document_type'], 'erp_document_layout_type_uq');
		}
		foreach (['erp_quotes', 'erp_orders', 'erp_delivery_notes', 'erp_invoices', 'erp_credit_notes'] as $tableName) {
			$table = $schema->getTable($tableName);
			if (!$table->hasColumn('layout_snapshot')) $table->addColumn('layout_snapshot', Types::TEXT, ['notnull' => false]);
		}
		return $schema;
	}
}
