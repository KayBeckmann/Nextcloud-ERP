<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\DocumentLayout;
use OCA\ERP\Db\DocumentLayoutMapper;

/** Safe per-document text slots. Templates are rendered exclusively by DocumentTemplateRenderer. */
class DocumentLayoutService {
	/** @var list<string> */
	private const DOCUMENT_TYPES = ['quote', 'order', 'delivery_note', 'invoice', 'credit_note', 'purchase_order'];
	/** @var list<string> */
	private const TEXT_FIELDS = ['subject', 'headerText', 'introText', 'closingText', 'footerText', 'paymentNote', 'deliveryNote', 'numberPrefix'];
	/** @var list<string> */
	private const BOOLEAN_FIELDS = ['showUnitPrice', 'showDiscount', 'showVat'];

	public function __construct(
		private DocumentLayoutMapper $mapper,
		private DocumentTemplateRenderer $renderer,
	) {
	}

	/** @return list<DocumentLayout> */
	public function listAll(): array {
		return $this->mapper->findAllLayouts();
	}

	public function get(string $documentType): ?DocumentLayout {
		$this->requireDocumentType($documentType);
		return $this->mapper->findByType($documentType);
	}

	/**
	 * @param array<string,mixed> $values
	 * @throws \InvalidArgumentException
	 */
	public function update(string $documentType, array $values): DocumentLayout {
		$this->requireDocumentType($documentType);
		foreach ($values as $field => $value) {
			if (!in_array($field, self::TEXT_FIELDS, true) && !in_array($field, self::BOOLEAN_FIELDS, true)) {
				throw new \InvalidArgumentException("Unsupported document layout field $field");
			}
			if (in_array($field, self::TEXT_FIELDS, true) && $value !== null && !is_string($value)) {
				throw new \InvalidArgumentException("Document layout field $field must be text");
			}
			if (in_array($field, self::BOOLEAN_FIELDS, true) && !is_bool($value)) {
				throw new \InvalidArgumentException("Document layout field $field must be boolean");
			}
		}

		$layout = $this->mapper->findByType($documentType) ?? new DocumentLayout();
		$layout->setDocumentType($documentType);
		foreach (self::TEXT_FIELDS as $field) {
			if (!array_key_exists($field, $values)) {
				continue;
			}
			$value = $this->nullIfBlank($values[$field]);
			$this->renderer->validate($value);
			$layout->{'set' . ucfirst($field)}($value);
		}
		foreach (self::BOOLEAN_FIELDS as $field) {
			if (array_key_exists($field, $values)) {
				$layout->{'set' . ucfirst($field)}($values[$field]);
			}
		}
		$layout->setUpdatedAt(time());
		return $layout->getId() === null ? $this->mapper->insert($layout) : $this->mapper->update($layout);
	}

	/** @throws \InvalidArgumentException */
	private function requireDocumentType(string $documentType): void {
		if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
			throw new \InvalidArgumentException("Unsupported document type $documentType");
		}
	}

	private function nullIfBlank(mixed $value): ?string {
		if ($value === null || trim($value) === '') {
			return null;
		}
		return $value;
	}
}
