<?php

declare(strict_types=1);

namespace OCA\ERP\Command;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\Sharing\Service as AddressBookSharingService;
use OCA\DAV\DAV\Sharing\Backend as SharingBackend;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Idempotent provisioning for the role-scoped native Contacts address books.
 * Card creation/editing itself uses the public OCP\Contacts API; only resource
 * provisioning/sharing needs DAV's internal server service (ADR-0024).
 */
class ProvisionSharedAddressBook extends Command {
	private const OWNER_USER_ID = 'admin';

	/** @var array<string, array{displayName: string, shares: array<string, int>}> */
	private const ADDRESS_BOOKS = [
		'erp-kunden' => [
			'displayName' => 'ERP Kunden',
			'shares' => [
				'erp-projektleiter' => SharingBackend::ACCESS_READ_WRITE,
				'erp-monteure' => SharingBackend::ACCESS_READ,
			],
		],
		'erp-lieferanten' => [
			'displayName' => 'ERP Lieferanten',
			'shares' => [
				'erp-projektleiter' => SharingBackend::ACCESS_READ_WRITE,
			],
		],
	];

	public function __construct(
		private CardDavBackend $cardDavBackend,
		private AddressBookSharingService $sharingService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('erp:provision-shared-addressbook')
			->setDescription('Legt die dedizierten ERP Kunden-/Lieferanten-Adressbücher an und setzt ihre Rollenfreigaben.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$principalUri = 'principals/users/' . self::OWNER_USER_ID;
		foreach (self::ADDRESS_BOOKS as $uri => $configuration) {
			$addressBook = $this->cardDavBackend->getAddressBooksByUri($principalUri, $uri);
			if ($addressBook === null) {
				$addressBookId = (int) $this->cardDavBackend->createAddressBook($principalUri, $uri, [
					'{DAV:}displayname' => $configuration['displayName'],
				]);
				$output->writeln(sprintf('Adressbuch "%s" angelegt.', $configuration['displayName']));
			} else {
				$addressBookId = (int) $addressBook['id'];
				$output->writeln(sprintf('Adressbuch "%s" bereits vorhanden.', $configuration['displayName']));
			}
			foreach ($configuration['shares'] as $group => $access) {
				$this->sharingService->shareWith($addressBookId, 'principal:principals/groups/' . $group, $access);
				$output->writeln(sprintf('Freigegeben an "%s" (%s).', $group, $access === SharingBackend::ACCESS_READ ? 'read' : 'read-write'));
			}
		}
		return Command::SUCCESS;
	}
}
