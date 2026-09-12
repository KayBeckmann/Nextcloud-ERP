<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\PurchaseOrder;
use OCA\ERP\Db\PurchaseOrderMapper;
use OCA\ERP\Db\PurchaseOrderPositionMapper;
use OCP\IUser;

/** Explicit preparation only: snapshot, render, and write one immutable purchase-order PDF. */
class PurchaseOrderDocumentService {
	public function __construct(
		private PurchaseOrderMapper $orderMapper,
		private PurchaseOrderPositionMapper $positionMapper,
		private DocumentHtmlBuilder $htmlBuilder,
		private PurchaseOrderPdfRenderer $renderer,
		private DocumentPdfService $pdfService,
		private ErpFolderService $folderService,
	) {
	}

	/** @throws \OutOfBoundsException */
	public function prepare(int $purchaseOrderId, IUser $issuer): PurchaseOrder {
		$order = $this->orderMapper->findById($purchaseOrderId);
		if ($order === null) {
			throw new \OutOfBoundsException('Purchase order not found');
		}
		if ($order->getDocumentFileId() !== null) {
			return $order;
		}

		$number = sprintf('PO-%05d', $order->getId());
		$snapshot = $order->getLayoutSnapshot() ?? $this->htmlBuilder->snapshot(
			'purchase_order', $number, 'Bestellung', $order->getCreatedAt(), null, $order->getSupplierContactUid(),
		);
		$positions = array_map(static fn ($position): array => $position->jsonSerialize(), $this->positionMapper->findByPurchaseOrder($order->getId()));
		$html = $this->renderer->render($order, $positions, $snapshot);
		$fileId = $this->pdfService->writePdf($this->folderService->ensurePurchaseOrderFolder($issuer), $number, $html);
		$order->setLayoutSnapshot($snapshot);
		$order->setDocumentFileId($fileId);
		return $this->orderMapper->update($order);
	}
}
