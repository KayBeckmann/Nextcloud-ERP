<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Permissions;

use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\PermissionResolver;
use OCA\ERP\Permissions\ResourceType;
use PHPUnit\Framework\TestCase;

/**
 * Bewusst PHPUnit\Framework\TestCase statt Test\TestCase: PermissionResolver
 * hat keine DB-/Nextcloud-Abhängigkeit, der Test soll ohne Server-Bootstrap
 * laufen (siehe ADR-0008).
 */
final class PermissionResolverTest extends TestCase {
	public function testNoEntriesMeansNone(): void {
		$level = PermissionResolver::resolve([], 'alice', [], ResourceType::Projekte);
		$this->assertSame(PermissionLevel::None, $level);
	}

	public function testDirectUserEntryApplies(): void {
		$entries = [
			['principalType' => 'user', 'principalId' => 'alice', 'resourceType' => 'projekte', 'permission' => 'write'],
		];
		$level = PermissionResolver::resolve($entries, 'alice', [], ResourceType::Projekte);
		$this->assertSame(PermissionLevel::Write, $level);
	}

	public function testGroupEntryAppliesForMember(): void {
		$entries = [
			['principalType' => 'group', 'principalId' => 'monteure', 'resourceType' => 'projekte', 'permission' => 'read'],
		];
		$level = PermissionResolver::resolve($entries, 'alice', ['monteure'], ResourceType::Projekte);
		$this->assertSame(PermissionLevel::Read, $level);
	}

	public function testGroupEntryIgnoredForNonMember(): void {
		$entries = [
			['principalType' => 'group', 'principalId' => 'monteure', 'resourceType' => 'projekte', 'permission' => 'read'],
		];
		$level = PermissionResolver::resolve($entries, 'alice', ['buero'], ResourceType::Projekte);
		$this->assertSame(PermissionLevel::None, $level);
	}

	public function testHighestOfMultipleApplicableEntriesWins(): void {
		$entries = [
			['principalType' => 'group', 'principalId' => 'monteure', 'resourceType' => 'projekte', 'permission' => 'read'],
			['principalType' => 'user', 'principalId' => 'alice', 'resourceType' => 'projekte', 'permission' => 'approve'],
			['principalType' => 'group', 'principalId' => 'buero', 'resourceType' => 'projekte', 'permission' => 'write'],
		];
		$level = PermissionResolver::resolve($entries, 'alice', ['monteure', 'buero'], ResourceType::Projekte);
		$this->assertSame(PermissionLevel::Approve, $level);
	}

	public function testEntriesForOtherResourcesAreIgnored(): void {
		$entries = [
			['principalType' => 'user', 'principalId' => 'alice', 'resourceType' => 'rechnungen', 'permission' => 'admin'],
		];
		$level = PermissionResolver::resolve($entries, 'alice', [], ResourceType::Projekte);
		$this->assertSame(PermissionLevel::None, $level);
	}

	public function testNextcloudAdminAlwaysGetsAdmin(): void {
		$level = PermissionResolver::resolve([], 'admin', [], ResourceType::Einstellungen, isNextcloudAdmin: true);
		$this->assertSame(PermissionLevel::Admin, $level);
	}

	public function testUnknownPermissionValueIsIgnoredNotFatal(): void {
		$entries = [
			['principalType' => 'user', 'principalId' => 'alice', 'resourceType' => 'projekte', 'permission' => 'kaputt'],
		];
		$level = PermissionResolver::resolve($entries, 'alice', [], ResourceType::Projekte);
		$this->assertSame(PermissionLevel::None, $level);
	}

	public function testResolveAllCoversEveryResourceType(): void {
		$all = PermissionResolver::resolveAll([], 'alice', []);
		$this->assertCount(count(ResourceType::cases()), $all);
		foreach (ResourceType::cases() as $resource) {
			$this->assertArrayHasKey($resource->value, $all);
			$this->assertSame('none', $all[$resource->value]);
		}
	}
}
