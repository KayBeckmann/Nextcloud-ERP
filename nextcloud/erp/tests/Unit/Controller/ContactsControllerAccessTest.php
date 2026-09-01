<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Controller\ContactsController;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Reine Unit-Tests (keine DB/NC-Bootstrap nötig) für die sicherheitsrelevante
 * Rechte-Gate-Logik in ContactsController — verifiziert, dass Lesen ab
 * PermissionLevel::Read reicht, Schreiben aber Write erfordert (ADR-0009).
 */
final class ContactsControllerAccessTest extends TestCase {
	private ContactsService&MockObject $contactsService;
	private PermissionService&MockObject $permissionService;
	private ContactsController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->contactsService = $this->createMock(ContactsService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('phpunit-user');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$this->controller = new ContactsController(
			'erp',
			$this->createMock(IRequest::class),
			$this->contactsService,
			$this->permissionService,
			$userSession,
		);
	}

	public function testLinksRejectsWithoutReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::None);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->links('customer');
	}

	public function testLinksAllowsWithReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->contactsService->expects($this->once())->method('listLinks')->with(ContactRole::Customer)->willReturn([]);

		$response = $this->controller->links('customer');

		$this->assertSame([], $response->getData());
	}

	public function testCreateLinkRequiresWriteNotJustRead(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->createLink('contact-1', 'customer');
	}

	public function testCreateLinkSucceedsWithWritePermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->contactsService->expects($this->once())->method('createLink');

		$this->controller->createLink('contact-1', 'customer');
	}

	public function testUnknownRoleIsRejectedBeforeAnyPermissionCheck(): void {
		$this->permissionService->expects($this->never())->method('getEffectivePermission');
		$this->expectException(OCSBadRequestException::class);
		$this->controller->links('not-a-role');
	}

	/**
	 * Regressionstest für den Phase-14-Fund (ControllerRightsGateTest):
	 * search() durchsucht ALLE Adressbücher unabhängig von ERP-Links und
	 * braucht deshalb mindestens read auf kunden ODER lieferanten.
	 */
	public function testSearchRejectsWithoutAnyContactReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::None);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->search('foo');
	}

	public function testSearchAllowsWithReadOnEitherRole(): void {
		// Kunden=None, Lieferanten=Read reicht — die Suche ist laut
		// Implementierung bewusst rollenunabhängig ("irgendeine Rolle").
		$this->permissionService->method('getEffectivePermission')->willReturnCallback(
			fn ($user, $resource) => $resource->value === 'lieferanten' ? PermissionLevel::Read : PermissionLevel::None
		);
		$this->contactsService->expects($this->once())->method('search')->with('foo')->willReturn([]);

		$response = $this->controller->search('foo');

		$this->assertSame([], $response->getData());
	}
}
