<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Belegqualität (ADR-0022): Firmenprofil-Singleton-Tabelle sowie
 * discount_percent auf allen preisführenden Positions- und Belegtabellen.
 * Rein additiv (neue Tabelle, neue nullable/defaultete Spalten) — keine
 * Datenmigration nötig, bestehende Zeilen bekommen discount_percent = 0.
 * Kein discount_percent auf erp_credit_notes (Korrektur-Beleg, ein Rabatt
 * darauf wäre nur verwirrend) oder erp_delivery_note*-Tabellen (keine
 * Preise dort).
 */
class Version0016Date20260825120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('erp_company_profile')) {
			$table = $schema->createTable('erp_company_profile');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('address_line', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('postal_code', Types::STRING, ['notnull' => false, 'length' => 32]);
			$table->addColumn('city', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('country', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('tax_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('phone', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('footer_text', Types::TEXT, ['notnull' => false]);
			$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
		}

		foreach (['erp_quote_positions', 'erp_order_positions', 'erp_invoice_positions', 'erp_credit_note_positions'] as $tableName) {
			$table = $schema->getTable($tableName);
			if (!$table->hasColumn('discount_percent')) {
				$table->addColumn('discount_percent', Types::FLOAT, ['notnull' => true, 'default' => 0]);
			}
		}

		foreach (['erp_quotes', 'erp_orders', 'erp_invoices'] as $tableName) {
			$table = $schema->getTable($tableName);
			if (!$table->hasColumn('discount_percent')) {
				$table->addColumn('discount_percent', Types::FLOAT, ['notnull' => true, 'default' => 0]);
			}
		}

		return $schema;
	}
}
