<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\DocumentLayout;
use OCA\ERP\Db\DocumentLayoutMapper;
use OCA\ERP\Service\DocumentLayoutService;
use OCA\ERP\Service\DocumentTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class DocumentLayoutServiceTest extends TestCase {
	public function testUpdatePersistsAnAllowlistedTextOnlyLayout(): void {
		$mapper = $this->createMock(DocumentLayoutMapper::class);
		$mapper->method('findByType')->with('quote')->willReturn(null);
		$mapper->expects(self::once())->method('insert')->willReturnCallback(static fn (DocumentLayout $layout): DocumentLayout => $layout);

		$layout = (new DocumentLayoutService($mapper, new DocumentTemplateRenderer()))->update('quote', [
			'subject' => 'Angebot {{document.number}}',
			'introText' => 'Guten Tag {{customer.name}}',
			'showUnitPrice' => false,
		]);

		self::assertSame('quote', $layout->getDocumentType());
		self::assertSame('Angebot {{document.number}}', $layout->getSubject());
		self::assertSame('Guten Tag {{customer.name}}', $layout->getIntroText());
		self::assertFalse($layout->getShowUnitPrice());
		self::assertTrue($layout->getShowDiscount());
	}

	public function testUpdateRejectsUnsupportedDocumentTypesAndUnknownPlaceholders(): void {
		$mapper = $this->createMock(DocumentLayoutMapper::class);
		$service = new DocumentLayoutService($mapper, new DocumentTemplateRenderer());

		$this->expectException(\InvalidArgumentException::class);
		$service->update('purchase-order', ['footerText' => '{{dangerous.html}}']);
	}
}
