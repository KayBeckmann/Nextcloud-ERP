<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CompanyProfile;
use OCA\ERP\Db\DocumentLayout;
use OCA\ERP\Db\PurchaseOrder;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\DocumentHtmlBuilder;
use OCA\ERP\Service\DocumentLayoutService;
use OCA\ERP\Service\DocumentTemplateRenderer;
use OCA\ERP\Service\PurchaseOrderPdfRenderer;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderPdfRendererTest extends TestCase {
	public function testRendersFixedEscapedPurchaseOrderLayoutWithReferencesAndTotals(): void {
		$profile = new CompanyProfile();
		$profile->setName('Buyer <GmbH>');
		$profile->setEmail('buyer@example.test');
		$companyProfiles = $this->createMock(CompanyProfileService::class);
		$companyProfiles->method('get')->willReturn($profile);
		$contacts = $this->createMock(ContactsService::class);
		$contacts->method('detailsFor')->willReturn(['displayName' => 'Supplier <OHG>', 'addressLines' => ['Road 1']]);
		$layouts = $this->createMock(DocumentLayoutService::class);
		$layout = new DocumentLayout();
		$layout->setDocumentType('purchase_order');
		$layout->setIntroText('Order {{document.number}}');
		$layouts->method('get')->willReturn($layout);
		$htmlBuilder = new DocumentHtmlBuilder($companyProfiles, $contacts, $layouts, new DocumentTemplateRenderer());
		$order = new PurchaseOrder();
		$order->setId(7);
		$order->setSupplierContactUid('supplier-1');
		$order->setSupplierReference('Offer <42>');
		$order->setCreatedAt(1780000000);
		$snapshot = $htmlBuilder->snapshot('purchase_order', 'PO-00007', 'Purchase order', 1780000000, null, 'supplier-1');

		$html = (new PurchaseOrderPdfRenderer($htmlBuilder))->render($order, [[
			'description' => 'Cable <NYM>', 'quantityOrdered' => 2.0, 'unit' => 'm', 'supplierArticleNo' => 'S-1',
			'unitPurchasePrice' => 3.5, 'currency' => 'EUR', 'projectId' => 8, 'warehouseId' => 9,
		]], $snapshot);

		self::assertStringContainsString('Buyer &lt;GmbH&gt;', $html);
		self::assertStringContainsString('Supplier &lt;OHG&gt;', $html);
		self::assertStringContainsString('Cable &lt;NYM&gt;', $html);
		self::assertStringContainsString('Projekt #8', $html);
		self::assertStringContainsString('Lager #9', $html);
		self::assertStringContainsString('7,00 EUR', $html);
		self::assertStringNotContainsString('<NYM>', $html);
	}
}
