<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\DeliveryNoteMapper;
use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\Project;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Service\DeliveryNoteService;
use OCA\ERP\Service\OrderService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class DeliveryNoteServiceTest extends TestCase {
	private DeliveryNoteService $service;
	private DeliveryNoteMapper $mapper;
	private DeliveryNotePositionMapper $positionMapper;
	private OrderMapper $orderMapper;
	private OrderPositionMapper $orderPositionMapper;
	private ProjectMapper $projectMapper;
	private int $projectId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new DeliveryNoteMapper($db);
		$this->positionMapper = new DeliveryNotePositionMapper($db);
		$this->orderMapper = new OrderMapper($db);
		$this->orderPositionMapper = new OrderPositionMapper($db);
		$this->projectMapper = new ProjectMapper($db);
		$this->service = new DeliveryNoteService($this->mapper, $this->positionMapper, $this->orderMapper, $this->orderPositionMapper);

		$project = new Project();
		$project->setTitle('phpunit-dn-project');
		$project->setStatus('draft');
		$project->setCreatedAt(time());
		$project->setUpdatedAt(time());
		$this->projectId = $this->projectMapper->insert($project)->getId();
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByProject($this->projectId) as $dn) {
			foreach ($this->positionMapper->findByDeliveryNote($dn->getId()) as $p) {
				$this->positionMapper->delete($p);
			}
			$this->mapper->delete($dn);
		}
		foreach ($this->orderMapper->findByProject($this->projectId) as $order) {
			foreach ($this->orderPositionMapper->findByOrder($order->getId()) as $p) {
				$this->orderPositionMapper->delete($p);
			}
			$this->orderMapper->delete($order);
		}
		$this->projectMapper->delete($this->projectMapper->findById($this->projectId));
		parent::tearDown();
	}

	private function createOrderWithPosition(string $positionType, float $quantity = 5.0): array {
		$order = new \OCA\ERP\Db\Order();
		$order->setProjectId($this->projectId);
		$order->setTitle('phpunit-order-for-dn');
		$order->setStatus('draft');
		$order->setCreatedAt(time());
		$order->setUpdatedAt(time());
		$order = $this->orderMapper->insert($order);

		$position = new \OCA\ERP\Db\OrderPosition();
		$position->setOrderId($order->getId());
		$position->setPositionType($positionType);
		$position->setDescription('Testposition');
		$position->setQuantity($quantity);
		$position->setUnit('Stk');
		$position->setUnitPriceNet(10.0);
		$position->setVatRatePercent(19.0);
		$position = $this->orderPositionMapper->insert($position);

		return [$order, $position];
	}

	public function testCreateDraftGeneratesNumberImmediately(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$this->assertSame(sprintf('L-%05d', $deliveryNote->getId()), $deliveryNote->getDeliveryNoteNumber());
		$this->assertSame('draft', $deliveryNote->getStatus());
	}

	public function testCreateDraftWithoutProjectThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->createDraft(0, null, null);
	}

	public function testAddPositionWithUnknownTypeThrows(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$this->expectException(\InvalidArgumentException::class);
		$this->service->addPosition($deliveryNote->getId(), 'not-a-type', null, 'x', 1.0, 'Stk');
	}

	/**
	 * Regressionstest analog zu QuotePosition (ADR-0011/Phase 5):
	 * 'custom' ist zufällig der PHP-Default von
	 * DeliveryNotePosition::$positionType.
	 */
	public function testCustomPositionTypeIsPersistedCorrectly(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$position = $this->service->addPosition($deliveryNote->getId(), 'custom', null, 'Sonderteil', 2.0, 'Stk');

		$this->assertSame('custom', $position->getPositionType());
		$reloaded = (new DeliveryNotePositionMapper(\OC::$server->get(IDBConnection::class)))
			->findOne($deliveryNote->getId(), $position->getId());
		$this->assertNotNull($reloaded);
		$this->assertSame('custom', $reloaded->getPositionType());
	}

	public function testIssueWithoutPositionsThrows(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$this->expectException(\DomainException::class);
		$this->service->issue($deliveryNote->getId());
	}

	public function testIssueSetsDeliveredAtAndMakesPositionsImmutable(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$this->service->addPosition($deliveryNote->getId(), 'custom', null, 'x', 1.0, 'Stk');

		$issued = $this->service->issue($deliveryNote->getId());
		$this->assertSame('issued', $issued->getStatus());
		$this->assertNotNull($issued->getDeliveredAt());

		$this->expectException(\DomainException::class);
		$this->service->addPosition($deliveryNote->getId(), 'custom', null, 'y', 1.0, 'Stk');
	}

	public function testRemovePosition(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$position = $this->service->addPosition($deliveryNote->getId(), 'custom', null, 'x', 1.0, 'Stk');

		$this->service->removePosition($deliveryNote->getId(), $position->getId());

		$full = $this->service->getFull($deliveryNote->getId());
		$this->assertCount(0, $full['positions']);
	}

	public function testListForProject(): void {
		$this->service->createDraft($this->projectId, null, null);
		$this->service->createDraft($this->projectId, null, null);

		$list = $this->service->listForProject($this->projectId);
		$this->assertCount(2, $list);
	}

	public function testCreateFromOrderCopiesArticlePosition(): void {
		[$order, $position] = $this->createOrderWithPosition('article', 5.0);

		$deliveryNote = $this->service->createFromOrder($order->getId(), [
			['orderPositionId' => $position->getId(), 'quantity' => 3.0],
		], 'Teillieferung');

		$this->assertSame($order->getId(), $deliveryNote->getOrderId());
		$full = $this->service->getFull($deliveryNote->getId());
		$this->assertCount(1, $full['positions']);
		$this->assertSame(3.0, $full['positions'][0]->getQuantity());
		$this->assertSame($position->getId(), $full['positions'][0]->getOrderPositionId());
	}

	public function testCreateFromOrderRejectsLaborPositions(): void {
		[$order, $position] = $this->createOrderWithPosition('labor', 5.0);

		$this->expectException(\DomainException::class);
		$this->service->createFromOrder($order->getId(), [
			['orderPositionId' => $position->getId(), 'quantity' => 1.0],
		], null);
	}

	public function testCreateFromOrderRejectsQuantityExceedingRemaining(): void {
		[$order, $position] = $this->createOrderWithPosition('product', 5.0);

		$this->expectException(\DomainException::class);
		$this->service->createFromOrder($order->getId(), [
			['orderPositionId' => $position->getId(), 'quantity' => 10.0],
		], null);
	}

	public function testCreateFromOrderWithEmptyPositionsThrows(): void {
		[$order] = $this->createOrderWithPosition('article', 5.0);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->createFromOrder($order->getId(), [], null);
	}
}
