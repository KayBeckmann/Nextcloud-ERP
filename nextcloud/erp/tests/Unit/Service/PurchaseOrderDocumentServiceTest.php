<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\PurchaseOrder;
use OCA\ERP\Db\PurchaseOrderMapper;
use OCA\ERP\Db\PurchaseOrderPositionMapper;
use OCA\ERP\Service\DocumentHtmlBuilder;
use OCA\ERP\Service\DocumentPdfService;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\PurchaseOrderDocumentService;
use OCA\ERP\Service\PurchaseOrderPdfRenderer;
use OCP\Files\Folder;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderDocumentServiceTest extends TestCase {
	public function testPrepareCapturesSnapshotWritesControlledPdfAndPersistsOnlyServerFileId(): void {
		$order = new PurchaseOrder();
		$order->setId(42);
		$order->setSupplierContactUid('supplier-1');
		$order->setCreatedAt(1780000000);
		$orderMapper = $this->createMock(PurchaseOrderMapper::class);
		$orderMapper->method('findById')->with(42)->willReturn($order);
		$orderMapper->expects(self::once())->method('update')->willReturnCallback(static fn (PurchaseOrder $value): PurchaseOrder => $value);
		$positions = $this->createMock(PurchaseOrderPositionMapper::class);
		$positions->method('findByPurchaseOrder')->with(42)->willReturn([]);
		$html = $this->createMock(DocumentHtmlBuilder::class);
		$html->expects(self::once())->method('snapshot')->with('purchase_order', 'PO-00042', 'Bestellung', 1780000000, null, 'supplier-1')->willReturn('{"format":1}');
		$renderer = $this->createMock(PurchaseOrderPdfRenderer::class);
		$renderer->expects(self::once())->method('render')->with($order, [], '{"format":1}')->willReturn('<html>safe</html>');
		$pdf = $this->createMock(DocumentPdfService::class);
		$pdf->expects(self::once())->method('writePdf')->with(self::isInstanceOf(Folder::class), 'PO-00042', '<html>safe</html>')->willReturn(91);
		$folder = $this->createMock(Folder::class);
		$user = $this->createMock(IUser::class);
		$folders = $this->createMock(ErpFolderService::class);
		$folders->expects(self::once())->method('ensurePurchaseOrderFolder')->with($user)->willReturn($folder);

		$prepared = (new PurchaseOrderDocumentService($orderMapper, $positions, $html, $renderer, $pdf, $folders))->prepare(42, $user);

		self::assertSame(91, $prepared->getDocumentFileId());
		self::assertSame('{"format":1}', $prepared->getLayoutSnapshot());
	}

	public function testPrepareReturnsExistingFileWithoutRenderingOrReplacingImmutableSnapshot(): void {
		$order = new PurchaseOrder();
		$order->setId(42);
		$order->setDocumentFileId(91);
		$order->setLayoutSnapshot('{"issued":true}');
		$orderMapper = $this->createMock(PurchaseOrderMapper::class);
		$orderMapper->method('findById')->willReturn($order);
		$service = new PurchaseOrderDocumentService($orderMapper, $this->createMock(PurchaseOrderPositionMapper::class), $this->createMock(DocumentHtmlBuilder::class), $this->createMock(PurchaseOrderPdfRenderer::class), $this->createMock(DocumentPdfService::class), $this->createMock(ErpFolderService::class));

		self::assertSame($order, $service->prepare(42, $this->createMock(IUser::class)));
		self::assertSame('{"issued":true}', $order->getLayoutSnapshot());
	}
}
