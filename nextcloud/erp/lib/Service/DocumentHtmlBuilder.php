<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

/**
 * Gemeinsame HTML-Bausteine für die Beleg-PDFs aller fünf Belegtypen
 * (ADR-0022) — Firmenkopf, Kundenblock, gruppierte Positionstabelle,
 * Summenblock, Fußzeile. Bewusst getrennt von DocumentPdfService (bleibt
 * domänenneutral, reines HTML→PDF, ADR-0021) und von den einzelnen
 * *Service::renderHtml()-Methoden (die bleiben für Reihenfolge/Überschrift
 * je Belegtyp zuständig, rufen aber dieselben Bausteine auf statt fünfmal
 * dieselbe Kopf-/Kunden-/Gruppenlogik zu duplizieren).
 */
class DocumentHtmlBuilder {
	public function __construct(
		private CompanyProfileService $companyProfileService,
		private ContactsService $contactsService,
	) {
	}

	public function header(
		string $documentTypeLabel,
		string $documentNumber,
		string $title,
		int $createdAt,
		?int $validUntil,
		?string $customerContactUid,
	): string {
		$profile = $this->companyProfileService->get();
		$companyLines = array_values(array_filter([
			$profile->getName(),
			$profile->getAddressLine(),
			trim(($profile->getPostalCode() ?? '') . ' ' . ($profile->getCity() ?? '')),
			$profile->getTaxId() !== null && trim($profile->getTaxId()) !== '' ? 'USt-IdNr./Steuernr.: ' . $profile->getTaxId() : null,
			$profile->getEmail(),
			$profile->getPhone(),
		], static fn (?string $line): bool => $line !== null && trim($line) !== ''));

		$customerLines = [];
		if ($customerContactUid !== null) {
			$details = $this->contactsService->detailsFor($customerContactUid);
			$customerLines[] = $details['displayName'];
			foreach ($details['addressLines'] as $line) {
				$customerLines[] = $line;
			}
		}

		$html = '<table class="doc-header-table" width="100%"><tr>';
		$html .= '<td class="company-block">' . implode('<br>', array_map('htmlspecialchars', $companyLines)) . '</td>';
		$html .= '<td class="customer-block">' . implode('<br>', array_map('htmlspecialchars', $customerLines)) . '</td>';
		$html .= '</tr></table>';

		$html .= '<h1>' . htmlspecialchars($documentTypeLabel . ' ' . $documentNumber) . '</h1>';
		$html .= '<p>' . htmlspecialchars($title) . '</p>';
		$html .= '<p>Datum: ' . htmlspecialchars(date('d.m.Y', $createdAt));
		if ($validUntil !== null) {
			$html .= ' &nbsp;·&nbsp; Bindefrist bis: ' . htmlspecialchars(date('d.m.Y', $validUntil));
		}
		$html .= '</p>';

		return $html;
	}

	/**
	 * @param list<array{id: int, title: ?string}> $groups
	 * @param list<array<string, mixed>> $positions jsonSerialize()-Form der jeweiligen *Position-Entity
	 */
	public function positionsTable(array $groups, array $positions, bool $showPrices): string {
		$groupTitles = [];
		foreach ($groups as $g) {
			$groupTitles[$g['id']] = $g['title'];
		}
		// 0 = ungruppierte Positionen — echte Gruppen-IDs sind immer > 0
		// (dasselbe Konzept wie QuoteCalculationService::calculate()).
		$byGroup = [];
		foreach ($positions as $p) {
			$key = $p['groupId'] ?? 0;
			$byGroup[$key][] = $p;
		}

		$html = '';
		foreach ($byGroup as $groupId => $groupPositions) {
			$groupTitle = $groupId === 0 ? null : ($groupTitles[$groupId] ?? null);
			if ($groupTitle !== null) {
				$html .= '<h3>' . htmlspecialchars($groupTitle) . '</h3>';
			}
			$html .= '<table border="1" cellspacing="0" cellpadding="4" width="100%"><thead><tr>';
			$html .= '<th>Beschreibung</th><th>Menge</th>';
			if ($showPrices) {
				$html .= '<th>EP netto</th><th>Rabatt</th><th>MwSt.</th><th>Gesamt netto</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ($groupPositions as $p) {
				$html .= '<tr><td>' . htmlspecialchars((string) $p['description']) . '</td>';
				$html .= '<td>' . htmlspecialchars((string) $p['quantity']) . ' ' . htmlspecialchars((string) $p['unit']) . '</td>';
				if ($showPrices) {
					$discount = (float) ($p['discountPercent'] ?? 0.0);
					$html .= '<td>' . htmlspecialchars(number_format((float) $p['unitPriceNet'], 2, ',', '.')) . ' €</td>';
					$html .= '<td>' . ($discount > 0.0 ? htmlspecialchars(number_format($discount, 2, ',', '.')) . ' %' : '—') . '</td>';
					$html .= '<td>' . htmlspecialchars((string) $p['vatRatePercent']) . ' %</td>';
					$html .= '<td>' . htmlspecialchars(number_format((float) $p['netTotal'], 2, ',', '.')) . ' €</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		}
		return $html;
	}

	/**
	 * @param array{netSubtotalBeforeDiscount: float, documentDiscountAmount: float, netSubtotal: float, vatBreakdown: list<array{ratePercent: float, netBase: float, vatAmount: float}>, grossTotal: float} $calc
	 */
	public function summary(array $calc): string {
		$html = '<div class="summary"><h3>Abschlussblock</h3>';
		if ($calc['documentDiscountAmount'] > 0.0) {
			$html .= '<p>Zwischensumme netto: ' . htmlspecialchars(number_format($calc['netSubtotalBeforeDiscount'], 2, ',', '.')) . ' €<br>';
			$html .= 'Rabatt: -' . htmlspecialchars(number_format($calc['documentDiscountAmount'], 2, ',', '.')) . ' €</p>';
		}
		$html .= '<p>Netto-Zwischensumme: ' . htmlspecialchars(number_format($calc['netSubtotal'], 2, ',', '.')) . ' €</p>';
		foreach ($calc['vatBreakdown'] as $v) {
			$html .= '<p>+ MwSt. ' . htmlspecialchars(number_format($v['ratePercent'], 2, ',', '.'))
				. '% auf ' . htmlspecialchars(number_format($v['netBase'], 2, ',', '.'))
				. ' €: ' . htmlspecialchars(number_format($v['vatAmount'], 2, ',', '.')) . ' €</p>';
		}
		$html .= '<p><strong>Brutto-Gesamt: ' . htmlspecialchars(number_format($calc['grossTotal'], 2, ',', '.')) . ' €</strong></p>';
		$html .= '</div>';
		return $html;
	}

	/** Freitext-Fußzeile aus dem Firmenprofil (Bankverbindung, Handelsregister, …) — leer, solange nicht gepflegt. */
	public function footer(): string {
		$footerText = $this->companyProfileService->get()->getFooterText();
		if ($footerText === null || trim($footerText) === '') {
			return '';
		}
		return '<div class="footer"><hr>' . nl2br(htmlspecialchars($footerText)) . '</div>';
	}

	public function wrap(string $title, string $bodyHtml): string {
		return sprintf(
			"<!DOCTYPE html>\n<html lang=\"de\"><head><meta charset=\"utf-8\"><title>%s</title>"
			. "<style>.doc-header-table td { vertical-align: top; } .customer-block { text-align: right; } "
			. "table { margin-bottom: 12px; } .footer { margin-top: 24px; font-size: 11px; color: #555; }</style>"
			. "</head><body>\n%s\n</body></html>\n",
			htmlspecialchars($title),
			$bodyHtml,
		);
	}
}
