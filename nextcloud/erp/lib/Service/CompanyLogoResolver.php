<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCP\Files\File;
use OCP\Files\IRootFolder;

/** Resolves only canonical ERP logo files to a local data URI; never a URL. */
final class CompanyLogoResolver {
	public function __construct(private IRootFolder $rootFolder, private CompanyLogoValidator $validator) {
	}

	public function dataUri(?int $fileId): ?string {
		if ($fileId === null) return null;
		foreach ($this->rootFolder->getById($fileId) as $node) {
			if (!$node instanceof File || !preg_match('#/ERP-Firma/ERP/Vorlagen/Logos/company-logo-[a-f0-9]{32}\.(png|jpeg)$#', $node->getPath(), $match)) continue;
			$bytes = $node->getContent();
			$extension = $this->validator->validate($bytes);
			return 'data:image/' . $extension . ';base64,' . base64_encode($bytes);
		}
		return null;
	}
}
