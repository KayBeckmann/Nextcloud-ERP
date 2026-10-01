<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Bearbeiten/Löschen eines bereits angelegten ERP-Termins (ADR-0031).
 * `created_by_user_id` wird gebraucht, um den richtigen Kalender (den des
 * anlegenden Users) für einen Termin ohne `assigned_user_id` wiederzufinden
 * — bisher war das nur im Moment des Anlegens über die aktive Session
 * bekannt, nirgends gespeichert. Reine Spaltenergänzung, kein Backfill:
 * bestehende Zeilen bleiben mit `created_by_user_id = null` (ihr Ersteller
 * ist nachträglich nicht mehr sicher bestimmbar — ADR-0031 "Nicht Teil
 * dieser Phase").
 */
class Version0023Date20261001160000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$calendarLinks = $schema->getTable('erp_calendar_links');
		if (!$calendarLinks->hasColumn('created_by_user_id')) {
			$calendarLinks->addColumn('created_by_user_id', Types::STRING, ['notnull' => false, 'length' => 64]);
		}

		return $schema;
	}
}
