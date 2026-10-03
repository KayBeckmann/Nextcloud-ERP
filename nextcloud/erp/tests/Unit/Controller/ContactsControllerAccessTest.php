<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Controller\ContactsController;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Service\ContactPersonService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\PermissionService;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
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
	private ContactPersonService&MockObject $contactPersonService;
	private PermissionService&MockObject $permissionService;
	private ContactsController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->contactsService = $this->createMock(ContactsService::class);
		$this->contactPersonService = $this->createMock(ContactPersonService::class);
		$this->permissionService = $this->createMock(PermissionService::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('phpunit-user');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$this->controller = new ContactsController(
			'erp',
			$this->createMock(IRequest::class),
			$this->contactsService,
			$this->contactPersonService,
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

	public function testCardsListsOnlyTheRequestedRoleWithReadPermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->contactsService->expects($this->once())
			->method('listCards')
			->with(ContactRole::Supplier)
			->willReturn([['uid' => 'supplier-1', 'displayName' => 'Muster Lieferant']]);

		$response = $this->controller->cards('supplier');

		$this->assertSame([['uid' => 'supplier-1', 'displayName' => 'Muster Lieferant']], $response->getData());
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

	public function testCreateCardRequiresWriteOnItsRole(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);

		$this->expectException(OCSForbiddenException::class);
		$this->controller->createCard('customer', 'ACME GmbH');
	}

	public function testCreateCardUsesTheRoleScopedContactsService(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->contactsService->expects($this->once())
			->method('createCard')
			->with(ContactRole::Supplier, ['fullName' => 'Muster Lieferant', 'email' => '', 'phone' => '', 'address' => ''])
			->willReturn(['uid' => 'native-1', 'displayName' => 'Muster Lieferant', 'emails' => [], 'uri' => 'native-1.vcf']);

		$response = $this->controller->createCard('supplier', 'Muster Lieferant');

		$this->assertSame('native-1', $response->getData()['uid']);
	}

	public function testUpdateCardRequiresWriteOnTheExistingRole(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);

		$this->expectException(OCSForbiddenException::class);
		$this->controller->updateCard('customer', 'native-1', 'ACME GmbH');
	}

	public function testDeleteCardRequiresWriteOnItsRole(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);

		$this->expectException(OCSForbiddenException::class);
		$this->controller->deleteCard('supplier', 'native-1');
	}

	public function testDeleteCardDelegatesRoleScopedDeletionWithWritePermission(): void {
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->contactsService->expects($this->once())->method('deleteCard')->with(ContactRole::Customer, 'native-1');

		$this->assertSame([], $this->controller->deleteCard('customer', 'native-1')->getData());
	}

	/**
	 * Ansprechpartner (ADR-0041): listPersons/createPerson prüfen die Rolle
	 * der zugehörigen Kunden-/Lieferanten-Verknüpfung (wie updateLink/
	 * deleteLink), nicht eine eigene Ressource.
	 */
	public function testListPersonsRejectsUnknownContactLink(): void {
		$this->contactsService->method('getLinkRole')->willReturn(null);
		$this->expectException(OCSNotFoundException::class);
		$this->controller->listPersons(999);
	}

	public function testListPersonsRejectsWithoutReadPermission(): void {
		$this->contactsService->method('getLinkRole')->willReturn(ContactRole::Customer);
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::None);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->listPersons(1);
	}

	public function testListPersonsAllowsWithReadPermission(): void {
		$this->contactsService->method('getLinkRole')->willReturn(ContactRole::Customer);
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->contactPersonService->expects($this->once())->method('listForLink')->with(1)->willReturn([]);

		$response = $this->controller->listPersons(1);

		$this->assertSame([], $response->getData());
	}

	public function testCreatePersonRequiresWriteNotJustRead(): void {
		$this->contactsService->method('getLinkRole')->willReturn(ContactRole::Customer);
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->createPerson(1, 'Lars Zimmermann', 'CEO');
	}

	public function testCreatePersonSucceedsWithWritePermission(): void {
		$this->contactsService->method('getLinkRole')->willReturn(ContactRole::Customer);
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->contactPersonService->expects($this->once())->method('create')->with(1, 'Lars Zimmermann', 'CEO', null, null, null);

		$this->controller->createPerson(1, 'Lars Zimmermann', 'CEO');
	}

	public function testUpdatePersonRejectsUnknownPerson(): void {
		$this->contactPersonService->method('getRoleFor')->willReturn(null);
		$this->expectException(OCSNotFoundException::class);
		$this->controller->updatePerson(999, 'Lars Zimmermann');
	}

	public function testUpdatePersonRequiresWriteNotJustRead(): void {
		$this->contactPersonService->method('getRoleFor')->willReturn(ContactRole::Customer);
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->updatePerson(1, 'Lars Zimmermann');
	}

	public function testDeletePersonRequiresWritePermission(): void {
		$this->contactPersonService->method('getRoleFor')->willReturn(ContactRole::Supplier);
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Read);
		$this->expectException(OCSForbiddenException::class);
		$this->controller->deletePerson(1);
	}

	public function testDeletePersonSucceedsWithWritePermission(): void {
		$this->contactPersonService->method('getRoleFor')->willReturn(ContactRole::Supplier);
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->contactPersonService->expects($this->once())->method('delete')->with(1);

		$this->assertSame([], $this->controller->deletePerson(1)->getData());
	}

	/** deleteLink räumt die Ansprechpartner dieser Verknüpfung mit auf (ADR-0041) — sie haben keine eigene vCard. */
	public function testDeleteLinkCascadesToContactPersons(): void {
		$this->contactsService->method('getLinkRole')->willReturn(ContactRole::Customer);
		$this->permissionService->method('getEffectivePermission')->willReturn(PermissionLevel::Write);
		$this->contactPersonService->expects($this->once())->method('deleteAllForLink')->with(1);
		$this->contactsService->expects($this->once())->method('deleteLink')->with(1);

		$this->controller->deleteLink(1);
	}
}
