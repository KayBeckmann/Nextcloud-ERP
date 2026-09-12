<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0004Date20260819200000;
use OCA\ERP\Migration\Version0005Date20260820100000;
use OCA\ERP\Migration\Version0006Date20260820140000;
use OCA\ERP\Migration\Version0007Date20260820180000;
use OCA\ERP\Migration\Version0009Date20260821100000;
use OCA\ERP\Migration\Version0017Date20260911123000;
use OCA\ERP\Migration\Version0020Date20260912113000;
use OCP\Migration\IOutput;
use Test\TestCase;

/** @group DB */
final class Version0020Date20260912113000Test extends TestCase {
	public function testAddsContactHistorySnapshotsForEveryRetainedBusinessReference(): void {
		$schema = new SchemaWrapper(\OC::$server->get(Connection::class));
		$output = $this->createMock(IOutput::class);
		foreach ([new Version0004Date20260819200000(), new Version0005Date20260820100000(), new Version0006Date20260820140000(), new Version0007Date20260820180000(), new Version0009Date20260821100000(), new Version0017Date20260911123000()] as $migration) {
			$schema = $migration->changeSchema($output, static fn () => $schema, []);
		}
		$result = (new Version0020Date20260912113000())->changeSchema($output, static fn () => $schema, []);

		self::assertNotNull($result);
		$table = $result->getTable('erp_contact_history_snapshots');
		foreach (['contact_uid', 'role', 'entity_type', 'entity_id', 'display_name', 'postal_address', 'created_at'] as $column) {
			self::assertTrue($table->hasColumn($column), "missing $column");
		}
		self::assertTrue($table->hasIndex('erp_contact_history_lookup_idx'));
		self::assertTrue($table->hasIndex('erp_contact_history_entity_uq'));
	}
}
