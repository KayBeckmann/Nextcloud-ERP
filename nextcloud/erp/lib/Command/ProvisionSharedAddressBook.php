<?php

declare(strict_types=1);

namespace OCA\ERP\Command;

use OCA\ERP\Service\SharedAddressBookProvisioner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manual repair entrypoint. Normal ERP Contacts use auto-provisioning through
 * ContactsService; this command remains useful for an administrator to repair
 * the shared addressbooks proactively.
 */
class ProvisionSharedAddressBook extends Command {
	public function __construct(private SharedAddressBookProvisioner $provisioner) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('erp:provision-shared-addressbook')
			->setDescription('Prüft die dedizierten ERP Kunden-/Lieferanten-Adressbücher und setzt ihre Rollenfreigaben.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->provisioner->ensure();
		$output->writeln('ERP Kunden-/Lieferanten-Adressbücher und Rollenfreigaben geprüft.');
		return Command::SUCCESS;
	}
}
