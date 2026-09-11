<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0017Date20260911123000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
final class Version0017Date20260911123000Test extends TestCase {
	public function testCreatesPurchaseOrderTablesWithAuditAndReceiptTraceability(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$resultSchema = (new Version0017Date20260911123000())->changeSchema($output, static fn () => $schema, []);
		self::assertNotNull($resultSchema);

		$expected = [
			'erp_purchase_orders' => ['supplier_contact_uid', 'supplier_reference', 'status', 'notes', 'created_by', 'created_at', 'updated_at'],
			'erp_purchase_order_positions' => ['purchase_order_id', 'article_id', 'description', 'quantity_ordered', 'quantity_received', 'unit', 'supplier_article_no', 'unit_purchase_price', 'currency', 'project_id', 'warehouse_id', 'position_order'],
			'erp_purchase_order_status_changes' => ['purchase_order_id', 'from_status', 'to_status', 'changed_by', 'changed_at', 'notes'],
			'erp_purchase_order_receipts' => ['purchase_order_position_id', 'warehouse_id', 'quantity', 'received_by', 'received_at', 'notes'],
		];
		foreach ($expected as $tableName => $columns) {
			self::assertTrue($resultSchema->hasTable($tableName), "missing table $tableName");
			$table = $resultSchema->getTable($tableName);
			foreach ($columns as $column) {
				self::assertTrue($table->hasColumn($column), "missing column $tableName.$column");
			}
		}
		self::assertTrue($resultSchema->getTable('erp_purchase_orders')->hasIndex('erp_po_supplier_idx'));
		self::assertTrue($resultSchema->getTable('erp_purchase_order_positions')->hasIndex('erp_pop_order_idx'));
	}
}
