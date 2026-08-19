<?php

declare(strict_types=1);

namespace OCA\ERP\Permissions;

/**
 * Geordnete Berechtigungsstufen (Roadmap Phase 2). Die Reihenfolge der Cases
 * ist bewusst aufsteigend — {@see self::rank()} nutzt sie, um bei mehreren
 * anwendbaren Einträgen (User + mehrere Gruppen) die höchste Stufe zu ermitteln.
 */
enum PermissionLevel: string {
	case None = 'none';
	case Read = 'read';
	case Write = 'write';
	case Approve = 'approve';
	case Admin = 'admin';

	/** Höher = weitreichender. Für den "höchste Stufe gewinnt"-Vergleich. */
	public function rank(): int {
		return match ($this) {
			self::None => 0,
			self::Read => 1,
			self::Write => 2,
			self::Approve => 3,
			self::Admin => 4,
		};
	}

	public static function highest(self $a, self $b): self {
		return $a->rank() >= $b->rank() ? $a : $b;
	}

	public function atLeast(self $required): bool {
		return $this->rank() >= $required->rank();
	}
}
