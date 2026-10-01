<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Resturlaub-Zähler (ADR-0033) — `erp_vacation_entitlements` speichert den
 * jährlichen Urlaubsanspruch je User, analog zu `erp_work_schedules`
 * (ADR-0012). Der tatsächliche Verbrauch wird weiterhin live aus
 * genehmigten `erp_absence_requests` berechnet, kein eigener Zähler.
 */
class Version0025Date20261001180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('erp_vacation_entitlements')) {
			return $schema;
		}
		$table = $schema->createTable('erp_vacation_entitlements');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		// Default 20 = gesetzlicher Mindesturlaub bei 5-Tage-Woche (§ 3
		// BUrlG) — ein neutraler, rechtlich begründbarer Startwert, keine
		// betriebliche Empfehlung. Siehe VacationEntitlementService.
		$table->addColumn('days_per_year', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2, 'default' => '20']);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id'], 'erp_ve_user_idx');

		return $schema;
	}
}
