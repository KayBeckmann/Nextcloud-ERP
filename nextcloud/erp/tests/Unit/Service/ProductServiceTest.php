<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\ProductComponentMapper;
use OCA\ERP\Db\ProductLaborMapper;
use OCA\ERP\Db\ProductMapper;
use OCA\ERP\Service\ProductService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class ProductServiceTest extends TestCase {
	private ProductService $service;
	private ProductMapper $mapper;
	private ProductComponentMapper $componentMapper;
	private ProductLaborMapper $laborMapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new ProductMapper($db);
		$this->componentMapper = new ProductComponentMapper($db);
		$this->laborMapper = new ProductLaborMapper($db);
		$this->service = new ProductService($this->mapper, $this->componentMapper, $this->laborMapper);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $product) {
			if (str_starts_with($product->getName(), 'phpunit-')) {
				foreach ($this->componentMapper->findByProduct($product->getId()) as $c) {
					$this->componentMapper->delete($c);
				}
				foreach ($this->laborMapper->findByProduct($product->getId()) as $l) {
					$this->laborMapper->delete($l);
				}
				$this->mapper->delete($product);
			}
		}
		parent::tearDown();
	}

	public function testGetWithComponentsIncludesMaterialAndLabor(): void {
		$product = $this->service->create('phpunit-product-1', null, null, null);
		$this->service->addComponent($product->getId(), 1, 2.0, 'Stk');
		$this->service->addLabor($product->getId(), 1, 6.0);

		$full = $this->service->getWithComponents($product->getId());
		$this->assertCount(1, $full['components']);
		$this->assertCount(1, $full['labor']);
		$this->assertSame(2.0, $full['components'][0]->getQuantity());
		$this->assertSame(6.0, $full['labor'][0]->getHours());
	}

	public function testAddComponentToUnknownProductThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->addComponent(999999999, 1, 1.0, 'Stk');
	}

	public function testRemoveComponentAndLabor(): void {
		$product = $this->service->create('phpunit-product-2', null, null, null);
		$component = $this->service->addComponent($product->getId(), 1, 1.0, 'Stk');
		$labor = $this->service->addLabor($product->getId(), 1, 1.0);

		$this->service->removeComponent($product->getId(), $component->getId());
		$this->service->removeLabor($product->getId(), $labor->getId());

		$full = $this->service->getWithComponents($product->getId());
		$this->assertCount(0, $full['components']);
		$this->assertCount(0, $full['labor']);
	}
}
