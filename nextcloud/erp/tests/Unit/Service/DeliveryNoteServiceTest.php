<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\DeliveryNoteGroupMapper;
use OCA\ERP\Db\DeliveryNoteMapper;
use OCA\ERP\Db\DeliveryNotePositionMapper;
use OCA\ERP\Db\OrderGroupMapper;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Db\OrderPositionMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Service\DeliveryNoteService;
use OCA\ERP\Service\DocumentPdfService;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\OrderService;
use OCA\ERP\Service\ProjectService;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

/**
 * @group DB
 */
final class DeliveryNoteServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-dn-user';

	private DeliveryNoteService $service;
	private DeliveryNoteMapper $mapper;
	private DeliveryNotePositionMapper $positionMapper;
	private DeliveryNoteGroupMapper $groupMapper;
	private OrderMapper $orderMapper;
	private OrderPositionMapper $orderPositionMapper;
	private OrderGroupMapper $orderGroupMapper;
	private ProjectMapper $projectMapper;
	private IUser $user;
	private int $projectId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new DeliveryNoteMapper($db);
		$this->positionMapper = new DeliveryNotePositionMapper($db);
		$this->groupMapper = new DeliveryNoteGroupMapper($db);
		$this->orderMapper = new OrderMapper($db);
		$this->orderPositionMapper = new OrderPositionMapper($db);
		$this->orderGroupMapper = new OrderGroupMapper($db);
		$this->projectMapper = new ProjectMapper($db);
		$folderService = new ErpFolderService(\OC::$server->get(IRootFolder::class));
		$projectService = new ProjectService($this->projectMapper, $folderService);
		$this->service = new DeliveryNoteService(
			$this->mapper,
			$this->positionMapper,
			$this->groupMapper,
			$this->orderMapper,
			$this->orderPositionMapper,
			$this->orderGroupMapper,
			$folderService,
			$projectService,
			new DocumentPdfService(),
		);

		$userManager = \OC::$server->get(IUserManager::class);
		if ($userManager->userExists(self::TEST_UID)) {
			$userManager->get(self::TEST_UID)->delete();
		}
		$this->user = $userManager->createUser(self::TEST_UID, 'Phpunit-Test-Pass-1!');
		self::loginAsUser(self::TEST_UID);

		$project = $projectService->createProject($this->user, 'phpunit-dn-project', null, null, null);
		$this->projectId = $project->getId();
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
			foreach ($this->orderGroupMapper->findByOrder($order->getId()) as $g) {
				$this->orderGroupMapper->delete($g);
			}
			$this->orderMapper->delete($order);
		}
		$this->projectMapper->delete($this->projectMapper->findById($this->projectId));
		self::logout();
		$this->user->delete();
		parent::tearDown();
	}

	private function createOrderWithPosition(string $positionType, float $quantity = 5.0, ?int $groupId = null): array {
		$order = new \OCA\ERP\Db\Order();
		$order->setProjectId($this->projectId);
		$order->setTitle('phpunit-order-for-dn');
		$order->setStatus('draft');
		$order->setCreatedAt(time());
		$order->setUpdatedAt(time());
		$order = $this->orderMapper->insert($order);

		$position = new \OCA\ERP\Db\OrderPosition();
		$position->setOrderId($order->getId());
		$position->setGroupId($groupId);
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
		$this->service->addPosition($deliveryNote->getId(), null, 'not-a-type', null, 'x', 1.0, 'Stk');
	}

	/**
	 * Regressionstest analog zu QuotePosition (ADR-0011/Phase 5):
	 * 'custom' ist zufällig der PHP-Default von
	 * DeliveryNotePosition::$positionType.
	 */
	public function testCustomPositionTypeIsPersistedCorrectly(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$position = $this->service->addPosition($deliveryNote->getId(), null, 'custom', null, 'Sonderteil', 2.0, 'Stk');

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
		$this->service->addPosition($deliveryNote->getId(), null, 'custom', null, 'x', 1.0, 'Stk');

		$issued = $this->service->issue($deliveryNote->getId());
		$this->assertSame('issued', $issued->getStatus());
		$this->assertNotNull($issued->getDeliveredAt());

		$this->expectException(\DomainException::class);
		$this->service->addPosition($deliveryNote->getId(), null, 'custom', null, 'y', 1.0, 'Stk');
	}

	/** ADR-0021: PDF wird beim Ausstellen abgelegt, wenn ein Issuer übergeben wird. */
	public function testIssueWithIssuerWritesPdfDocument(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$this->service->addPosition($deliveryNote->getId(), null, 'custom', null, 'x', 1.0, 'Stk');
		$this->assertNull($deliveryNote->getDocumentFileId());

		$issued = $this->service->issue($deliveryNote->getId(), $this->user);
		$this->assertNotNull($issued->getDocumentFileId());
	}

	public function testRemovePosition(): void {
		$deliveryNote = $this->service->createDraft($this->projectId, null, null);
		$position = $this->service->addPosition($deliveryNote->getId(), null, 'custom', null, 'x', 1.0, 'Stk');

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

	public function testCreateFromOrderPreservesGroup(): void {
		$order = new \OCA\ERP\Db\Order();
		$order->setProjectId($this->projectId);
		$order->setTitle('phpunit-order-with-group');
		$order->setStatus('draft');
		$order->setCreatedAt(time());
		$order->setUpdatedAt(time());
		$order = $this->orderMapper->insert($order);

		$group = new \OCA\ERP\Db\OrderGroup();
		$group->setOrderId($order->getId());
		$group->setTitle('Elektrik');
		$group = $this->orderGroupMapper->insert($group);

		$position = new \OCA\ERP\Db\OrderPosition();
		$position->setOrderId($order->getId());
		$position->setGroupId($group->getId());
		$position->setPositionType('article');
		$position->setDescription('Kabel');
		$position->setQuantity(5.0);
		$position->setUnit('Stk');
		$position->setUnitPriceNet(10.0);
		$position->setVatRatePercent(19.0);
		$position = $this->orderPositionMapper->insert($position);

		$deliveryNote = $this->service->createFromOrder($order->getId(), [
			['orderPositionId' => $position->getId(), 'quantity' => 2.0],
		], null);

		$full = $this->service->getFull($deliveryNote->getId());
		$this->assertCount(1, $full['groups']);
		$this->assertSame('Elektrik', $full['groups'][0]->getTitle());
		$this->assertSame($full['groups'][0]->getId(), $full['positions'][0]->getGroupId());
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
