<?php

declare(strict_types=1);
namespace OCA\ERP\Tests\Unit\Service;
use OCA\ERP\Service\MeasurementAssetValidator;
use PHPUnit\Framework\TestCase;
final class MeasurementAssetValidatorTest extends TestCase {
	public function testAcceptsPngJpegAndPdfOnly(): void { $v=new MeasurementAssetValidator(); self::assertSame('png',$v->validate(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl4Yq8AAAAASUVORK5CYII=',true))); self::assertSame('pdf',$v->validate("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")); }
	public function testRejectsSpoofedAndOversizedAssetBytes(): void { $this->expectException(\InvalidArgumentException::class); (new MeasurementAssetValidator())->validate("\x89PNG\r\n\x1a\nnot-image"); }
}
