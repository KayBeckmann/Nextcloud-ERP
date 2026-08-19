<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0003Date20260819180000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version0003Date20260819180000Test extends TestCase {
	public function testCreatesContactAndCalendarLinkTables(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0003Date20260819180000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);

		$this->assertNotNull($resultSchema);

		$this->assertTrue($resultSchema->hasTable('erp_contact_links'));
		$contactTable = $resultSchema->getTable('erp_contact_links');
		foreach (['contact_uid', 'role', 'reference_number', 'payment_terms_days', 'notes'] as $column) {
			$this->assertTrue($contactTable->hasColumn($column), "missing column $column");
		}
		$this->assertTrue($contactTable->getIndex('erp_contact_link_idx')->isUnique());

		$this->assertTrue($resultSchema->hasTable('erp_calendar_links'));
		$calendarTable = $resultSchema->getTable('erp_calendar_links');
		foreach (['resource_type', 'resource_id', 'calendar_uri', 'event_uri', 'summary'] as $column) {
			$this->assertTrue($calendarTable->hasColumn($column), "missing column $column");
		}
		$this->assertTrue($calendarTable->hasIndex('erp_cal_link_resource_idx'));
	}
}
