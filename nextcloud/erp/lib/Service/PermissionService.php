<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\PermissionEntry;
use OCA\ERP\Db\PermissionMapper;
use OCA\ERP\Permissions\PermissionLevel;
use OCA\ERP\Permissions\PermissionResolver;
use OCA\ERP\Permissions\ResourceType;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Verbindet die reine {@see PermissionResolver}-Logik mit der DB
 * (`erp_permissions`) und den Nextcloud-User-/Gruppendiensten (ADR-0008).
 */
class PermissionService {
	public function __construct(
		private PermissionMapper $mapper,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
	) {
	}

	/** @return list<array{principalType: string, principalId: string, resourceType: string, permission: string}> */
	private function allEntriesAsArrays(): array {
		return array_map(
			static fn (PermissionEntry $e): array => [
				'principalType' => $e->getPrincipalType(),
				'principalId' => $e->getPrincipalId(),
				'resourceType' => $e->getResourceType(),
				'permission' => $e->getPermission(),
			],
			$this->mapper->findAll(),
		);
	}

	/** @return list<string> */
	public function groupIdsFor(IUser $user): array {
		return array_map(
			static fn ($group) => $group->getGID(),
			$this->groupManager->getUserGroups($user),
		);
	}

	public function getEffectivePermission(IUser $user, ResourceType $resource): PermissionLevel {
		return PermissionResolver::resolve(
			$this->allEntriesAsArrays(),
			$user->getUID(),
			$this->groupIdsFor($user),
			$resource,
			$this->groupManager->isAdmin($user->getUID()),
		);
	}

	/** @return array<string, string> resourceType => PermissionLevel-Wert */
	public function getEffectivePermissions(IUser $user): array {
		return PermissionResolver::resolveAll(
			$this->allEntriesAsArrays(),
			$user->getUID(),
			$this->groupIdsFor($user),
			$this->groupManager->isAdmin($user->getUID()),
		);
	}

	/** @return list<array{principalType: string, principalId: string, resourceType: string, permission: string}> */
	public function listMatrix(): array {
		return $this->allEntriesAsArrays();
	}

	/**
	 * Alle wählbaren Principals (Nextcloud-User + -Gruppen) für die
	 * Rechte-Matrix-UI.
	 *
	 * @return list<array{type: string, id: string, displayName: string}>
	 */
	public function listPrincipals(): array {
		$principals = [];
		foreach ($this->groupManager->search('') as $group) {
			$principals[] = ['type' => 'group', 'id' => $group->getGID(), 'displayName' => $group->getDisplayName()];
		}
		foreach ($this->userManager->search('') as $user) {
			$principals[] = ['type' => 'user', 'id' => $user->getUID(), 'displayName' => $user->getDisplayName()];
		}
		return $principals;
	}

	/**
	 * Nextcloud-User suchen — für Auswahl-Dropdowns wie "Verantwortlicher"
	 * im Projekt (ADR-0015). Bewusst ohne Rechte-Gate: welche Nextcloud-User
	 * es gibt, ist keine sensible Information (dieselbe Einschätzung wie
	 * bei ContactsService::search()).
	 *
	 * @return list<array{uid: string, displayName: string}>
	 */
	public function searchUsers(string $q, int $limit = 20): array {
		$results = [];
		foreach ($this->userManager->searchDisplayName($q, $limit) as $user) {
			$results[] = ['uid' => $user->getUID(), 'displayName' => $user->getDisplayName()];
		}
		return $results;
	}

	/** Anzeigename für eine bekannte User-UID nachschlagen — für UserPicker beim Editieren (ADR-0015). */
	public function displayNameForUser(string $uid): string {
		return $this->userManager->get($uid)?->getDisplayName() ?? $uid;
	}

	/**
	 * Setzt/entfernt einen Rechte-Eintrag. `PermissionLevel::None` löscht den
	 * Eintrag (kein Eintrag = "none" per Default, siehe ADR-0008) statt ihn
	 * mit dem Wert 'none' zu persistieren.
	 *
	 * @throws \InvalidArgumentException wenn principalType weder 'user' noch 'group' ist
	 */
	public function setPermission(string $principalType, string $principalId, ResourceType $resource, PermissionLevel $level): void {
		if ($principalType !== 'user' && $principalType !== 'group') {
			throw new \InvalidArgumentException("principalType must be 'user' or 'group', got '$principalType'");
		}

		$existing = $this->mapper->findOneByPrincipalAndResource($principalType, $principalId, $resource->value);

		if ($level === PermissionLevel::None) {
			if ($existing !== null) {
				$this->mapper->delete($existing);
			}
			return;
		}

		$now = time();
		if ($existing !== null) {
			$existing->setPermission($level->value);
			$existing->setUpdatedAt($now);
			$this->mapper->update($existing);
			return;
		}

		$entry = new PermissionEntry();
		$entry->setPrincipalType($principalType);
		$entry->setPrincipalId($principalId);
		$entry->setResourceType($resource->value);
		$entry->setPermission($level->value);
		$entry->setCreatedAt($now);
		$entry->setUpdatedAt($now);
		$this->mapper->insert($entry);
	}
}
