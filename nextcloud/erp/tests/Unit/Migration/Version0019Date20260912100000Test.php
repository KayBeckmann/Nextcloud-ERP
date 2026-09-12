<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0017Date20260911123000;
use OCA\ERP\Migration\Version0019Date20260912100000;
use OCP\Migration\IOutput;
use Test\TestCase;

/** @group DB */
final class Version0019Date20260912100000Test extends TestCase {
	public function testAddsImmutablePurchaseOrderDocumentReferences(): void {
		$schema = new SchemaWrapper(\OC::$server->get(Connection::class));
		$schema = (new Version0017Date20260911123000())->changeSchema(
			$this->createMock(IOutput::class), static fn () => $schema, []
		);
		self::assertNotNull($schema);

		$result = (new Version0019Date20260912100000())->changeSchema(
			$this->createMock(IOutput::class), static fn () => $schema, []
		);
		self::assertNotNull($result);
		$table = $result->getTable('erp_purchase_orders');
		self::assertTrue($table->hasColumn('document_file_id'));
		self::assertTrue($table->hasColumn('layout_snapshot'));
		self::assertFalse($table->getColumn('document_file_id')->getNotnull());
		self::assertFalse($table->getColumn('layout_snapshot')->getNotnull());
	}
}
