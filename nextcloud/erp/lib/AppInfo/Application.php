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
		// Composer-Autoloader für echte Runtime-Dependencies (aktuell nur
		// dompdf/dompdf, ADR-0021). Nextclouds eigener App-Loader sucht nur
		// <appPath>/composer/autoload.php, nicht das Standard-Composer-
		// Vendor-Verzeichnis — ohne diesen require bleiben Fremdklassen wie
		// Dompdf\Dompdf "Class not found", obwohl `composer install` sauber
		// durchlief (siehe Nextcloud Dependency-Management-Doku).
		require_once __DIR__ . '/../../vendor/autoload.php';
		// Service-Registrierungen folgen ab Phase 2 (Rechte-/API-Grundlage).
	}

	public function boot(IBootContext $context): void {
	}
}
