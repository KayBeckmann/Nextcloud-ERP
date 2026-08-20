<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Invoices;

use OCA\ERP\Invoices\InvoiceNumberFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase (keine DB-Abhängigkeit), siehe ADR-0013.
 */
final class InvoiceNumberFormatterTest extends TestCase {
	public function testFormatsInvoiceNumber(): void {
		$this->assertSame('R-2026-00001', InvoiceNumberFormatter::format(InvoiceNumberFormatter::INVOICE_PREFIX, 2026, 1));
	}

	public function testFormatsCreditNoteNumber(): void {
		$this->assertSame('G-2026-00042', InvoiceNumberFormatter::format(InvoiceNumberFormatter::CREDIT_NOTE_PREFIX, 2026, 42));
	}

	public function testPadsWithLeadingZeros(): void {
		$this->assertSame('R-2026-00007', InvoiceNumberFormatter::format(InvoiceNumberFormatter::INVOICE_PREFIX, 2026, 7));
	}

	public function testDoesNotTruncateLargeSequences(): void {
		$this->assertSame('R-2026-123456', InvoiceNumberFormatter::format(InvoiceNumberFormatter::INVOICE_PREFIX, 2026, 123456));
	}
}
