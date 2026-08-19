<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Controller\PermissionsController;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regressionsschutz für ADR-0008: principals()/matrix()/setMatrixEntry()
 * müssen Nextcloud-Instanz-Admin bleiben (kein #[NoAdminRequired]), nur me()
 * darf für jeden eingeloggten User offen sein. Reine Reflection, ohne
 * Server-Bootstrap — die eigentliche Durchsetzung (403 für Nicht-Admins)
 * wurde zusätzlich manuell gegen die laufende Docker-Instanz verifiziert
 * (siehe docs/status.md).
 */
final class PermissionsControllerAccessTest extends TestCase {
	/**
	 * @dataProvider adminOnlyMethods
	 */
	public function testAdminOnlyMethodsHaveNoNoAdminRequiredAttribute(string $method): void {
		$this->assertHasNoAdminRequired($method, false);
	}

	public function testMeIsOpenToAnyLoggedInUser(): void {
		$this->assertHasNoAdminRequired('me', true);
	}

	/** @return array<string, list<string>> */
	public static function adminOnlyMethods(): array {
		return [
			'principals' => ['principals'],
			'matrix' => ['matrix'],
			'setMatrixEntry' => ['setMatrixEntry'],
		];
	}

	private function assertHasNoAdminRequired(string $method, bool $expected): void {
		$reflection = new ReflectionMethod(PermissionsController::class, $method);
		$has = count($reflection->getAttributes(NoAdminRequired::class)) > 0;
		$this->assertSame($expected, $has, "PermissionsController::$method NoAdminRequired mismatch");
	}
}
