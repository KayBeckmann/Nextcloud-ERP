<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Projects\OrderStatus;
use OCA\ERP\Service\OrderService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class OrderServiceTest extends TestCase {
	private const PROJECT_ID = 999999002;

	private OrderService $service;
	private OrderMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new OrderMapper($db);
		$this->service = new OrderService($this->mapper);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByProject(self::PROJECT_ID) as $order) {
			$this->mapper->delete($order);
		}
		parent::tearDown();
	}

	public function testCreateOrderDefaultsToDraft(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Ausführung', 'Beschreibung');
		$this->assertSame(OrderStatus::Draft->value, $order->getStatus());
		$this->assertSame('Beschreibung', $order->getDescription());
	}

	public function testUpdateOrderChangesStatus(): void {
		$order = $this->service->createOrder(self::PROJECT_ID, 'Ausführung', null);
		$updated = $this->service->updateOrder(self::PROJECT_ID, $order->getId(), 'Ausführung', OrderStatus::Confirmed, null);
		$this->assertSame('confirmed', $updated->getStatus());
	}

	public function testUpdateUnknownOrderThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->updateOrder(self::PROJECT_ID, 999999999, 'x', OrderStatus::Draft, null);
	}

	public function testListOrdersScopedToProject(): void {
		$this->service->createOrder(self::PROJECT_ID, 'Eigenes Projekt', null);
		$this->assertCount(1, $this->service->listOrders(self::PROJECT_ID));
		$this->assertCount(0, $this->service->listOrders(self::PROJECT_ID + 1));
	}
}
