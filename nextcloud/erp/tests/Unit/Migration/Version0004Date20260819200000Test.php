<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0004Date20260819200000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version0004Date20260819200000Test extends TestCase {
	public function testCreatesProjectTaskAndOrderTables(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0004Date20260819200000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);

		$this->assertNotNull($resultSchema);

		$this->assertTrue($resultSchema->hasTable('erp_projects'));
		$projects = $resultSchema->getTable('erp_projects');
		foreach (['project_number', 'title', 'customer_contact_uid', 'responsible_user_id', 'status', 'files_folder_id', 'notes'] as $column) {
			$this->assertTrue($projects->hasColumn($column), "missing column $column");
		}
		$this->assertTrue($projects->getIndex('erp_project_number_idx')->isUnique());

		$this->assertTrue($resultSchema->hasTable('erp_project_tasks'));
		$tasks = $resultSchema->getTable('erp_project_tasks');
		foreach (['project_id', 'title', 'done', 'position'] as $column) {
			$this->assertTrue($tasks->hasColumn($column), "missing column $column");
		}

		$this->assertTrue($resultSchema->hasTable('erp_orders'));
		$orders = $resultSchema->getTable('erp_orders');
		foreach (['project_id', 'title', 'status', 'description'] as $column) {
			$this->assertTrue($orders->hasColumn($column), "missing column $column");
		}
	}
}
