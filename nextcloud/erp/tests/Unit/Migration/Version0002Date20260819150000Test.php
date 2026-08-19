<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0002Date20260819150000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version0002Date20260819150000Test extends TestCase {
	public function testCreatesErpPermissionsTableWithUniqueIndex(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0002Date20260819150000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);

		$this->assertNotNull($resultSchema);
		$this->assertTrue($resultSchema->hasTable('erp_permissions'));

		$table = $resultSchema->getTable('erp_permissions');
		foreach (['principal_type', 'principal_id', 'resource_type', 'permission', 'created_at', 'updated_at'] as $column) {
			$this->assertTrue($table->hasColumn($column), "missing column $column");
		}
		$this->assertTrue($table->hasIndex('erp_perm_principal_res_idx'));
		$this->assertTrue($table->getIndex('erp_perm_principal_res_idx')->isUnique());
	}
}
