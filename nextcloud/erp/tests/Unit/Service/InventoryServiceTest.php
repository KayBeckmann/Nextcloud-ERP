<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\ArticleMapper;
use OCA\ERP\Db\InventoryCountMapper;
use OCA\ERP\Db\InventoryMapper;
use OCA\ERP\Db\StockLevelMapper;
use OCA\ERP\Db\StockMovementMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Service\InventoryService;
use OCA\ERP\Service\StockService;
use OCA\ERP\Service\WarehouseService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class InventoryServiceTest extends TestCase {
	private InventoryService $service;
	private InventoryMapper $mapper;
	private InventoryCountMapper $countMapper;
	private StockLevelMapper $levelMapper;
	private StockService $stockService;
	private WarehouseMapper $warehouseMapper;
	private ArticleMapper $articleMapper;
	private int $warehouseId;
	private int $articleId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new InventoryMapper($db);
		$this->countMapper = new InventoryCountMapper($db);
		$this->levelMapper = new StockLevelMapper($db);
		$this->stockService = new StockService($this->levelMapper, new StockMovementMapper($db));
		$this->warehouseMapper = new WarehouseMapper($db);
		$warehouseService = new WarehouseService($this->warehouseMapper);
		$this->articleMapper = new ArticleMapper($db);

		$this->service = new InventoryService($this->mapper, $this->countMapper, $this->levelMapper, $this->stockService);

		$this->warehouseId = $warehouseService->create('phpunit-inventory-warehouse', 'central', null, null)->getId();
		$article = new \OCA\ERP\Db\Article();
		$article->setName('phpunit-inventory-article');
		$article->setUnit('Stk');
		$article->setCreatedAt(time());
		$article->setUpdatedAt(time());
		$this->articleId = $this->articleMapper->insert($article)->getId();

		$this->stockService->recordMovement($this->articleId, $this->warehouseId, 20.0, 'receipt', null, null, 'kay', null);
	}

	protected function tearDown(): void {
		$level = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		if ($level !== null) {
			$this->levelMapper->delete($level);
		}
		$this->articleMapper->delete($this->articleMapper->findById($this->articleId));
		$this->warehouseMapper->delete($this->warehouseMapper->findById($this->warehouseId));
		parent::tearDown();
	}

	public function testStartCreatesOpenInventory(): void {
		$inventory = $this->service->start($this->warehouseId, 'kay', 'Jahresinventur');
		$this->assertSame('open', $inventory->getStatus());
	}

	public function testRecordCountSnapshotsCurrentStock(): void {
		$inventory = $this->service->start($this->warehouseId, 'kay', null);
		$count = $this->service->recordCount($inventory->getId(), $this->articleId, 18.0);

		$this->assertSame(20.0, $count->getExpectedQuantity());
		$this->assertSame(18.0, $count->getCountedQuantity());
		$this->assertSame(-2.0, $count->jsonSerialize()['difference']);
	}

	public function testRecordCountTwiceUpdatesInsteadOfDuplicating(): void {
		$inventory = $this->service->start($this->warehouseId, 'kay', null);
		$this->service->recordCount($inventory->getId(), $this->articleId, 18.0);
		$this->service->recordCount($inventory->getId(), $this->articleId, 19.0);

		$counts = $this->countMapper->findByInventory($inventory->getId());
		$this->assertCount(1, $counts);
		$this->assertSame(19.0, $counts[0]->getCountedQuantity());
	}

	public function testCloseBooksCorrectionMovementForDifference(): void {
		$inventory = $this->service->start($this->warehouseId, 'kay', null);
		$this->service->recordCount($inventory->getId(), $this->articleId, 18.0);

		$closed = $this->service->close($inventory->getId(), 'kay');
		$this->assertSame('closed', $closed->getStatus());
		$this->assertNotNull($closed->getClosedAt());

		$level = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(18.0, $level->getQuantityOnHand());
	}

	public function testCloseSkipsMovementWhenNoDifference(): void {
		$inventory = $this->service->start($this->warehouseId, 'kay', null);
		$this->service->recordCount($inventory->getId(), $this->articleId, 20.0); // = expected, no diff
		$this->service->close($inventory->getId(), 'kay');

		$level = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(20.0, $level->getQuantityOnHand());
	}

	public function testRecordCountAfterCloseThrows(): void {
		$inventory = $this->service->start($this->warehouseId, 'kay', null);
		$this->service->close($inventory->getId(), 'kay');

		$this->expectException(\DomainException::class);
		$this->service->recordCount($inventory->getId(), $this->articleId, 5.0);
	}

	public function testCloseTwiceThrows(): void {
		$inventory = $this->service->start($this->warehouseId, 'kay', null);
		$this->service->close($inventory->getId(), 'kay');

		$this->expectException(\DomainException::class);
		$this->service->close($inventory->getId(), 'kay');
	}
}
