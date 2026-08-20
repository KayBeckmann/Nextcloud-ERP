<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Controller\QuoteController;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\QuoteService;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class QuoteControllerAccessTest extends TestCase {
	private QuoteService&MockObject $quoteService;
	private PermissionService&MockObject $permissionService;
	private QuoteController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->quoteService = $this->createMock(QuoteService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('phpunit-user');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$this->controller = new QuoteController(
			'erp',
			$this->createMock(IRequest::class),
			$this->quoteService,
			$this->permissionService,
			$userSession,
		);
	}

	public function testIndexRejectsWithoutReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::None);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->index();
	}

	public function testCreateRequiresWriteNotJustRead(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->create('Neues Angebot');
	}

	public function testAddPositionRejectsUnknownPositionTypeBeforeServiceCall(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->quoteService->expects($this->never())->method('addPosition');

		$this->expectException(OCSBadRequestException::class);
		$this->controller->addPosition(1, 'not-a-type', 'x', 1.0, 1.0, 19.0);
	}

	public function testAddPositionSucceedsWithValidTypeAndWritePermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->quoteService->expects($this->once())->method('addPosition');

		$this->controller->addPosition(1, 'custom', 'Anfahrt', 1.0, 25.0, 7.0);
	}

	public function testUpdateRejectsUnknownStatus(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->expectException(OCSBadRequestException::class);
		$this->controller->update(1, 'Titel', 'not-a-status');
	}
}
