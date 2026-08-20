<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0006Date20260820140000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version0006Date20260820140000Test extends TestCase {
	public function testCreatesAllPhase6Tables(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0006Date20260820140000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$expected = [
			'erp_standard_rates' => ['work_type_id', 'principal_type', 'principal_id', 'rate'],
			'erp_customer_contracts' => ['customer_contact_uid', 'title', 'valid_from', 'valid_until'],
			'erp_customer_contract_rates' => ['contract_id', 'work_type_id', 'rate'],
			'erp_time_entries' => ['user_id', 'project_id', 'order_id', 'work_type_id', 'entry_date', 'duration_minutes', 'rate_snapshot'],
			'erp_work_schedules' => ['user_id', 'weekly_hours'],
			'erp_absence_types' => ['name', 'affects_vacation_balance'],
			'erp_absence_requests' => ['user_id', 'absence_type_id', 'start_date', 'end_date', 'status'],
			'erp_overtime_actions' => ['user_id', 'hours', 'action_type', 'status'],
		];

		foreach ($expected as $tableName => $columns) {
			$this->assertTrue($resultSchema->hasTable($tableName), "missing table $tableName");
			$table = $resultSchema->getTable($tableName);
			foreach ($columns as $column) {
				$this->assertTrue($table->hasColumn($column), "missing column $tableName.$column");
			}
		}

		$this->assertTrue($resultSchema->getTable('erp_work_schedules')->getIndex('erp_ws_user_idx')->isUnique());
	}
}
