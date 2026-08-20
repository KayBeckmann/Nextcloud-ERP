<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0007Date20260820180000;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class Version0007Date20260820180000Test extends TestCase {
	public function testCreatesAllPhase7Tables(): void {
		$connection = \OC::$server->get(Connection::class);
		$schema = new SchemaWrapper($connection);
		$output = $this->createMock(IOutput::class);

		$migration = new Version0007Date20260820180000();
		$resultSchema = $migration->changeSchema($output, static fn () => $schema, []);
		$this->assertNotNull($resultSchema);

		$expected = [
			'erp_invoices' => ['invoice_number', 'type', 'status', 'project_id', 'quote_id', 'customer_contact_uid', 'due_date', 'paid_amount', 'document_file_id'],
			'erp_invoice_positions' => ['invoice_id', 'position_type', 'reference_id', 'description', 'quantity', 'unit_price_net', 'vat_rate_percent'],
			'erp_credit_notes' => ['credit_note_number', 'invoice_id', 'status', 'reason', 'cancels_invoice'],
			'erp_credit_note_positions' => ['credit_note_id', 'description', 'quantity', 'unit_price_net', 'vat_rate_percent'],
			'erp_invoice_counters' => ['year', 'kind', 'next_sequence'],
		];

		foreach ($expected as $tableName => $columns) {
			$this->assertTrue($resultSchema->hasTable($tableName), "missing table $tableName");
			$table = $resultSchema->getTable($tableName);
			foreach ($columns as $column) {
				$this->assertTrue($table->hasColumn($column), "missing column $tableName.$column");
			}
		}

		$this->assertTrue($resultSchema->getTable('erp_invoices')->getIndex('erp_inv_number_idx')->isUnique());
		$this->assertTrue($resultSchema->getTable('erp_credit_notes')->getIndex('erp_cn_number_idx')->isUnique());
		$this->assertTrue($resultSchema->getTable('erp_invoice_counters')->getIndex('erp_invc_year_kind_idx')->isUnique());
	}
}
