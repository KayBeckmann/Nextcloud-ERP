<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0001Date20260819120000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version0001Date20260819120000Test extends TestCase {
	public function testCreatesErpAppMetaTable(): void {
		// Migrationen erhalten intern eine OC\DB\Connection (nicht die
		// öffentliche IDBConnection-Fassade) — SchemaWrapper erwartet genau
		// diesen konkreten Typ, siehe OC\DB\MigrationService.
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0001Date20260819120000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);

		$this->assertNotNull($resultSchema);
		$this->assertTrue($resultSchema->hasTable('erp_app_meta'));

		$table = $resultSchema->getTable('erp_app_meta');
		$this->assertTrue($table->hasColumn('meta_key'));
		$this->assertTrue($table->hasColumn('meta_value'));
	}
}
