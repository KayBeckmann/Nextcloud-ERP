<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Rates;

use OCA\ERP\Rates\RateResolutionService;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0012.
 * Deckt alle 6 Prioritätsstufen aus dem Brainstorming ab.
 */
final class RateResolutionServiceTest extends TestCase {
	public function testFallsBackToWorkTypeDefaultWhenNothingElseMatches(): void {
		$rate = RateResolutionService::resolve([], [], 1, 'alice', [], 55.0);
		$this->assertSame(55.0, $rate);
	}

	public function testHardFallbackIsZeroWithoutAnyRate(): void {
		$rate = RateResolutionService::resolve([], [], 1, 'alice', [], null);
		$this->assertSame(0.0, $rate);
	}

	public function testGlobalStandardRateOverridesWorkTypeDefault(): void {
		$standard = [['workTypeId' => 1, 'principalType' => null, 'principalId' => null, 'rate' => 60.0]];
		$rate = RateResolutionService::resolve([], $standard, 1, 'alice', [], 55.0);
		$this->assertSame(60.0, $rate);
	}

	public function testGroupStandardRateOverridesGlobal(): void {
		$standard = [
			['workTypeId' => 1, 'principalType' => null, 'principalId' => null, 'rate' => 60.0],
			['workTypeId' => 1, 'principalType' => 'group', 'principalId' => 'monteure', 'rate' => 65.0],
		];
		$rate = RateResolutionService::resolve([], $standard, 1, 'alice', ['monteure'], 55.0);
		$this->assertSame(65.0, $rate);
	}

	public function testUserStandardRateOverridesGroupStandardRate(): void {
		$standard = [
			['workTypeId' => 1, 'principalType' => 'group', 'principalId' => 'monteure', 'rate' => 65.0],
			['workTypeId' => 1, 'principalType' => 'user', 'principalId' => 'alice', 'rate' => 70.0],
		];
		$rate = RateResolutionService::resolve([], $standard, 1, 'alice', ['monteure'], 55.0);
		$this->assertSame(70.0, $rate);
	}

	public function testContractGroupRateOverridesStandardUserRate(): void {
		$standard = [['workTypeId' => 1, 'principalType' => 'user', 'principalId' => 'alice', 'rate' => 70.0]];
		$contract = [['workTypeId' => 1, 'principalType' => 'group', 'principalId' => 'monteure', 'rate' => 80.0]];
		$rate = RateResolutionService::resolve($contract, $standard, 1, 'alice', ['monteure'], 55.0);
		$this->assertSame(80.0, $rate);
	}

	public function testContractUserRateWinsOverEverythingElse(): void {
		$standard = [['workTypeId' => 1, 'principalType' => 'user', 'principalId' => 'alice', 'rate' => 70.0]];
		$contract = [
			['workTypeId' => 1, 'principalType' => 'group', 'principalId' => 'monteure', 'rate' => 80.0],
			['workTypeId' => 1, 'principalType' => 'user', 'principalId' => 'alice', 'rate' => 90.0],
		];
		$rate = RateResolutionService::resolve($contract, $standard, 1, 'alice', ['monteure'], 55.0);
		$this->assertSame(90.0, $rate);
	}

	public function testRatesForOtherWorkTypesAreIgnored(): void {
		$standard = [['workTypeId' => 2, 'principalType' => 'user', 'principalId' => 'alice', 'rate' => 999.0]];
		$rate = RateResolutionService::resolve([], $standard, 1, 'alice', [], 55.0);
		$this->assertSame(55.0, $rate);
	}

	public function testSameUserDifferentWorkTypesCanHaveDifferentRates(): void {
		$standard = [
			['workTypeId' => 1, 'principalType' => 'user', 'principalId' => 'alice', 'rate' => 70.0], // Monteur
			['workTypeId' => 2, 'principalType' => 'user', 'principalId' => 'alice', 'rate' => 95.0], // Meister
		];
		$monteurRate = RateResolutionService::resolve([], $standard, 1, 'alice', [], null);
		$meisterRate = RateResolutionService::resolve([], $standard, 2, 'alice', [], null);
		$this->assertSame(70.0, $monteurRate);
		$this->assertSame(95.0, $meisterRate);
	}
}
