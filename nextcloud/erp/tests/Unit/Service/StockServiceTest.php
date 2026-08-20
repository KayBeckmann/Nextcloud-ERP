<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\ArticleMapper;
use OCA\ERP\Db\StockLevelMapper;
use OCA\ERP\Db\StockMovementMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Service\StockService;
use OCA\ERP\Service\WarehouseService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class StockServiceTest extends TestCase {
	private StockService $service;
	private StockLevelMapper $levelMapper;
	private StockMovementMapper $movementMapper;
	private WarehouseService $warehouseService;
	private WarehouseMapper $warehouseMapper;
	private ArticleMapper $articleMapper;
	private int $warehouseId;
	private int $articleId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->levelMapper = new StockLevelMapper($db);
		$this->movementMapper = new StockMovementMapper($db);
		$this->warehouseMapper = new WarehouseMapper($db);
		$this->warehouseService = new WarehouseService($this->warehouseMapper);
		$this->articleMapper = new ArticleMapper($db);
		$this->service = new StockService($this->levelMapper, $this->movementMapper);

		$this->warehouseId = $this->warehouseService->create('phpunit-warehouse', 'central', null, null)->getId();

		$article = new \OCA\ERP\Db\Article();
		$article->setName('phpunit-stock-article');
		$article->setUnit('Stk');
		$article->setCreatedAt(time());
		$article->setUpdatedAt(time());
		$this->articleId = $this->articleMapper->insert($article)->getId();
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

	public function testRecordMovementIncreasesOnHandAndWritesAuditTrail(): void {
		$this->service->recordMovement($this->articleId, $this->warehouseId, 10.0, 'receipt', null, null, 'kay', 'Erstbestand');

		$level = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(10.0, $level->getQuantityOnHand());

		$movements = $this->movementMapper->findByArticleAndWarehouse($this->articleId, $this->warehouseId);
		$this->assertCount(1, $movements);
		$this->assertSame('receipt', $movements[0]->getMovementType());
	}

	public function testConsumptionReducesOnHand(): void {
		$this->service->recordMovement($this->articleId, $this->warehouseId, 10.0, 'receipt', null, null, 'kay', null);
		$this->service->recordMovement($this->articleId, $this->warehouseId, -4.0, 'consumption', 'project', 1, 'kay', null);

		$level = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(6.0, $level->getQuantityOnHand());
	}

	public function testConsumptionBeyondOnHandThrows(): void {
		$this->service->recordMovement($this->articleId, $this->warehouseId, 5.0, 'receipt', null, null, 'kay', null);
		$this->expectException(\DomainException::class);
		$this->service->recordMovement($this->articleId, $this->warehouseId, -10.0, 'consumption', null, null, 'kay', null);
	}

	public function testUnknownMovementTypeThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->recordMovement($this->articleId, $this->warehouseId, 1.0, 'unknown', null, null, 'kay', null);
	}

	/**
	 * Regressionstest analog zu QuotePosition (ADR-0011/Phase 5):
	 * 'consumption' ist zufällig der PHP-Default von StockMovement::$movementType.
	 */
	public function testConsumptionMovementTypeIsPersistedCorrectly(): void {
		$this->service->recordMovement($this->articleId, $this->warehouseId, 10.0, 'receipt', null, null, 'kay', null);
		$this->service->recordMovement($this->articleId, $this->warehouseId, -1.0, 'consumption', null, null, 'kay', null);

		$reloaded = (new StockMovementMapper(\OC::$server->get(IDBConnection::class)))
			->findByArticleAndWarehouse($this->articleId, $this->warehouseId);
		$this->assertSame('consumption', $reloaded[0]->getMovementType());
	}

	public function testTransferMovesStockBetweenWarehouses(): void {
		$targetWarehouse = $this->warehouseService->create('phpunit-warehouse-2', 'central', null, null);
		$this->service->recordMovement($this->articleId, $this->warehouseId, 10.0, 'receipt', null, null, 'kay', null);

		$this->service->transfer($this->articleId, $this->warehouseId, $targetWarehouse->getId(), 4.0, 'kay', 'Umlagerung');

		$source = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		$target = $this->levelMapper->findOne($this->articleId, $targetWarehouse->getId());
		$this->assertSame(6.0, $source->getQuantityOnHand());
		$this->assertSame(4.0, $target->getQuantityOnHand());

		$this->levelMapper->delete($target);
		$this->warehouseMapper->delete($this->warehouseMapper->findById($targetWarehouse->getId()));
	}

	public function testReserveAndReleaseAdjustReservedQuantity(): void {
		$this->service->recordMovement($this->articleId, $this->warehouseId, 10.0, 'receipt', null, null, 'kay', null);
		$this->service->reserve($this->articleId, $this->warehouseId, 3.0);

		$level = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(3.0, $level->getQuantityReserved());
		$this->assertSame(7.0, $level->jsonSerialize()['sollQuantity']);

		$this->service->release($this->articleId, $this->warehouseId, 3.0);
		$level = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		$this->assertSame(0.0, $level->getQuantityReserved());
	}

	public function testReleaseMoreThanReservedThrows(): void {
		$this->service->reserve($this->articleId, $this->warehouseId, 2.0);
		$this->expectException(\DomainException::class);
		$this->service->release($this->articleId, $this->warehouseId, 5.0);
	}

	public function testSetMinQuantityCreatesLevelIfMissing(): void {
		$level = $this->service->setMinQuantity($this->articleId, $this->warehouseId, 5.0);
		$this->assertSame(5.0, $level->getMinQuantity());
		$this->assertSame(0.0, $level->getQuantityOnHand());
	}
}
