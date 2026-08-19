<?php

declare(strict_types=1);

namespace OCA\ERP\Permissions;

/**
 * Reine Auflösungslogik ohne DB-/Nextcloud-Abhängigkeit (ADR-0008) — damit sie
 * schnell und ohne Server-Bootstrap testbar bleibt. {@see PermissionService}
 * lädt die Rohdaten aus der DB/Nextcloud und ruft diese Klasse auf.
 */
final class PermissionResolver {
	/**
	 * @param list<array{principalType: string, principalId: string, resourceType: string, permission: string}> $entries
	 *   Alle Rechte-Einträge, die für den User relevant sein könnten (i.d.R.
	 *   bereits auf den User + seine Gruppen vorgefiltert, aber ein Aufruf mit
	 *   der vollständigen Tabelle ist ebenfalls korrekt — irrelevante
	 *   Principals werden schlicht nicht berücksichtigt).
	 * @param list<string> $groupIds Gruppen, in denen der User Mitglied ist.
	 */
	public static function resolve(
		array $entries,
		string $userId,
		array $groupIds,
		ResourceType $resource,
		bool $isNextcloudAdmin = false,
	): PermissionLevel {
		if ($isNextcloudAdmin) {
			// ADR-0008: Nextcloud-Instanz-Admins haben immer Vollzugriff,
			// unabhängig von erp_permissions — verhindert Aussperrung.
			return PermissionLevel::Admin;
		}

		$groupSet = array_flip($groupIds);
		$level = PermissionLevel::None;

		foreach ($entries as $entry) {
			if ($entry['resourceType'] !== $resource->value) {
				continue;
			}
			$applies = ($entry['principalType'] === 'user' && $entry['principalId'] === $userId)
				|| ($entry['principalType'] === 'group' && isset($groupSet[$entry['principalId']]));
			if (!$applies) {
				continue;
			}
			$entryLevel = PermissionLevel::tryFrom($entry['permission']);
			if ($entryLevel === null) {
				continue; // unbekannter/kaputter Wert wird ignoriert statt zu crashen
			}
			$level = PermissionLevel::highest($level, $entryLevel);
		}

		return $level;
	}

	/**
	 * Effektive Rechte für alle Ressourcen auf einen Schlag (für die
	 * "/permissions/me"-API und die Web-UI-Gate-Logik).
	 *
	 * @param list<array{principalType: string, principalId: string, resourceType: string, permission: string}> $entries
	 * @param list<string> $groupIds
	 * @return array<string, string> resourceType => PermissionLevel-Wert
	 */
	public static function resolveAll(
		array $entries,
		string $userId,
		array $groupIds,
		bool $isNextcloudAdmin = false,
	): array {
		$result = [];
		foreach (ResourceType::cases() as $resource) {
			$result[$resource->value] = self::resolve($entries, $userId, $groupIds, $resource, $isNextcloudAdmin)->value;
		}
		return $result;
	}
}
