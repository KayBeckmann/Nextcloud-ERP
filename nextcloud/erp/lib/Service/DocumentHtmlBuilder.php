<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

/** Shared, safe HTML blocks for all ERP document PDFs. User text is always escaped by DocumentTemplateRenderer. */
class DocumentHtmlBuilder {
	public function __construct(
		private CompanyProfileService $companyProfileService,
		private ContactsService $contactsService,
		private ?DocumentLayoutService $documentLayoutService = null,
		private ?DocumentTemplateRenderer $templateRenderer = null,
		private ?DocumentSnapshotService $snapshotService = null,
		private ?CompanyLogoResolver $logoResolver = null,
	) {
		$this->templateRenderer ??= new DocumentTemplateRenderer();
		$this->snapshotService ??= new DocumentSnapshotService();
	}

	/** Capture all mutable layout/company/token data before an issued document is written. */
	public function snapshot(string $documentType, string $documentNumber, string $title, int $createdAt, ?int $validUntil, ?string $customerContactUid, ?string $dueDate = null): string {
		$profile = $this->companyProfileService->get();
		$layout = $this->documentLayoutService?->get($documentType);
		return $this->snapshotService->encode($profile, $layout, $this->tokens($profile, $this->customer($customerContactUid), $documentNumber, $createdAt, $title, $dueDate, $validUntil));
	}

	public function header(
		string $documentTypeLabel,
		string $documentNumber,
		string $title,
		int $createdAt,
		?int $validUntil,
		?string $customerContactUid,
		?string $documentType = null,
		?string $dueDate = null,
		?string $snapshot = null,
	): string {
		$context = $this->renderingContext($snapshot);
		$profile = $context['company'] ?? $this->companyProfileService->get();
		$customer = $context === null ? $this->customer($customerContactUid) : $this->customerFromTokens($context['tokens']);
		$layout = $context['layout'] ?? ($documentType === null ? null : $this->documentLayoutService?->get($documentType));
		$tokens = $context['tokens'] ?? $this->tokens($profile, $customer, $documentNumber, $createdAt, $title, $dueDate, $validUntil);
		$companyLines = array_values(array_filter([$this->companyValue($profile, 'name'), $this->companyValue($profile, 'addressLine'), trim($this->companyValue($profile, 'postalCode') . ' ' . $this->companyValue($profile, 'city')), $this->companyValue($profile, 'taxId'), $this->companyValue($profile, 'email'), $this->companyValue($profile, 'phone')], static fn (string $line): bool => trim($line) !== ''));
		$logoFileId = $profile instanceof \OCA\ERP\Db\CompanyProfile ? $profile->getLogoFileId() : (isset($profile['logoFileId']) ? (int) $profile['logoFileId'] : null);
		$logo = $this->logoResolver?->dataUri($logoFileId);
		$html = '<table class="doc-header-table" width="100%"><tr>';
		$html .= '<td class="company-block">' . ($logo === null ? '' : '<img class="company-logo" src="' . htmlspecialchars($logo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt=""><br>') . implode('<br>', array_map(static fn (string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $companyLines)) . '</td>';
		$html .= '<td class="customer-block">' . implode('<br>', array_map(static fn (string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $customer['lines'])) . '</td>';
		$html .= '</tr></table>';
		$html .= $this->textBlock($this->companyValue($profile, 'headerText'), $tokens, 'company-header');
		$html .= $this->textBlock($this->layoutValue($layout, 'headerText'), $tokens, 'document-header');
		$html .= '<h1>' . htmlspecialchars($documentTypeLabel . ' ' . ($tokens['document.number'] ?? $documentNumber), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>';
		$subject = $this->layoutValue($layout, 'subject') ?: ($tokens['document.subject'] ?? $title);
		$html .= $this->textBlock($subject, $tokens, 'subject');
		$html .= '<p>Datum: ' . htmlspecialchars($tokens['document.date'] ?? date('d.m.Y', $createdAt), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		if (($tokens['document.validUntil'] ?? '') !== '') {
			$html .= ' &nbsp;·&nbsp; Bindefrist bis: ' . htmlspecialchars($tokens['document.validUntil'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}
		$html .= '</p>';
		$html .= $this->textBlock($this->layoutValue($layout, 'introText'), $tokens, 'intro');
		return $html;
	}

	/** @param list<array{id:int,title:?string}> $groups @param list<array<string,mixed>> $positions */
	public function positionsTable(array $groups, array $positions, bool $showPrices, ?string $documentType = null, ?string $snapshot = null): string {
		$context = $this->renderingContext($snapshot);
		$layout = $context['layout'] ?? ($documentType === null ? null : $this->documentLayoutService?->get($documentType));
		$showUnitPrice = $showPrices && $this->layoutBool($layout, 'showUnitPrice', true);
		$showDiscount = $showPrices && $this->layoutBool($layout, 'showDiscount', true);
		$showVat = $showPrices && $this->layoutBool($layout, 'showVat', true);
		$groupTitles = [];
		foreach ($groups as $group) $groupTitles[$group['id']] = $group['title'];
		$byGroup = [];
		foreach ($positions as $position) $byGroup[$position['groupId'] ?? 0][] = $position;
		$html = '';
		foreach ($byGroup as $groupId => $groupPositions) {
			if ($groupId !== 0 && ($groupTitles[$groupId] ?? null) !== null) $html .= '<h3>' . htmlspecialchars((string) $groupTitles[$groupId], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>';
			$html .= '<table border="1" cellspacing="0" cellpadding="4" width="100%"><thead><tr><th>Beschreibung</th><th>Menge</th>';
			if ($showUnitPrice) $html .= '<th>EP netto</th>';
			if ($showDiscount) $html .= '<th>Rabatt</th>';
			if ($showVat) $html .= '<th>MwSt.</th>';
			if ($showPrices) $html .= '<th>Gesamt netto</th>';
			$html .= '</tr></thead><tbody>';
			foreach ($groupPositions as $position) {
				$html .= '<tr><td>' . htmlspecialchars((string) $position['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td><td>' . htmlspecialchars((string) $position['quantity'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ' . htmlspecialchars((string) $position['unit'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
				if ($showUnitPrice) $html .= '<td>' . number_format((float) $position['unitPriceNet'], 2, ',', '.') . ' €</td>';
				if ($showDiscount) { $discount = (float) ($position['discountPercent'] ?? 0); $html .= '<td>' . ($discount > 0 ? number_format($discount, 2, ',', '.') . ' %' : '—') . '</td>'; }
				if ($showVat) $html .= '<td>' . htmlspecialchars((string) $position['vatRatePercent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' %</td>';
				if ($showPrices) $html .= '<td>' . number_format((float) $position['netTotal'], 2, ',', '.') . ' €</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		}
		return $html;
	}

	/** @param array{netSubtotalBeforeDiscount:float,documentDiscountAmount:float,netSubtotal:float,vatBreakdown:list<array{ratePercent:float,netBase:float,vatAmount:float}>,grossTotal:float} $calc */
	public function summary(array $calc): string {
		$html = '<div class="summary"><h3>Abschlussblock</h3>';
		if ($calc['documentDiscountAmount'] > 0.0) $html .= '<p>Zwischensumme netto: ' . number_format($calc['netSubtotalBeforeDiscount'], 2, ',', '.') . ' €<br>Rabatt: -' . number_format($calc['documentDiscountAmount'], 2, ',', '.') . ' €</p>';
		$html .= '<p>Netto-Zwischensumme: ' . number_format($calc['netSubtotal'], 2, ',', '.') . ' €</p>';
		foreach ($calc['vatBreakdown'] as $vat) $html .= '<p>+ MwSt. ' . number_format($vat['ratePercent'], 2, ',', '.') . '% auf ' . number_format($vat['netBase'], 2, ',', '.') . ' €: ' . number_format($vat['vatAmount'], 2, ',', '.') . ' €</p>';
		return $html . '<p><strong>Brutto-Gesamt: ' . number_format($calc['grossTotal'], 2, ',', '.') . ' €</strong></p></div>';
	}

	public function footer(?string $documentType = null, string $documentNumber = '', string $title = '', int $createdAt = 0, ?int $validUntil = null, ?string $customerContactUid = null, ?string $dueDate = null, ?string $snapshot = null): string {
		$context = $this->renderingContext($snapshot);
		$profile = $context['company'] ?? $this->companyProfileService->get();
		$layout = $context['layout'] ?? ($documentType === null ? null : $this->documentLayoutService?->get($documentType));
		$tokens = $context['tokens'] ?? $this->tokens($profile, $this->customer($customerContactUid), $documentNumber, $createdAt, $title, $dueDate, $validUntil);
		$html = $this->textBlock($this->layoutValue($layout, 'closingText'), $tokens, 'closing');
		foreach ([$this->layoutValue($layout, 'paymentNote'), $this->layoutValue($layout, 'deliveryNote'), $this->layoutValue($layout, 'footerText'), $this->companyValue($profile, 'footerText')] as $text) $html .= $this->textBlock($text, $tokens, 'footer');
		return $html === '' ? '' : '<div class="footer"><hr>' . $html . '</div>';
	}

	public function wrap(string $title, string $bodyHtml): string {
		return sprintf("<!DOCTYPE html>\n<html lang=\"de\"><head><meta charset=\"utf-8\"><title>%s</title><style>.doc-header-table td { vertical-align: top; } .customer-block { text-align: right; } table { margin-bottom: 12px; } .footer { margin-top: 24px; font-size: 11px; color: #555; } .document-header,.company-header,.intro,.subject { white-space: normal; }</style></head><body>\n%s\n</body></html>\n", htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $bodyHtml);
	}

	/** @return array{format:int,company:array<string,mixed>,layout:array<string,mixed>,tokens:array<string,string>}|null */
	private function renderingContext(?string $snapshot): ?array {
		return $snapshot === null ? null : $this->snapshotService->decode($snapshot);
	}

	/** @param object|array<string,mixed> $company */
	private function companyValue(object|array $company, string $key): string {
		if (is_array($company)) return (string) ($company[$key] ?? '');
		$getter = 'get' . ucfirst($key);
		return (string) ($company->$getter() ?? '');
	}

	/** @param object|array<string,mixed>|null $layout */
	private function layoutValue(object|array|null $layout, string $key): ?string {
		if ($layout === null) return null;
		if (is_array($layout)) return isset($layout[$key]) ? (string) $layout[$key] : null;
		$getter = 'get' . ucfirst($key);
		return $layout->$getter();
	}

	/** @param object|array<string,mixed>|null $layout */
	private function layoutBool(object|array|null $layout, string $key, bool $default): bool {
		if ($layout === null) return $default;
		if (is_array($layout)) return array_key_exists($key, $layout) ? (bool) $layout[$key] : $default;
		$getter = 'get' . ucfirst($key);
		return $layout->$getter();
	}

	/** @param array<string,string> $tokens @return array{name:string,address:string,lines:list<string>} */
	private function customerFromTokens(array $tokens): array {
		$name = $tokens['customer.name'] ?? '';
		$address = $tokens['customer.address'] ?? '';
		return ['name' => $name, 'address' => $address, 'lines' => array_values(array_filter(array_merge([$name], preg_split('/\R/', $address) ?: []), static fn (string $line): bool => $line !== ''))];
	}

	/** @return array{name:string,address:string,lines:list<string>} */
	private function customer(?string $uid): array {
		if ($uid === null) return ['name' => '', 'address' => '', 'lines' => []];
		$details = $this->contactsService->detailsFor($uid);
		$lines = array_merge([(string) $details['displayName']], $details['addressLines']);
		return ['name' => (string) $details['displayName'], 'address' => implode("\n", $details['addressLines']), 'lines' => $lines];
	}

	/** @return array<string,string> */
	private function tokens(object $profile, array $customer, string $number, int $createdAt, string $subject, ?string $dueDate, ?int $validUntil): array {
		return ['company.name'=>(string) $profile->getName(), 'company.address'=>implode("\n", array_filter([$profile->getAddressLine(), trim(($profile->getPostalCode() ?? '') . ' ' . ($profile->getCity() ?? '')), $profile->getCountry()])), 'company.email'=>(string) $profile->getEmail(), 'company.phone'=>(string) $profile->getPhone(), 'company.vatId'=>(string) $profile->getVatId(), 'customer.name'=>$customer['name'], 'customer.address'=>$customer['address'], 'document.number'=>$number, 'document.date'=>date('d.m.Y', $createdAt), 'document.subject'=>$subject, 'document.dueDate'=>(string) $dueDate, 'document.validUntil'=>$validUntil === null ? '' : date('d.m.Y', $validUntil)];
	}

	/** Rendered content has already been escaped; this only preserves newlines. */
	private function textBlock(?string $template, array $tokens, string $class): string {
		if ($template === null || trim($template) === '') return '';
		return '<p class="' . $class . '">' . nl2br($this->templateRenderer->render($template, $tokens)) . '</p>';
	}
}
