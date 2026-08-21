<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Costs;

use OCA\ERP\Costs\CostCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe
 * ADR-0018 — analog zu RateResolutionServiceTest (ADR-0012) und
 * QuoteCalculationServiceTest (ADR-0011).
 */
final class CostCalculationServiceTest extends TestCase {
	public function testSumAnnualCostsAddsAllEntries(): void {
		$sum = CostCalculationService::sumAnnualCosts([
			['monthlyAmount' => 800.0],
			['monthlyAmount' => 150.0],
			['monthlyAmount' => 50.0],
		]);
		$this->assertSame(1000.0, $sum);
	}

	public function testSumAnnualCostsWithNoEntriesIsZero(): void {
		$this->assertSame(0.0, CostCalculationService::sumAnnualCosts([]));
	}

	public function testSumByCategoryGroupsCorrectly(): void {
		$sums = CostCalculationService::sumByCategory([
			['category' => 'rent', 'monthlyAmount' => 800.0],
			['category' => 'rent', 'monthlyAmount' => 800.0],
			['category' => 'software', 'monthlyAmount' => 49.0],
		]);
		$this->assertSame(['rent' => 1600.0, 'software' => 49.0], $sums);
	}

	public function testCalculateInternalHourlyRate(): void {
		$rate = CostCalculationService::calculateInternalHourlyRate(48000.0, 1600.0);
		$this->assertSame(30.0, $rate);
	}

	public function testCalculateInternalHourlyRateIsZeroWithoutProductiveHours(): void {
		$this->assertSame(0.0, CostCalculationService::calculateInternalHourlyRate(48000.0, 0.0));
		$this->assertSame(0.0, CostCalculationService::calculateInternalHourlyRate(48000.0, -10.0));
	}

	public function testCalculateSurchargedPrice(): void {
		$price = CostCalculationService::calculateSurchargedPrice(100.0, 25.0);
		$this->assertSame(125.0, $price);
	}

	public function testCalculateSurchargedPriceWithZeroPercentReturnsBaseCost(): void {
		$this->assertSame(100.0, CostCalculationService::calculateSurchargedPrice(100.0, 0.0));
	}
}
