<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Quotes;

use OCA\ERP\Quotes\QuoteCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0011.
 */
final class QuoteCalculationServiceTest extends TestCase {
	public function testSingleUngroupedPosition(): void {
		$result = QuoteCalculationService::calculate([], [
			['id' => 1, 'groupId' => null, 'quantity' => 2, 'unitPriceNet' => 10.0, 'vatRatePercent' => 19.0],
		]);

		$this->assertSame(20.0, $result['netSubtotal']);
		$this->assertSame(23.8, $result['grossTotal']);
		$this->assertCount(1, $result['vatBreakdown']);
		$this->assertSame(19.0, $result['vatBreakdown'][0]['ratePercent']);
		$this->assertSame(3.8, $result['vatBreakdown'][0]['vatAmount']);
	}

	public function testGroupNetTotalsAreSeparate(): void {
		$groups = [['id' => 1, 'title' => 'Material'], ['id' => 2, 'title' => 'Montage']];
		$positions = [
			['id' => 1, 'groupId' => 1, 'quantity' => 1, 'unitPriceNet' => 100.0, 'vatRatePercent' => 19.0],
			['id' => 2, 'groupId' => 2, 'quantity' => 5, 'unitPriceNet' => 50.0, 'vatRatePercent' => 19.0],
		];

		$result = QuoteCalculationService::calculate($groups, $positions);

		$byTitle = [];
		foreach ($result['groups'] as $g) {
			$byTitle[$g['title']] = $g['netTotal'];
		}
		$this->assertSame(100.0, $byTitle['Material']);
		$this->assertSame(250.0, $byTitle['Montage']);
		$this->assertSame(350.0, $result['netSubtotal']);
	}

	public function testMultipleVatRatesAreBrokenDownSeparately(): void {
		$positions = [
			['id' => 1, 'groupId' => null, 'quantity' => 1, 'unitPriceNet' => 100.0, 'vatRatePercent' => 19.0],
			['id' => 2, 'groupId' => null, 'quantity' => 1, 'unitPriceNet' => 100.0, 'vatRatePercent' => 7.0],
		];

		$result = QuoteCalculationService::calculate([], $positions);

		$this->assertCount(2, $result['vatBreakdown']);
		$this->assertSame(200.0, $result['netSubtotal']);
		// 19 + 7 = 26 EUR MwSt. auf 200 EUR netto
		$this->assertSame(226.0, $result['grossTotal']);
	}

	public function testUngroupedPositionsUseNullGroup(): void {
		$result = QuoteCalculationService::calculate(
			[['id' => 1, 'title' => 'Gruppe A']],
			[['id' => 1, 'groupId' => null, 'quantity' => 1, 'unitPriceNet' => 10.0, 'vatRatePercent' => 19.0]],
		);

		$this->assertCount(1, $result['groups']);
		$this->assertNull($result['groups'][0]['groupId']);
		$this->assertNull($result['groups'][0]['title']);
	}

	public function testEmptyQuoteHasZeroTotals(): void {
		$result = QuoteCalculationService::calculate([], []);
		$this->assertSame(0.0, $result['netSubtotal']);
		$this->assertSame(0.0, $result['grossTotal']);
		$this->assertSame([], $result['vatBreakdown']);
	}

	/** ADR-0022: Rabatt auf eine einzelne Position wirkt vor der MwSt. */
	public function testPositionDiscountReducesNetTotalBeforeVat(): void {
		$result = QuoteCalculationService::calculate([], [
			['id' => 1, 'groupId' => null, 'quantity' => 2, 'unitPriceNet' => 10.0, 'vatRatePercent' => 19.0, 'discountPercent' => 10.0],
		]);

		// 2 * 10 = 20, minus 10% = 18 netto
		$this->assertSame(18.0, $result['netSubtotal']);
		$this->assertSame(3.42, $result['vatBreakdown'][0]['vatAmount']);
		$this->assertSame(21.42, $result['grossTotal']);
	}

	/** ADR-0022: Belegrabatt wirkt zusätzlich zum Positionsrabatt, anteilig je MwSt.-Satz. */
	public function testDocumentDiscountAppliesProportionallyPerVatRate(): void {
		$positions = [
			['id' => 1, 'groupId' => null, 'quantity' => 1, 'unitPriceNet' => 100.0, 'vatRatePercent' => 19.0],
			['id' => 2, 'groupId' => null, 'quantity' => 1, 'unitPriceNet' => 100.0, 'vatRatePercent' => 7.0],
		];

		$result = QuoteCalculationService::calculate([], $positions, 10.0);

		$this->assertSame(200.0, $result['netSubtotalBeforeDiscount']);
		$this->assertSame(20.0, $result['documentDiscountAmount']);
		$this->assertSame(180.0, $result['netSubtotal']);
		foreach ($result['vatBreakdown'] as $v) {
			$this->assertSame(90.0, $v['netBase']);
		}
	}

	/** Ohne Rabatt müssen die neuen Felder das bisherige Verhalten exakt spiegeln. */
	public function testNoDiscountKeepsNetSubtotalBeforeDiscountEqualToNetSubtotal(): void {
		$result = QuoteCalculationService::calculate([], [
			['id' => 1, 'groupId' => null, 'quantity' => 1, 'unitPriceNet' => 50.0, 'vatRatePercent' => 19.0],
		]);

		$this->assertSame($result['netSubtotal'], $result['netSubtotalBeforeDiscount']);
		$this->assertSame(0.0, $result['documentDiscountAmount']);
	}
}
