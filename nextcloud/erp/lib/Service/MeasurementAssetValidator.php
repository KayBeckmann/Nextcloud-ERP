<?php

declare(strict_types=1);
namespace OCA\ERP\Service;
/** Content signature validation; 10 MiB cap prevents JSON/base64 upload abuse. */
class MeasurementAssetValidator {
	public const MAX_BYTES = 10485760;
	public function validate(string $bytes): string { if ($bytes==='' || strlen($bytes)>self::MAX_BYTES) throw new \InvalidArgumentException('asset is empty or exceeds 10 MiB'); if (str_starts_with($bytes,"\x89PNG\r\n\x1a\n") && @getimagesizefromstring($bytes)!==false) return 'png'; if (str_starts_with($bytes,"\xff\xd8\xff") && @getimagesizefromstring($bytes)!==false) return 'jpeg'; if (str_starts_with($bytes,'%PDF-') && str_contains($bytes,'%%EOF')) return 'pdf'; throw new \InvalidArgumentException('asset must be a structurally valid PNG, JPEG, or PDF'); }
}
