<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Service\CompanyLogoValidator;
use PHPUnit\Framework\TestCase;

final class CompanyLogoValidatorTest extends TestCase {
	public function testAcceptsOnlyStructurallyValidPngOrJpegWithinCap(): void {
		$validator = new CompanyLogoValidator();
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl4Yq8AAAAASUVORK5CYII=', true);
		self::assertIsString($png);

		self::assertSame('png', $validator->validate($png));
		self::assertSame('jpeg', $validator->validate(base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/Aaf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/Aaf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Ar//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IX//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z', true)));
	}

	public function testRejectsSpoofedOrOversizedImageBytes(): void {
		$validator = new CompanyLogoValidator();
		$this->expectException(\InvalidArgumentException::class);
		$validator->validate("\x89PNG\r\n\x1a\nnot an image");
	}
}
