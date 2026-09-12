<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\Sharing\Service as AddressBookSharingService;
use OCA\DAV\DAV\Sharing\Backend as SharingBackend;
use OCA\ERP\Service\SharedAddressBookProvisioner;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SharedAddressBookProvisionerTest extends TestCase {
	private CardDavBackend&MockObject $cardDavBackend;
	private AddressBookSharingService&MockObject $sharingService;
	private IConfig&MockObject $config;
	private IUserSession&MockObject $userSession;

	protected function setUp(): void {
		parent::setUp();
		$this->cardDavBackend = $this->createMock(CardDavBackend::class);
		$this->sharingService = $this->createMock(AddressBookSharingService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->config->method('getAppValue')->willReturn('');
	}

	private function subject(): SharedAddressBookProvisioner {
		return new SharedAddressBookProvisioner($this->cardDavBackend, $this->sharingService, $this->config, $this->userSession);
	}

	public function testEnsureCreatesMissingRoleAddressBooksAndAppliesEveryRequiredShare(): void {
		$this->cardDavBackend->expects($this->exactly(2))
			->method('getAddressBooksByUri')
			->willReturn(null);
		$this->cardDavBackend->expects($this->exactly(2))
			->method('createAddressBook')
			->with('principals/users/kay', $this->isType('string'), $this->isType('array'))
			->willReturnOnConsecutiveCalls(101, 102);

		$shares = [];
		$this->sharingService->expects($this->exactly(3))
			->method('shareWith')
			->willReturnCallback(static function (int $addressBookId, string $principal, int $access) use (&$shares): void {
				$shares[] = [$addressBookId, $principal, $access];
			});

		$this->subject()->ensureFor('kay');

		self::assertSame([
			[101, 'principal:principals/groups/erp-projektleiter', SharingBackend::ACCESS_READ_WRITE],
			[101, 'principal:principals/groups/erp-monteure', SharingBackend::ACCESS_READ],
			[102, 'principal:principals/groups/erp-projektleiter', SharingBackend::ACCESS_READ_WRITE],
		], $shares);
	}

	public function testEnsureReusesExistingAddressBooksAndStillRepairsRoleShares(): void {
		$this->cardDavBackend->expects($this->exactly(2))
			->method('getAddressBooksByUri')
			->willReturnOnConsecutiveCalls(['id' => 77], ['id' => 88]);
		$this->cardDavBackend->expects($this->never())->method('createAddressBook');
		$this->sharingService->expects($this->exactly(3))->method('shareWith');

		$this->subject()->ensureFor('kay');
	}

	public function testEnsureStoresTheFirstAuthenticatedUserAsTheStableAddressBookOwner(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('kay');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->expects($this->once())->method('setAppValue')->with('erp', 'shared_addressbook_owner', 'kay');
		$this->cardDavBackend->method('getAddressBooksByUri')->willReturn(null);
		$this->cardDavBackend->expects($this->exactly(2))->method('createAddressBook')->with('principals/users/kay', $this->anything(), $this->anything())->willReturnOnConsecutiveCalls(1, 2);
		$this->sharingService->expects($this->exactly(3))->method('shareWith');

		$this->subject()->ensure();
	}
}
