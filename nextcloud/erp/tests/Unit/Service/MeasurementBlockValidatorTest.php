<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Service\MeasurementBlockValidator;
use PHPUnit\Framework\TestCase;

final class MeasurementBlockValidatorTest extends TestCase {
	public function testAcceptsBoundedPayloadsForEverySupportedBlockType(): void {
		$validator = new MeasurementBlockValidator();
		self::assertSame(['text' => 'Wall needs repainting'], $validator->validate('text', ['text' => 'Wall needs repainting']));
		self::assertSame(['quantity' => 12.5, 'unit' => 'm', 'label' => 'Wall'], $validator->validate('measurement', ['quantity' => 12.5, 'unit' => 'm', 'label' => 'Wall']));
		self::assertSame(['assetFileId' => 42, 'mimeType' => 'image/png'], $validator->validate('image', ['assetFileId' => 42, 'mimeType' => 'image/png']));
		self::assertSame(['assetFileId' => 43, 'mimeType' => 'application/pdf'], $validator->validate('plan', ['assetFileId' => 43, 'mimeType' => 'application/pdf']));
		self::assertSame(['strokes' => [[[1.0, 2.0], [3.0, 4.0]]]], $validator->validate('drawing', ['strokes' => [[[1.0, 2.0], [3.0, 4.0]]]]));
	}

	/** @dataProvider invalidPayloads */
	public function testRejectsUnknownFieldsAndUnsafeOrUnboundedPayloads(string $type, array $payload): void {
		$this->expectException(\InvalidArgumentException::class);
		(new MeasurementBlockValidator())->validate($type, $payload);
	}
	/** @return iterable<string, array{string,array<string,mixed>}> */
	public static function invalidPayloads(): iterable {
		yield 'unknown type' => ['url', ['url' => 'https://example.test/x']];
		yield 'unknown text field' => ['text', ['text' => 'x', 'url' => 'https://example.test/x']];
		yield 'oversized text' => ['text', ['text' => str_repeat('x', 10001)]];
		yield 'zero quantity' => ['measurement', ['quantity' => 0, 'unit' => 'm']];
		yield 'invalid unit' => ['measurement', ['quantity' => 1, 'unit' => 'https://example.test']];
		yield 'external asset URL' => ['image', ['assetFileId' => 1, 'mimeType' => 'image/png', 'url' => 'https://example.test/x']];
		yield 'too many vector points' => ['drawing', ['strokes' => [array_fill(0, 2001, [1, 2])]]];
	}
}
