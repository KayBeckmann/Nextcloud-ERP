<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Controller\ProjectController;
use OCA\ERP\Documents\DocumentType;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Service\PermissionService;
use OCA\ERP\Service\ProjectContactOverrideService;
use OCA\ERP\Service\ProjectService;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProjectControllerAccessTest extends TestCase {
	private ProjectService&MockObject $projectService;
	private ProjectContactOverrideService&MockObject $contactOverrideService;
	private PermissionService&MockObject $permissionService;
	private ProjectController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->projectService = $this->createMock(ProjectService::class);
		$this->contactOverrideService = $this->createMock(ProjectContactOverrideService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('phpunit-user');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$this->controller = new ProjectController(
			'erp',
			$this->createMock(IRequest::class),
			$this->projectService,
			$this->contactOverrideService,
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

	/** Ansprechpartner-Override je Projekt (ADR-0042). */
	public function testGetContactOverridesRejectsWithoutReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::None);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->getContactOverrides(1);
	}

	public function testGetContactOverridesAllowsWithReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->contactOverrideService->expects($this->once())->method('getForProject')->with(1)->willReturn(['invoice' => null]);

		$response = $this->controller->getContactOverrides(1);

		$this->assertSame(['invoice' => null], $response->getData());
	}

	public function testSetContactOverrideRequiresWriteNotJustRead(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->setContactOverride(1, 'invoice', 5);
	}

	public function testSetContactOverrideSucceedsWithWritePermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->contactOverrideService->expects($this->once())->method('set')->with(1, DocumentType::Invoice, 5);
		$this->contactOverrideService->method('getForProject')->willReturn(['invoice' => 5]);

		$this->controller->setContactOverride(1, 'invoice', 5);
	}

	public function testSetContactOverrideRejectsUnknownDocumentType(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->expectException(OCSBadRequestException::class);
		$this->controller->setContactOverride(1, 'not-a-type', 5);
	}
}
