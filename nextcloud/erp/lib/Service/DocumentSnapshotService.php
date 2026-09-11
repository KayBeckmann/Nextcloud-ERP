<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\CompanyProfile;
use OCA\ERP\Db\DocumentLayout;

/** Builds the durable, plain-data rendering context captured at issuance. */
final class DocumentSnapshotService {
	/** @param array<string,string> $tokens @return string canonical JSON */
	public function encode(?CompanyProfile $company, ?DocumentLayout $layout, array $tokens): string {
		$data = [
			'format' => 1,
			'company' => $company?->jsonSerialize() ?? [],
			'layout' => $layout?->jsonSerialize() ?? [],
			'tokens' => $tokens,
		];
		return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	/** @return array{format:int,company:array<string,mixed>,layout:array<string,mixed>,tokens:array<string,string>} */
	public function decode(string $snapshot): array {
		$data = json_decode($snapshot, true, 16, JSON_THROW_ON_ERROR);
		if (!is_array($data) || ($data['format'] ?? null) !== 1 || !is_array($data['company'] ?? null) || !is_array($data['layout'] ?? null) || !is_array($data['tokens'] ?? null)) {
			throw new \InvalidArgumentException('Invalid document rendering snapshot');
		}
		return $data;
	}
}
