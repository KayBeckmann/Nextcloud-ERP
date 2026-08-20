<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Zeitwirtschaft und Verrechnungssätze (Roadmap Phase 6, ADR-0012).
 */
class Version0006Date20260820140000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createStandardRates($schema);
		$this->createCustomerContracts($schema);
		$this->createCustomerContractRates($schema);
		$this->createTimeEntries($schema);
		$this->createWorkSchedules($schema);
		$this->createAbsenceTypes($schema);
		$this->createAbsenceRequests($schema);
		$this->createOvertimeActions($schema);

		return $schema;
	}

	private function createStandardRates(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_standard_rates')) {
			return;
		}
		$table = $schema->createTable('erp_standard_rates');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('work_type_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('principal_type', Types::STRING, ['notnull' => false, 'length' => 10]);
		$table->addColumn('principal_id', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('rate', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['work_type_id'], 'erp_sr_work_type_idx');
	}

	private function createCustomerContracts(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_customer_contracts')) {
			return;
		}
		$table = $schema->createTable('erp_customer_contracts');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('customer_contact_uid', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('valid_from', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('valid_until', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['customer_contact_uid'], 'erp_cc_customer_idx');
	}

	private function createCustomerContractRates(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_customer_contract_rates')) {
			return;
		}
		$table = $schema->createTable('erp_customer_contract_rates');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('contract_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('work_type_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('principal_type', Types::STRING, ['notnull' => false, 'length' => 10]);
		$table->addColumn('principal_id', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('rate', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['contract_id'], 'erp_ccr_contract_idx');
	}

	private function createTimeEntries(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_time_entries')) {
			return;
		}
		$table = $schema->createTable('erp_time_entries');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('project_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('order_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('work_type_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('entry_date', Types::STRING, ['notnull' => true, 'length' => 10]); // YYYY-MM-DD
		$table->addColumn('duration_minutes', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('break_minutes', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('billable', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
		$table->addColumn('rate_snapshot', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id', 'entry_date'], 'erp_te_user_date_idx');
		$table->addIndex(['project_id'], 'erp_te_project_idx');
	}

	private function createWorkSchedules(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_work_schedules')) {
			return;
		}
		$table = $schema->createTable('erp_work_schedules');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('weekly_hours', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2, 'default' => '40']);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id'], 'erp_ws_user_idx');
	}

	private function createAbsenceTypes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_absence_types')) {
			return;
		}
		$table = $schema->createTable('erp_absence_types');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('affects_vacation_balance', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		$table->setPrimaryKey(['id']);
	}

	private function createAbsenceRequests(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_absence_requests')) {
			return;
		}
		$table = $schema->createTable('erp_absence_requests');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('absence_type_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('start_date', Types::STRING, ['notnull' => true, 'length' => 10]);
		$table->addColumn('end_date', Types::STRING, ['notnull' => true, 'length' => 10]);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'requested']);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'erp_ar_user_idx');
	}

	private function createOvertimeActions(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_overtime_actions')) {
			return;
		}
		$table = $schema->createTable('erp_overtime_actions');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('hours', Types::DECIMAL, ['notnull' => true, 'precision' => 6, 'scale' => 2]);
		$table->addColumn('action_type', Types::STRING, ['notnull' => true, 'length' => 16]);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'requested']);
		$table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'erp_oa_user_idx');
	}
}
