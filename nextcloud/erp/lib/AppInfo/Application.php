<?php

declare(strict_types=1);

namespace OCA\ERP\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'erp';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Service-Registrierungen folgen ab Phase 2 (Rechte-/API-Grundlage).
	}

	public function boot(IBootContext $context): void {
	}
}
