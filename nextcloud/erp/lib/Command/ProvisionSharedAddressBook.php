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
 * Einmalige Provisionierung des geteilten "ERP Kontakte"-Adressbuchs
 * (ADR-0024) — anders als der Kalender pro User ist das Adressbuch bewusst
 * ein einzelnes, gemeinsames: `ContactsService` selbst braucht dafür keine
 * Codeänderung, `OCP\Contacts\IManager::search()` durchsucht automatisch
 * alle für den User sichtbaren Adressbücher, sobald eines geteilt ist.
 *
 * Das Adressbuch selbst muss vorher per `occ dav:create-addressbook admin
 * erp-kontakte` angelegt worden sein (kein OCP-Weg dafür verfügbar, siehe
 * ADR-0024) — dieser Befehl übernimmt nur das Freigeben.
 *
 * Nutzt dieselbe interne (nicht `OCP`-)API wie
 * {@see \OCA\ERP\Service\CalendarProvisioningService} — siehe dort für die
 * ausführliche Begründung der Abweichung von ADR-0009s OCP-only-Regel.
 */
class ProvisionSharedAddressBook extends Command {
	private const OWNER_USER_ID = 'admin';
	private const ADDRESS_BOOK_URI = 'erp-kontakte';
	private const SHARE_WITH_GROUPS = ['erp-projektleiter', 'erp-monteure'];

	public function __construct(
		private CardDavBackend $cardDavBackend,
		private AddressBookSharingService $sharingService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('erp:provision-shared-addressbook')
			->setDescription(
				'Gibt das Adressbuch "' . self::ADDRESS_BOOK_URI . '" (Owner "'
				. self::OWNER_USER_ID . '") an ' . implode(', ', self::SHARE_WITH_GROUPS)
				. ' frei (ADR-0024). Das Adressbuch selbst muss vorher per '
				. '"occ dav:create-addressbook ' . self::OWNER_USER_ID . ' ' . self::ADDRESS_BOOK_URI
				. '" angelegt worden sein.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$principalUri = 'principals/users/' . self::OWNER_USER_ID;
		$addressBook = $this->cardDavBackend->getAddressBooksByUri($principalUri, self::ADDRESS_BOOK_URI);
		if ($addressBook === null) {
			$output->writeln(sprintf(
				'<error>Adressbuch "%s" existiert nicht unter "%s" — zuerst anlegen: '
				. 'occ dav:create-addressbook %s %s</error>',
				self::ADDRESS_BOOK_URI,
				self::OWNER_USER_ID,
				self::OWNER_USER_ID,
				self::ADDRESS_BOOK_URI,
			));
			return 1;
		}

		$addressBookId = (int) $addressBook['id'];
		foreach (self::SHARE_WITH_GROUPS as $group) {
			$this->sharingService->shareWith(
				$addressBookId,
				'principal:principals/groups/' . $group,
				SharingBackend::ACCESS_READ_WRITE,
			);
			$output->writeln(sprintf('Freigegeben an Gruppe "%s" (read-write).', $group));
		}

		return 0;
	}
}
