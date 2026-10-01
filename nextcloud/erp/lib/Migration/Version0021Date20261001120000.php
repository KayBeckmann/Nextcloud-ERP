<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Zahlungsjournal mit Einzelbuchungen (Datum/Referenz je Teilzahlung) und
 * Mahnwesen-Grundgerüst (ADR-0025) — löst die bisherige reine
 * `paid_amount`-Summe ab, die keine Historie kannte (status.md,
 * "Noch offen" aus Phase 7).
 */
class Version0021Date20261001120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$this->addDunningLevel($schema);
		$this->createPayments($schema);
		$this->createDunningSteps($schema);
		return $schema;
	}

	private function addDunningLevel(ISchemaWrapper $schema): void {
		$table = $schema->getTable('erp_invoices');
		if (!$table->hasColumn('dunning_level')) {
			$table->addColumn('dunning_level', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		}
	}

	private function createPayments(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_invoice_payments')) {
			return;
		}
		$table = $schema->createTable('erp_invoice_payments');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('invoice_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('amount', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
		// paid_at: vom Nutzer gewähltes Zahlungsdatum (z.B. Kontoauszugsdatum),
		// bewusst getrennt von recorded_at (wann der Eintrag im ERP erfasst
		// wurde) — beides kann auseinanderfallen, Nachbuchungen sind normal.
		$table->addColumn('paid_at', Types::STRING, ['notnull' => true, 'length' => 10]);
		$table->addColumn('reference', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->addColumn('recorded_by', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('recorded_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['invoice_id'], 'erp_invpay_invoice_idx');
	}

	private function createDunningSteps(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_invoice_dunning_steps')) {
			return;
		}
		$table = $schema->createTable('erp_invoice_dunning_steps');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('invoice_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('level', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['invoice_id'], 'erp_invdun_invoice_idx');
	}
}
