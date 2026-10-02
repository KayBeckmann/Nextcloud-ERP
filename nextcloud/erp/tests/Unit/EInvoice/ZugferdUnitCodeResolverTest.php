<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\EInvoice;

use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use OCA\ERP\EInvoice\ZugferdUnitCodeResolver;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0012.
 */
final class ZugferdUnitCodeResolverTest extends TestCase {
	public function testResolvesStueckAbbreviation(): void {
		self::assertSame(ZugferdUnitCodes::REC20_PIECE, ZugferdUnitCodeResolver::resolve('Stk'));
	}

	public function testResolvesStundeAbbreviation(): void {
		self::assertSame(ZugferdUnitCodes::REC20_HOUR, ZugferdUnitCodeResolver::resolve('Std.'));
	}

	public function testResolvesPauschal(): void {
		self::assertSame(ZugferdUnitCodes::REC20_LUMP_SUM, ZugferdUnitCodeResolver::resolve('psch.'));
	}

	public function testIsCaseInsensitiveAndTrims(): void {
		self::assertSame(ZugferdUnitCodes::REC20_KILOGRAM, ZugferdUnitCodeResolver::resolve('  KG  '));
	}

	public function testFallsBackToGenericUnitForUnknownText(): void {
		self::assertSame(ZugferdUnitCodes::REC20_ONE, ZugferdUnitCodeResolver::resolve('Rolle'));
	}
}
