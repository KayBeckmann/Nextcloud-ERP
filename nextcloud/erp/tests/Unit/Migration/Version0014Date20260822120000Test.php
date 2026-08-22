<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0014Date20260822120000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 *
 * Nur der Schema-Diff wird geprüft (wie bei allen anderen Migrationstests
 * in diesem Projekt) — ausschließlich neue nullable Spalten + ein Index.
 */
class Version0014Date20260822120000Test extends TestCase {
	public function testChangeSchemaAddsAssignmentAndCollisionColumns(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0014Date20260822120000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$calendarLinks = $resultSchema->getTable('erp_calendar_links');
		foreach (['assigned_user_id', 'start_at', 'end_at'] as $column) {
			$this->assertTrue($calendarLinks->hasColumn($column), "missing column $column");
			$this->assertFalse($calendarLinks->getColumn($column)->getNotnull(), "$column must be nullable");
		}
		$this->assertTrue($calendarLinks->hasIndex('erp_cal_links_assignee_idx'));

		$orders = $resultSchema->getTable('erp_orders');
		$this->assertTrue($orders->hasColumn('assigned_user_id'));
		$this->assertFalse($orders->getColumn('assigned_user_id')->getNotnull());
	}
}
