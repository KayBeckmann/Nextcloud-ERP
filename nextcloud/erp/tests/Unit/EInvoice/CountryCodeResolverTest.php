<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\EInvoice;

use OCA\ERP\EInvoice\CountryCodeResolver;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0012.
 */
final class CountryCodeResolverTest extends TestCase {
	public function testResolvesGermanName(): void {
		self::assertSame('DE', CountryCodeResolver::resolve('Deutschland'));
	}

	public function testResolvesEnglishName(): void {
		self::assertSame('DE', CountryCodeResolver::resolve('Germany'));
	}

	public function testPassesThroughExistingTwoLetterCode(): void {
		self::assertSame('AT', CountryCodeResolver::resolve('at'));
	}

	public function testIsCaseInsensitive(): void {
		self::assertSame('CH', CountryCodeResolver::resolve('schweiz'));
	}

	public function testFallsBackToGermanyOnEmpty(): void {
		self::assertSame('DE', CountryCodeResolver::resolve(null));
		self::assertSame('DE', CountryCodeResolver::resolve(''));
		self::assertSame('DE', CountryCodeResolver::resolve('   '));
	}

	public function testFallsBackToGermanyOnUnknownName(): void {
		self::assertSame('DE', CountryCodeResolver::resolve('Nirgendland'));
	}
}
