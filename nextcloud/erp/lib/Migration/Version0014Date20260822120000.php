<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Mitarbeiter-Zuweisung für Termine + Kollisionserkennung + Auftrags-
 * Zuweisung (ADR-0020). Reine Spaltenergänzung, kein Backfill nötig —
 * bestehende Zeilen bleiben mit assigned_user_id/start_at/end_at = null
 * (sie waren nie einem Mitarbeiter zugewiesen, siehe ADR-0020).
 */
class Version0014Date20260822120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$calendarLinks = $schema->getTable('erp_calendar_links');
		if (!$calendarLinks->hasColumn('assigned_user_id')) {
			$calendarLinks->addColumn('assigned_user_id', Types::STRING, ['notnull' => false, 'length' => 64]);
		}
		if (!$calendarLinks->hasColumn('start_at')) {
			$calendarLinks->addColumn('start_at', Types::BIGINT, ['notnull' => false]);
		}
		if (!$calendarLinks->hasColumn('end_at')) {
			$calendarLinks->addColumn('end_at', Types::BIGINT, ['notnull' => false]);
		}
		// Für die Kollisionsprüfung (WHERE assigned_user_id = ? AND
		// start_at < ? AND end_at > ?) — assigned_user_id führend, da immer
		// als erste Bedingung gefiltert wird.
		if (!$calendarLinks->hasIndex('erp_cal_links_assignee_idx')) {
			$calendarLinks->addIndex(['assigned_user_id', 'start_at', 'end_at'], 'erp_cal_links_assignee_idx');
		}

		$orders = $schema->getTable('erp_orders');
		if (!$orders->hasColumn('assigned_user_id')) {
			$orders->addColumn('assigned_user_id', Types::STRING, ['notnull' => false, 'length' => 64]);
		}

		return $schema;
	}
}
