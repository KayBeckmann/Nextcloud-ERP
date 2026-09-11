<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

/** Validates raw logo bytes server-side; client MIME names are never trusted. */
final class CompanyLogoValidator {
	public const MAX_BYTES = 2_000_000;

	/** @return 'png'|'jpeg' */
	public function validate(string $bytes): string {
		if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
			throw new \InvalidArgumentException('Logo must be a PNG or JPEG no larger than 2 MB');
		}
		$info = @getimagesizefromstring($bytes);
		if ($info === false || !isset($info[2], $info[0], $info[1]) || $info[0] < 1 || $info[1] < 1) {
			throw new \InvalidArgumentException('Logo content is not a valid image');
		}
		return match ($info[2]) {
			IMAGETYPE_PNG => 'png',
			IMAGETYPE_JPEG => 'jpeg',
			default => throw new \InvalidArgumentException('Logo must be PNG or JPEG'),
		};
	}
}
