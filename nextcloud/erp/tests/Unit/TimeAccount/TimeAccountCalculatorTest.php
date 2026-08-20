<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\TimeAccount;

use OCA\ERP\TimeAccount\TimeAccountCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0012.
 */
final class TimeAccountCalculatorTest extends TestCase {
	public function testFullWorkWeekMatchesSoll(): void {
		// 2026-08-17 (Mo) bis 2026-08-21 (Fr) = 5 Werktage.
		$result = TimeAccountCalculator::calculate(40.0, '2026-08-17', '2026-08-21', 5 * 8 * 60);

		$this->assertSame(5, $result['workdays']);
		$this->assertSame(40.0, $result['sollHours']);
		$this->assertSame(40.0, $result['istHours']);
		$this->assertSame(0.0, $result['balanceHours']);
	}

	public function testWeekendIsExcludedFromWorkdays(): void {
		// 2026-08-15 (Sa) bis 2026-08-16 (So) = 0 Werktage.
		$result = TimeAccountCalculator::calculate(40.0, '2026-08-15', '2026-08-16', 0);

		$this->assertSame(0, $result['workdays']);
		$this->assertSame(0.0, $result['sollHours']);
	}

	public function testPositiveBalanceWhenOvertime(): void {
		$result = TimeAccountCalculator::calculate(40.0, '2026-08-17', '2026-08-21', 45 * 60);

		$this->assertSame(45.0, $result['istHours']);
		$this->assertSame(40.0, $result['sollHours']);
		$this->assertSame(5.0, $result['balanceHours']);
	}

	public function testNegativeBalanceWhenUndertime(): void {
		$result = TimeAccountCalculator::calculate(40.0, '2026-08-17', '2026-08-21', 30 * 60);

		$this->assertSame(-10.0, $result['balanceHours']);
	}

	public function testPartTimeScalesSollByWeeklyHours(): void {
		$result = TimeAccountCalculator::calculate(20.0, '2026-08-17', '2026-08-21', 20 * 60);

		$this->assertSame(20.0, $result['sollHours']);
		$this->assertSame(0.0, $result['balanceHours']);
	}

	public function testInvertedDateRangeYieldsZeroWorkdays(): void {
		$result = TimeAccountCalculator::calculate(40.0, '2026-08-21', '2026-08-17', 0);
		$this->assertSame(0, $result['workdays']);
		$this->assertSame(0.0, $result['sollHours']);
	}
}
