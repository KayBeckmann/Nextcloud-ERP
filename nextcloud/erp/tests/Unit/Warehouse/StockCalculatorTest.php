<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Warehouse;

use OCA\ERP\Warehouse\StockCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0014.
 */
final class StockCalculatorTest extends TestCase {
	public function testSollQuantitySubtractsReserved(): void {
		$this->assertSame(7.0, StockCalculator::sollQuantity(10.0, 3.0));
	}

	public function testSollQuantityWithoutReservation(): void {
		$this->assertSame(10.0, StockCalculator::sollQuantity(10.0, 0.0));
	}

	public function testNeedsReorderWhenOnHandBelowMinimum(): void {
		$this->assertTrue(StockCalculator::needsReorder(2.0, 0.0, 5.0));
	}

	public function testNeedsReorderWhenSollBelowMinimumEvenIfOnHandIsNot(): void {
		// onHand = 6 (über Mindestbestand 5), aber 4 davon reserviert -> Soll = 2 < 5.
		$this->assertTrue(StockCalculator::needsReorder(6.0, 4.0, 5.0));
	}

	public function testNoReorderWhenBothAboveMinimum(): void {
		$this->assertFalse(StockCalculator::needsReorder(10.0, 2.0, 5.0));
	}

	public function testNoReorderAtExactMinimum(): void {
		$this->assertFalse(StockCalculator::needsReorder(5.0, 0.0, 5.0));
	}
}
