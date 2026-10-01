<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Migration;

use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0004Date20260819200000;
use OCA\ERP\Migration\Version0005Date20260820100000;
use OCA\ERP\Migration\Version0006Date20260820140000;
use OCA\ERP\Migration\Version0007Date20260820180000;
use OCA\ERP\Migration\Version0021Date20261001120000;
use OCP\Migration\IOutput;
use Test\TestCase;

/** @group DB */
final class Version0021Date20261001120000Test extends TestCase {
	public function testAddsDunningLevelAndCreatesPaymentAndDunningStepTables(): void {
		$schema = new SchemaWrapper(\OC::$server->get(Connection::class));
		$output = $this->createMock(IOutput::class);
		foreach ([new Version0004Date20260819200000(), new Version0005Date20260820100000(), new Version0006Date20260820140000(), new Version0007Date20260820180000()] as $migration) {
			$schema = $migration->changeSchema($output, static fn () => $schema, []);
		}
		$result = (new Version0021Date20261001120000())->changeSchema($output, static fn () => $schema, []);

		self::assertNotNull($result);

		$invoices = $result->getTable('erp_invoices');
		self::assertTrue($invoices->hasColumn('dunning_level'));

		$payments = $result->getTable('erp_invoice_payments');
		foreach (['invoice_id', 'amount', 'paid_at', 'reference', 'notes', 'recorded_by', 'recorded_at'] as $column) {
			self::assertTrue($payments->hasColumn($column), "erp_invoice_payments missing $column");
		}
		self::assertTrue($payments->hasIndex('erp_invpay_invoice_idx'));

		$dunningSteps = $result->getTable('erp_invoice_dunning_steps');
		foreach (['invoice_id', 'level', 'notes', 'created_by', 'created_at'] as $column) {
			self::assertTrue($dunningSteps->hasColumn($column), "erp_invoice_dunning_steps missing $column");
		}
		self::assertTrue($dunningSteps->hasIndex('erp_invdun_invoice_idx'));
	}

	public function testIsIdempotentWhenRunTwice(): void {
		$schema = new SchemaWrapper(\OC::$server->get(Connection::class));
		$output = $this->createMock(IOutput::class);
		foreach ([new Version0004Date20260819200000(), new Version0005Date20260820100000(), new Version0006Date20260820140000(), new Version0007Date20260820180000()] as $migration) {
			$schema = $migration->changeSchema($output, static fn () => $schema, []);
		}
		$migration = new Version0021Date20261001120000();
		$schema = $migration->changeSchema($output, static fn () => $schema, []);
		$result = $migration->changeSchema($output, static fn () => $schema, []);

		self::assertNotNull($result);
		self::assertTrue($result->getTable('erp_invoice_payments')->hasColumn('amount'));
	}
}
