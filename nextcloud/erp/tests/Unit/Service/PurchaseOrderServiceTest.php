<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\Article;
use OCA\ERP\Db\ArticleMapper;
use OCA\ERP\Db\PurchaseOrderMapper;
use OCA\ERP\Db\PurchaseOrderPositionMapper;
use OCA\ERP\Db\PurchaseOrderReceiptMapper;
use OCA\ERP\Db\PurchaseOrderStatusChangeMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\StockLevelMapper;
use OCA\ERP\Db\StockMovementMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Service\PurchaseOrderService;
use OCA\ERP\Service\StockService;
use OCA\ERP\Service\WarehouseService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class PurchaseOrderServiceTest extends TestCase {
	private PurchaseOrderService $service;
	private PurchaseOrderMapper $orderMapper;
	private PurchaseOrderPositionMapper $positionMapper;
	private PurchaseOrderStatusChangeMapper $statusMapper;
	private StockMovementMapper $movementMapper;
	private ArticleMapper $articleMapper;
	private WarehouseMapper $warehouseMapper;
	private int $articleId;
	private int $warehouseId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->orderMapper = new PurchaseOrderMapper($db);
		$this->positionMapper = new PurchaseOrderPositionMapper($db);
		$this->statusMapper = new PurchaseOrderStatusChangeMapper($db);
		$this->movementMapper = new StockMovementMapper($db);
		$this->articleMapper = new ArticleMapper($db);
		$this->warehouseMapper = new WarehouseMapper($db);
		$this->service = new PurchaseOrderService(
			$this->orderMapper,
			$this->positionMapper,
			$this->statusMapper,
			new PurchaseOrderReceiptMapper($db),
			new StockService(new StockLevelMapper($db), $this->movementMapper),
		);
		$this->warehouseId = (new WarehouseService($this->warehouseMapper, new ProjectMapper($db)))->create('phpunit-po-warehouse', 'central', null, null)->getId();
		$article = new Article();
		$article->setName('phpunit-po-article');
		$article->setUnit('Stk');
		$article->setCreatedAt(time());
		$article->setUpdatedAt(time());
		$this->articleId = $this->articleMapper->insert($article)->getId();
	}

	protected function tearDown(): void {
		foreach ($this->orderMapper->findAll() as $order) {
			if ($order->getCreatedBy() !== 'phpunit-po-user') {
				continue;
			}
			foreach ($this->positionMapper->findByPurchaseOrder($order->getId()) as $position) {
				foreach ($this->statusMapper->findByPurchaseOrder($order->getId()) as $status) {
					$this->statusMapper->delete($status);
				}
				$this->positionMapper->delete($position);
			}
			$this->orderMapper->delete($order);
		}
		$this->articleMapper->delete($this->articleMapper->findById($this->articleId));
		$this->warehouseMapper->delete($this->warehouseMapper->findById($this->warehouseId));
		parent::tearDown();
	}

	public function testCreatesDraftWithSnapshottedArticlePositionWithoutStockMovement(): void {
		$order = $this->service->createDraft('supplier-a', [[
			'articleId' => $this->articleId,
			'description' => 'Kabel NYM-J 3x2,5',
			'quantityOrdered' => 12.0,
			'unit' => 'm',
			'supplierArticleNo' => 'NYM-325',
			'unitPurchasePrice' => 1.25,
			'currency' => 'EUR',
			'warehouseId' => $this->warehouseId,
			'projectId' => null,
		]], 'phpunit-po-user');

		self::assertSame('draft', $order->getStatus());
		self::assertSame('supplier-a', $order->getSupplierContactUid());
		$positions = $this->positionMapper->findByPurchaseOrder($order->getId());
		self::assertCount(1, $positions);
		self::assertSame(12.0, $positions[0]->getQuantityOrdered());
		self::assertSame(0.0, $positions[0]->getQuantityReceived());
		self::assertSame('NYM-325', $positions[0]->getSupplierArticleNo());
		self::assertSame([], $this->movementMapper->findByArticleAndWarehouse($this->articleId, $this->warehouseId));
		self::assertCount(1, $this->statusMapper->findByPurchaseOrder($order->getId()));
	}

	public function testOnlyAllowsAuditedLifecycleTransitions(): void {
		$order = $this->service->createDraft('supplier-a', [[
			'description' => 'Freitext', 'quantityOrdered' => 1.0, 'unit' => 'Stk', 'warehouseId' => $this->warehouseId,
		]], 'phpunit-po-user');
		$approved = $this->service->transitionStatus($order->getId(), 'approved', 'phpunit-po-user', 'fachlich geprüft');
		self::assertSame('approved', $approved->getStatus());
		self::assertCount(2, $this->statusMapper->findByPurchaseOrder($order->getId()));
		$this->expectException(\DomainException::class);
		$this->service->transitionStatus($order->getId(), 'received', 'phpunit-po-user', null);
	}

	public function testReceivingGoodsIsExplicitAuditedAndUpdatesStockOnlyThen(): void {
		$order = $this->service->createDraft('supplier-a', [[
			'articleId' => $this->articleId, 'description' => 'Schrauben', 'quantityOrdered' => 10.0, 'unit' => 'Stk', 'warehouseId' => $this->warehouseId,
		]], 'phpunit-po-user');
		$this->service->transitionStatus($order->getId(), 'approved', 'phpunit-po-user');
		$this->service->transitionStatus($order->getId(), 'sent', 'phpunit-po-user');
		$position = $this->positionMapper->findByPurchaseOrder($order->getId())[0];
		$received = $this->service->receive($position->getId(), 4.0, $this->warehouseId, 'phpunit-po-user', 'Lieferschein 123');
		self::assertSame('partially_received', $received->getStatus());
		self::assertSame(4.0, $this->positionMapper->findByPurchaseOrder($order->getId())[0]->getQuantityReceived());
		$movements = $this->movementMapper->findByArticleAndWarehouse($this->articleId, $this->warehouseId);
		self::assertCount(1, $movements);
		self::assertSame('receipt', $movements[0]->getMovementType());
		$this->expectException(\DomainException::class);
		$this->service->receive($position->getId(), 7.0, $this->warehouseId, 'phpunit-po-user');
	}

}
