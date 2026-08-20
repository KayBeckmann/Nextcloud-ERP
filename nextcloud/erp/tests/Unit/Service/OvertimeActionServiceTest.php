<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\OvertimeActionMapper;
use OCA\ERP\Service\OvertimeActionService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class OvertimeActionServiceTest extends TestCase {
	private OvertimeActionService $service;
	private OvertimeActionMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new OvertimeActionMapper($db);
		$this->service = new OvertimeActionService($this->mapper);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByUser('phpunit-overtime-user') as $action) {
			$this->mapper->delete($action);
		}
		parent::tearDown();
	}

	public function testCreateWithInvalidActionTypeThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->create('phpunit-overtime-user', 5.0, 'invalid', null);
	}

	public function testFullApprovalWorkflowForCompensate(): void {
		$action = $this->service->create('phpunit-overtime-user', 3.5, 'compensate', 'Abbummeln');
		$this->assertSame('requested', $action->getStatus());

		$approved = $this->service->approve($action->getId());
		$this->assertSame('approved', $approved->getStatus());

		$done = $this->service->complete($action->getId());
		$this->assertSame('done', $done->getStatus());
	}

	public function testCompleteBeforeApproveThrows(): void {
		$action = $this->service->create('phpunit-overtime-user', 2.0, 'payout', null);
		$this->expectException(\DomainException::class);
		$this->service->complete($action->getId());
	}

	public function testRejectRequestedAction(): void {
		$action = $this->service->create('phpunit-overtime-user', 1.0, 'payout', null);
		$rejected = $this->service->reject($action->getId());
		$this->assertSame('rejected', $rejected->getStatus());
	}

	public function testApproveAlreadyApprovedActionThrows(): void {
		$action = $this->service->create('phpunit-overtime-user', 1.0, 'compensate', null);
		$this->service->approve($action->getId());

		$this->expectException(\DomainException::class);
		$this->service->approve($action->getId());
	}
}
