<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Mehrere Ansprechpartner pro Firmenkunde/-lieferant (ADR-0041). Bisher
 * bildete ein `erp_contact_links`-Eintrag genau einen Nextcloud-Kontakt als
 * "den" Kunden/Lieferanten ab — für Firmen mit mehreren benannten
 * Ansprechpartnern (z. B. Geschäftsführung, Buchhaltung, Projektleitung)
 * gab es keine Struktur. `erp_contact_persons` hängt bewusst am
 * `contact_link_id` (nicht an der Nextcloud-Contact-UID) — Ansprechpartner
 * sind reine ERP-Metadaten ohne eigene vCard, ihr Lebenszyklus folgt dem der
 * ERP-Kunden-/Lieferanten-Verknüpfung.
 */
class Version0027Date20261003100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('erp_contact_persons')) {
			return $schema;
		}
		$table = $schema->createTable('erp_contact_persons');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('contact_link_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		// position: Funktion/Rolle bei der Firma (z. B. "Geschäftsführer",
		// "Buchhaltung"), bewusst freier Text statt Enum — Kundenstrukturen
		// sind zu unterschiedlich für eine feste Liste.
		$table->addColumn('position', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('phone', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['contact_link_id'], 'erp_contactpersons_link_idx');

		return $schema;
	}
}
