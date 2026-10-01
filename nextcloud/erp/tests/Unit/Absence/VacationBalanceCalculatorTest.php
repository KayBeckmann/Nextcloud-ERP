<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Absence;

use OCA\ERP\Absence\VacationBalanceCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0033
 * und (analog) ADR-0012/TimeAccountCalculatorTest.
 */
final class VacationBalanceCalculatorTest extends TestCase {
	public function testFullEntitlementRemainsWithoutRequests(): void {
		$result = VacationBalanceCalculator::calculate(20.0, []);

		$this->assertSame(0, $result['usedDays']);
		$this->assertSame(20.0, $result['remainingDays']);
	}

	public function testUsedDaysCountOnlyWorkdays(): void {
		// 2026-08-17 (Mo) bis 2026-08-21 (Fr) = 5 Werktage.
		$result = VacationBalanceCalculator::calculate(20.0, [
			['startDate' => '2026-08-17', 'endDate' => '2026-08-21'],
		]);

		$this->assertSame(5, $result['usedDays']);
		$this->assertSame(15.0, $result['remainingDays']);
	}

	public function testWeekendWithinRequestDoesNotCountAsUsedDay(): void {
		// 2026-08-14 (Fr) bis 2026-08-17 (Mo) = 2 Werktage (Sa/So ausgenommen).
		$result = VacationBalanceCalculator::calculate(20.0, [
			['startDate' => '2026-08-14', 'endDate' => '2026-08-17'],
		]);

		$this->assertSame(2, $result['usedDays']);
	}

	public function testMultipleRequestsSumUp(): void {
		$result = VacationBalanceCalculator::calculate(20.0, [
			['startDate' => '2026-08-17', 'endDate' => '2026-08-21'], // 5 Werktage
			['startDate' => '2026-09-01', 'endDate' => '2026-09-02'], // 2 Werktage
		]);

		$this->assertSame(7, $result['usedDays']);
		$this->assertSame(13.0, $result['remainingDays']);
	}

	public function testRemainingDaysCanGoNegativeWhenOverdrawn(): void {
		$result = VacationBalanceCalculator::calculate(3.0, [
			['startDate' => '2026-08-17', 'endDate' => '2026-08-21'], // 5 Werktage
		]);

		$this->assertSame(5, $result['usedDays']);
		$this->assertSame(-2.0, $result['remainingDays']);
	}
}
