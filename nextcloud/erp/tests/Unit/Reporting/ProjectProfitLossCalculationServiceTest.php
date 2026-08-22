<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Reporting;

use OCA\ERP\Reporting\ProjectProfitLossCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe
 * ADR-0019 — analog zu CostCalculationServiceTest (ADR-0018).
 */
final class ProjectProfitLossCalculationServiceTest extends TestCase {
	public function testCalculatesResultFromInvoicedMinusCosts(): void {
		$result = ProjectProfitLossCalculationService::calculate(10000.0, 8000.0, 2000.0, 1500.0);

		$this->assertSame(10000.0, $result['sollNet']);
		$this->assertSame(8000.0, $result['invoicedNet']);
		$this->assertSame(2000.0, $result['laborCost']);
		$this->assertSame(1500.0, $result['materialCost']);
		$this->assertSame(3500.0, $result['totalCost']);
		$this->assertSame(4500.0, $result['result']);
	}

	public function testSollNetIsNullWhenNoOrderOrQuoteExists(): void {
		$result = ProjectProfitLossCalculationService::calculate(null, 1000.0, 0.0, 0.0);
		$this->assertNull($result['sollNet']);
	}

	public function testResultIsNegativeWhenCostsExceedRevenue(): void {
		$result = ProjectProfitLossCalculationService::calculate(5000.0, 1000.0, 2000.0, 500.0);
		$this->assertSame(-1500.0, $result['result']);
	}

	public function testZeroCostsAndRevenueYieldZeroResult(): void {
		$result = ProjectProfitLossCalculationService::calculate(null, 0.0, 0.0, 0.0);
		$this->assertSame(0.0, $result['result']);
	}

	public function testRoundsToTwoDecimals(): void {
		$result = ProjectProfitLossCalculationService::calculate(null, 100.005, 33.333, 0.0);
		$this->assertSame(33.33, $result['laborCost']);
		$this->assertSame(100.01, $result['invoicedNet']);
	}
}
