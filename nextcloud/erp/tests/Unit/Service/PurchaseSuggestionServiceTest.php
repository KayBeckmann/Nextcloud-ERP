<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\Article;
use OCA\ERP\Db\ArticleMapper;
use OCA\ERP\Db\ArticleSupplierPrice;
use OCA\ERP\Db\ArticleSupplierPriceMapper;
use OCA\ERP\Db\StockLevelMapper;
use OCA\ERP\Db\StockMovementMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Service\PurchaseSuggestionService;
use OCA\ERP\Service\StockService;
use OCA\ERP\Service\WarehouseService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class PurchaseSuggestionServiceTest extends TestCase {
	private PurchaseSuggestionService $service;
	private StockService $stockService;
	private StockLevelMapper $levelMapper;
	private ArticleMapper $articleMapper;
	private ArticleSupplierPriceMapper $supplierPriceMapper;
	private WarehouseMapper $warehouseMapper;
	private int $warehouseId;
	private int $articleId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->levelMapper = new StockLevelMapper($db);
		$this->stockService = new StockService($this->levelMapper, new StockMovementMapper($db));
		$this->articleMapper = new ArticleMapper($db);
		$this->supplierPriceMapper = new ArticleSupplierPriceMapper($db);
		$this->warehouseMapper = new WarehouseMapper($db);
		$warehouseService = new WarehouseService($this->warehouseMapper);

		$this->service = new PurchaseSuggestionService($this->levelMapper, $this->articleMapper, $this->supplierPriceMapper, $this->warehouseMapper);

		$this->warehouseId = $warehouseService->create('phpunit-suggestion-warehouse', 'central', null, null)->getId();

		$article = new Article();
		$article->setName('phpunit-suggestion-article');
		$article->setUnit('Stk');
		$article->setCreatedAt(time());
		$article->setUpdatedAt(time());
		$this->articleId = $this->articleMapper->insert($article)->getId();

		$expensive = new ArticleSupplierPrice();
		$expensive->setArticleId($this->articleId);
		$expensive->setSupplierContactUid('supplier-expensive');
		$expensive->setPurchasePrice(9.0);
		$expensive->setCreatedAt(time());
		$expensive->setUpdatedAt(time());
		$this->supplierPriceMapper->insert($expensive);

		$cheap = new ArticleSupplierPrice();
		$cheap->setArticleId($this->articleId);
		$cheap->setSupplierContactUid('supplier-cheap');
		$cheap->setPurchasePrice(3.0);
		$cheap->setCreatedAt(time());
		$cheap->setUpdatedAt(time());
		$this->supplierPriceMapper->insert($cheap);
	}

	protected function tearDown(): void {
		foreach ($this->supplierPriceMapper->findByArticle($this->articleId) as $p) {
			$this->supplierPriceMapper->delete($p);
		}
		$level = $this->levelMapper->findOne($this->articleId, $this->warehouseId);
		if ($level !== null) {
			$this->levelMapper->delete($level);
		}
		$this->articleMapper->delete($this->articleMapper->findById($this->articleId));
		$this->warehouseMapper->delete($this->warehouseMapper->findById($this->warehouseId));
		parent::tearDown();
	}

	public function testSuggestsArticleBelowMinimumWithCheapestSupplierFirst(): void {
		$this->stockService->recordMovement($this->articleId, $this->warehouseId, 2.0, 'receipt', null, null, 'kay', null);
		$this->stockService->setMinQuantity($this->articleId, $this->warehouseId, 5.0);

		$suggestions = $this->service->suggestions($this->warehouseId);
		$this->assertCount(1, $suggestions);
		$this->assertSame($this->articleId, $suggestions[0]['articleId']);
		$this->assertSame(3.0, $suggestions[0]['suggestedQuantity']);
		$this->assertSame('supplier-cheap', $suggestions[0]['supplierOptions'][0]['supplierContactUid']);
	}

	public function testArticleAboveMinimumIsNotSuggested(): void {
		$this->stockService->recordMovement($this->articleId, $this->warehouseId, 10.0, 'receipt', null, null, 'kay', null);
		$this->stockService->setMinQuantity($this->articleId, $this->warehouseId, 5.0);

		$suggestions = $this->service->suggestions($this->warehouseId);
		$this->assertCount(0, $suggestions);
	}
}
