<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Permissions;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Rollen-/Rechte-Testmatrix (Roadmap Phase 14, "Rollen/Rechte getestet").
 *
 * Statt für jeden der (Stand 2026-09-01) 31 Controller einen eigenen,
 * weitgehend identischen Access-Test zu duplizieren, prüft dieser Test
 * strukturell über alle Controller hinweg: Jede öffentliche Action-Methode
 * muss entweder
 *
 *  (a) einen ERP-Rechte-Check aufrufen (`requireLevel()`/`requireUser()` aus
 *      AbstractResourceController, oder das gleichwertige manuelle Muster
 *      `getEffectivePermission()->atLeast()` der älteren Controller aus
 *      Phase 2-4), oder
 *  (b) bewusst OHNE `#[NoAdminRequired]` sein — dann erzwingt Nextclouds
 *      eigenes AppFramework bereits "nur Instanz-Admin" ohne App-Code
 *      (Muster von PermissionsController::matrix()/setMatrixEntry()), oder
 *  (c) explizit in {@see self::ALLOWED_WITHOUT_GATE} als bewusste, bereits
 *      dokumentierte Ausnahme eingetragen sein.
 *
 * Fängt die Klasse von Regressionsfehlern ab, bei denen ein neuer Endpunkt
 * ohne jede Rechteprüfung committet wird — ohne dabei die *fachliche*
 * Korrektheit jedes einzelnen Gates zu behaupten (welches ResourceType/
 * PermissionLevel *richtig* ist, bleibt Aufgabe der bestehenden
 * modul-spezifischen Access-Tests und PermissionResolver-Tests).
 */
final class ControllerRightsGateTest extends TestCase {
	/**
	 * Öffentliche Methoden, die bewusst ohne ERP-Rechte-Gate auskommen —
	 * jeweils mit Verweis auf die Begründung im Controller selbst.
	 *
	 * @var array<string, list<string>>
	 */
	private const ALLOWED_WITHOUT_GATE = [
		// Statische Metadaten ohne Nutzdaten (Roadmap Phase 2).
		'ApiController' => ['status'],
		// Liefert nur die leere SPA-Hülle aus, keine Nutzdaten.
		'PageController' => ['index'],
		// Jeder eingeloggte User darf seine EIGENE ERP-Ordnerstruktur
		// sicherstellen/abrufen — keine geteilte Ablage (ADR-0009).
		'FilesController' => ['erpFolder'],
		// Löst nur eine BEREITS BEKANNTE Contact-UID zu einem Anzeigenamen
		// auf (z. B. für einen Projektkunden, dessen Sichtbarkeit schon über
		// ResourceType::Projekte gegated ist) — kein Enumerations-Risiko wie
		// bei search(). Gleiches Muster wie PermissionsController::resolveUser().
		'ContactsController' => ['resolve'],
		// Kein eigenes ERP-Rechte-Gate — es gilt Nextclouds normale
		// Datei-Sichtbarkeit (getUserFolder()->getById() liefert nur
		// Dateien, die für den eingeloggten User überhaupt sichtbar sind),
		// derselbe Vertrauenslevel wie ein regulärer Files-App-Link (ADR-0021,
		// siehe auch docs/api/v1.md).
		'DocumentsController' => ['show'],
		// Bewusst für jeden eingeloggten User offen — User-Suche/-Auflösung
		// für Zuweisungs-Dropdowns (Verantwortlicher, Termin-Zuweisung),
		// analog zu ContactsController::resolve() (ADR-0015).
		// me(): jeder eingeloggte User darf seine EIGENEN effektiven
		// Rechte abfragen — kein zusätzliches Gate nötig oder sinnvoll.
		'PermissionsController' => ['users', 'resolveUser', 'me'],
	];

	/** Erkennbare ERP-Rechte-Check-Aufrufe im Methodenquelltext. */
	private const GATE_CALL_PATTERN = '/requireLevel\(|requireUser\(|atLeast\(|getEffectivePermission\(/';

	public function testEveryPublicControllerActionHasARightsGate(): void {
		$dir = __DIR__ . '/../../../lib/Controller';
		$files = glob($dir . '/*.php');
		self::assertNotEmpty($files, 'Controller-Verzeichnis nicht gefunden — Testpfad prüfen.');

		$checked = 0;

		foreach ($files as $file) {
			$shortName = basename($file, '.php');
			$class = 'OCA\\ERP\\Controller\\' . $shortName;

			if (!class_exists($class)) {
				self::fail("Controller-Klasse $class konnte nicht geladen werden (Autoloading prüfen).");
			}

			$reflection = new ReflectionClass($class);
			if ($reflection->isAbstract()) {
				continue;
			}

			$allowed = self::ALLOWED_WITHOUT_GATE[$shortName] ?? [];

			foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
				// Nur eigene, nicht geerbte Methoden (Konstruktor u. Ä. ausgenommen).
				if ($method->getDeclaringClass()->getName() !== $class) {
					continue;
				}
				if ($method->isConstructor()) {
					continue;
				}

				$checked++;

				if (in_array($method->getName(), $allowed, true)) {
					continue;
				}

				// (b) Kein #[NoAdminRequired] -> Nextcloud verlangt bereits
				// Instanz-Admin, ohne dass App-Code das prüfen muss.
				if ($method->getAttributes('OCP\\AppFramework\\Http\\Attribute\\NoAdminRequired') === []) {
					continue;
				}

				self::assertTrue(
					$this->hasGateCall($reflection, $method),
					"{$class}::{$method->getName()}() ist #[NoAdminRequired] (jeder eingeloggte User "
					. "erreicht die Methode), hat aber keinen erkennbaren ERP-Rechte-Check "
					. "(requireLevel/requireUser/atLeast/getEffectivePermission), auch nicht über einen "
					. "private aufgerufenen Helper. Falls das bewusst so ist, in "
					. "ControllerRightsGateTest::ALLOWED_WITHOUT_GATE eintragen und begründen."
				);
			}
		}

		// Regressionsschutz gegen ein leeres/falsch konfiguriertes Glob-Muster,
		// das den Test unbemerkt zu einem No-Op machen würde.
		self::assertGreaterThan(50, $checked, 'Verdächtig wenige Controller-Methoden geprüft — Testaufbau prüfen.');
	}

	/**
	 * Prüft den Methodenquelltext auf einen Rechte-Check — und, falls dort
	 * keiner direkt steht, rekursiv auch die Quelltexte aller `$this->xyz(`
	 * -Aufrufe von Helper-Methoden derselben Klasse (Muster: eigene private
	 * `requireXyz()`-Methoden wie in ContactsController/OrderController/...).
	 *
	 * @param list<string> $visited bereits besuchte Methodennamen (Zyklenschutz)
	 */
	private function hasGateCall(ReflectionClass $class, ReflectionMethod $method, array $visited = []): bool {
		$source = $this->methodSource($method);
		if (preg_match(self::GATE_CALL_PATTERN, $source) === 1) {
			return true;
		}

		$visited[] = $method->getName();

		if (preg_match_all('/\$this->([a-zA-Z_][a-zA-Z0-9_]*)\(/', $source, $matches) > 0) {
			foreach (array_unique($matches[1]) as $calledName) {
				if (in_array($calledName, $visited, true)) {
					continue; // Zyklenschutz
				}
				if (!$class->hasMethod($calledName)) {
					continue;
				}
				$calledMethod = $class->getMethod($calledName);
				if ($this->hasGateCall($class, $calledMethod, $visited)) {
					return true;
				}
			}
		}

		return false;
	}

	private function methodSource(ReflectionMethod $method): string {
		$filename = $method->getFileName();
		self::assertNotFalse($filename);
		$lines = file($filename);
		self::assertNotFalse($lines);
		$start = $method->getStartLine() - 1;
		$length = $method->getEndLine() - $start;
		return implode('', array_slice($lines, $start, $length));
	}
}
