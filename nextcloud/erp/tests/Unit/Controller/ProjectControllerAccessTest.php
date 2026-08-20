<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Controller\ProjectController;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\ProjectService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProjectControllerAccessTest extends TestCase {
	private ProjectService&MockObject $projectService;
	private PermissionService&MockObject $permissionService;
	private ProjectController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->projectService = $this->createMock(ProjectService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('phpunit-user');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$this->controller = new ProjectController(
			'erp',
			$this->createMock(IRequest::class),
			$this->projectService,
			$this->permissionService,
			$userSession,
		);
	}

	public function testIndexRejectsWithoutReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::None);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->index();
	}

	public function testIndexAllowsWithReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->projectService->expects($this->once())->method('listProjects')->willReturn([]);

		$response = $this->controller->index();

		$this->assertSame([], $response->getData());
	}

	public function testCreateRequiresWriteNotJustRead(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->create('Neues Projekt');
	}

	public function testCreateSucceedsWithWritePermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->projectService->expects($this->once())->method('createProject');

		$this->controller->create('Neues Projekt');
	}
}
