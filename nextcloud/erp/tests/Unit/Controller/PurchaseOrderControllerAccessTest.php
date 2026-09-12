<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Controller\PurchaseOrderController;
use OCA\ERP\Db\PurchaseOrder;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\PurchaseOrderDocumentService;
use OCA\ERP\Service\PurchaseOrderService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\Files\IRootFolder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderControllerAccessTest extends TestCase {
	private function controllerForDocument(PurchaseOrderService $orders, IRootFolder $rootFolder, IUser $user): PurchaseOrderController {
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		return new PurchaseOrderController('erp', $this->createMock(IRequest::class), $orders, $this->createMock(PurchaseOrderDocumentService::class), $rootFolder, $permissions, $userSession);
	}

	private function orderWithDocument(?int $fileId): PurchaseOrder {
		$order = new PurchaseOrder();
		$order->setId(42);
		$order->setDocumentFileId($fileId);
		return $order;
	}

	public function testDocumentRequiresLagerReadPermission(): void {
		$orders = $this->createMock(PurchaseOrderService::class);
		$orders->expects(self::never())->method('get');
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('getEffectivePermission')->willReturn(PermissionLevel::None);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($this->createMock(IUser::class));
		$controller = new PurchaseOrderController('erp', $this->createMock(IRequest::class), $orders, $this->createMock(PurchaseOrderDocumentService::class), $this->createMock(IRootFolder::class), $permissions, $userSession);

		$this->expectException(OCSForbiddenException::class);
		$controller->document(42);
	}

	public function testDocumentReturnsNotFoundWhenNoPdfWasPrepared(): void {
		$orders = $this->createMock(PurchaseOrderService::class);
		$orders->method('get')->with(42)->willReturn(['order' => $this->orderWithDocument(null)]);
		$user = $this->createMock(IUser::class);
		$response = $this->controllerForDocument($orders, $this->createMock(IRootFolder::class), $user)->document(42);

		self::assertInstanceOf(DataResponse::class, $response);
		self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testDocumentResolvesOnlyThePersistedPurchaseOrderFileId(): void {
		$orders = $this->createMock(PurchaseOrderService::class);
		$orders->method('get')->with(42)->willReturn(['order' => $this->orderWithDocument(91)]);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('reader');
		$file = $this->createMock(File::class);
		$file->method('getMimeType')->willReturn('application/pdf');
		$file->method('getPath')->willReturn('/reader/files/ERP-Firma/ERP/Lieferanten/Bestellungen/PO-00042_2026-09-12T10-00.pdf');
		$file->method('getName')->willReturn('PO-00042_2026-09-12T10-00.pdf');
		$file->method('getEtag')->willReturn('etag');
		$file->method('getMTime')->willReturn(1);
		$folder = $this->createMock(Folder::class);
		$folder->expects(self::once())->method('getById')->with(91)->willReturn([$file]);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->with('reader')->willReturn($folder);

		$response = $this->controllerForDocument($orders, $root, $user)->document(42);

		self::assertInstanceOf(FileDisplayResponse::class, $response);
	}

	public function testDocumentReturnsNotFoundForMissingNonPdfOrUncontrolledFiles(): void {
		foreach ([
			'missing' => null,
			'non-pdf' => ['text/plain', '/reader/files/ERP-Firma/ERP/Lieferanten/Bestellungen/PO-00042.txt'],
			'uncontrolled' => ['application/pdf', '/reader/files/Personal/other.pdf'],
		] as $case => $fileDefinition) {
			$orders = $this->createMock(PurchaseOrderService::class);
			$orders->method('get')->willReturn(['order' => $this->orderWithDocument(91)]);
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('reader');
			$folder = $this->createMock(Folder::class);
			if ($fileDefinition === null) {
				$folder->method('getById')->with(91)->willReturn([]);
			} else {
				$file = $this->createMock(File::class);
				$file->method('getMimeType')->willReturn($fileDefinition[0]);
				$file->method('getPath')->willReturn($fileDefinition[1]);
				$folder->method('getById')->with(91)->willReturn([$file]);
			}
			$root = $this->createMock(IRootFolder::class);
			$root->method('getUserFolder')->with('reader')->willReturn($folder);

			$response = $this->controllerForDocument($orders, $root, $user)->document(42);

			self::assertInstanceOf(DataResponse::class, $response, $case);
			self::assertSame(Http::STATUS_NOT_FOUND, $response->getStatus(), $case);
		}
	}
	public function testPrepareDocumentRequiresWritePermission(): void {
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$user = $this->createMock(IUser::class);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$documents = $this->createMock(PurchaseOrderDocumentService::class);
		$documents->expects(self::never())->method('prepare');
		$controller = new PurchaseOrderController('erp', $this->createMock(IRequest::class), $this->createMock(PurchaseOrderService::class), $documents, $this->createMock(IRootFolder::class), $permissions, $userSession);

		$this->expectException(OCSForbiddenException::class);
		$controller->prepareDocument(1);
	}
}
