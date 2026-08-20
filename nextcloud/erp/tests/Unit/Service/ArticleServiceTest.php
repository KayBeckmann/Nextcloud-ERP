<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\ArticleMapper;
use OCA\ERP\Db\ArticleSupplierPriceMapper;
use OCA\ERP\Service\ArticleService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class ArticleServiceTest extends TestCase {
	private ArticleService $service;
	private ArticleMapper $mapper;
	private ArticleSupplierPriceMapper $priceMapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new ArticleMapper($db);
		$this->priceMapper = new ArticleSupplierPriceMapper($db);
		$this->service = new ArticleService($this->mapper, $this->priceMapper);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $article) {
			if (str_starts_with($article->getName(), 'phpunit-')) {
				foreach ($this->priceMapper->findByArticle($article->getId()) as $price) {
					$this->priceMapper->delete($price);
				}
				$this->mapper->delete($article);
			}
		}
		parent::tearDown();
	}

	public function testBestPurchasePriceIsNullWithoutPrices(): void {
		$article = $this->service->create('phpunit-article-1', null, null, 'Stk', null, null, null);
		$this->assertNull($this->service->bestPurchasePrice($article->getId()));
	}

	public function testBestPurchasePriceIsTheCheapest(): void {
		$article = $this->service->create('phpunit-article-2', null, null, 'Stk', null, null, null);
		$this->service->addSupplierPrice($article->getId(), 'supplier-a', null, 5.0, 'EUR', null, null);
		$this->service->addSupplierPrice($article->getId(), 'supplier-b', null, 3.5, 'EUR', null, null);

		$this->assertSame(3.5, $this->service->bestPurchasePrice($article->getId()));
	}

	public function testGetWithPricesIncludesAllSupplierPrices(): void {
		$article = $this->service->create('phpunit-article-3', 'Gira', 'GI-1', 'Stk', 'Elektro', null, null);
		$this->service->addSupplierPrice($article->getId(), 'supplier-a', 'SA-1', 1.0, 'EUR', null, null);

		$full = $this->service->getWithPrices($article->getId());
		$this->assertCount(1, $full['supplierPrices']);
	}

	public function testAddSupplierPriceToUnknownArticleThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->addSupplierPrice(999999999, 'supplier-a', null, 1.0, 'EUR', null, null);
	}

	public function testRemoveSupplierPrice(): void {
		$article = $this->service->create('phpunit-article-4', null, null, 'Stk', null, null, null);
		$price = $this->service->addSupplierPrice($article->getId(), 'supplier-a', null, 1.0, 'EUR', null, null);

		$this->service->removeSupplierPrice($article->getId(), $price->getId());

		$this->assertCount(0, $this->priceMapper->findByArticle($article->getId()));
	}
}
