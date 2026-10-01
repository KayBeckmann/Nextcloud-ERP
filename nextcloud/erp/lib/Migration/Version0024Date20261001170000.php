<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Verkaufspreis für Artikel/Produkte + Live-Preisabgleich in der
 * Positions-Maske (ADR-0032). Bisher hatte nur `Article` Einkaufs-/
 * Lieferantenpreise (ADR-0019, Kostenkalkulation) — keine der beiden
 * Entitäten einen Verkaufspreis. `selling_price_net` ist nullable: ohne
 * Wert verhält sich die Positions-Maske wie bisher (kein Autofill des
 * Preisfelds), kein Backfill nötig.
 */
class Version0024Date20261001170000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$articles = $schema->getTable('erp_articles');
		if (!$articles->hasColumn('selling_price_net')) {
			$articles->addColumn('selling_price_net', Types::DECIMAL, ['notnull' => false, 'precision' => 10, 'scale' => 2]);
		}

		$products = $schema->getTable('erp_products');
		if (!$products->hasColumn('selling_price_net')) {
			$products->addColumn('selling_price_net', Types::DECIMAL, ['notnull' => false, 'precision' => 10, 'scale' => 2]);
		}

		return $schema;
	}
}
