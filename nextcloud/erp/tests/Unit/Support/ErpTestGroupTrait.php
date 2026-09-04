<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Support;

use OCP\IGroupManager;
use OCP\IUser;

/**
 * Fügt einen PHPUnit-Testuser der Gruppe "erp-projektleiter" hinzu, damit er
 * den gemeinsamen Group Folder "ERP-Firma" sieht (ADR-0024) — Voraussetzung
 * für jeden Integrationstest, der über ErpFolderService (direkt oder via
 * ProjectService/VehicleService/…) echte Dateien anlegt. Der Group Folder
 * selbst ist bewusst einmalige Infrastruktur-Provisionierung außerhalb der
 * Tests (siehe docker/README.md bzw. .github/workflows/ci.yml) — dieser
 * Trait übernimmt nur die Gruppenzuordnung des jeweiligen Testusers.
 */
trait ErpTestGroupTrait {
	private function addToErpGroup(IUser $user, string $group = 'erp-projektleiter'): void {
		$groupManager = \OC::$server->get(IGroupManager::class);
		$erpGroup = $groupManager->get($group) ?? $groupManager->createGroup($group);
		$erpGroup?->addUser($user);
	}

	private function removeFromErpGroup(IUser $user, string $group = 'erp-projektleiter'): void {
		\OC::$server->get(IGroupManager::class)->get($group)?->removeUser($user);
	}
}
