<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ansprechpartner-Standards je Belegtyp (ADR-0042): auf Kundenebene
 * ("Angebote gehen an den Projektleiter, Rechnungen an die Buchhaltung")
 * und auf Projektebene als Abweichung vom Kundenstandard. Beide Tabellen
 * bewusst ohne `addForeignKeyConstraint` — konsistent mit dem Rest des
 * Projekts (App-Code sichert die Integrität).
 */
class Version0028Date20261003120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$this->createDefaults($schema);
		$this->createOverrides($schema);
		return $schema;
	}

	private function createDefaults(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_contact_person_defaults')) {
			return;
		}
		$table = $schema->createTable('erp_contact_person_defaults');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('contact_link_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('document_type', Types::STRING, ['notnull' => true, 'length' => 32]);
		$table->addColumn('contact_person_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		// Genau ein Standard je Belegtyp und Kunde.
		$table->addUniqueIndex(['contact_link_id', 'document_type'], 'erp_cpdefaults_link_type_idx');
	}

	private function createOverrides(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_project_contact_overrides')) {
			return;
		}
		$table = $schema->createTable('erp_project_contact_overrides');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('project_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('document_type', Types::STRING, ['notnull' => true, 'length' => 32]);
		$table->addColumn('contact_person_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		// Genau eine Abweichung je Belegtyp und Projekt — fehlt die Zeile,
		// gilt der Kundenstandard (kein expliziter "niemand"-Zustand).
		$table->addUniqueIndex(['project_id', 'document_type'], 'erp_cpoverrides_proj_type_idx');
	}
}
