<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Warehouse;

use OCA\ERP\Warehouse\PurchaseSuggestionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0014.
 */
final class PurchaseSuggestionCalculatorTest extends TestCase {
	public function testSuggestsDifferenceToMinimum(): void {
		$this->assertSame(3.0, PurchaseSuggestionCalculator::suggestedQuantity(2.0, 5.0));
	}

	public function testSuggestsZeroWhenAboveMinimum(): void {
		$this->assertSame(0.0, PurchaseSuggestionCalculator::suggestedQuantity(10.0, 5.0));
	}

	public function testSuggestsZeroAtExactMinimum(): void {
		$this->assertSame(0.0, PurchaseSuggestionCalculator::suggestedQuantity(5.0, 5.0));
	}

	public function testNeverNegative(): void {
		$this->assertSame(0.0, PurchaseSuggestionCalculator::suggestedQuantity(20.0, 5.0));
	}
}
