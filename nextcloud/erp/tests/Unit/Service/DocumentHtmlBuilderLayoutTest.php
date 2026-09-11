<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\CompanyProfile;
use OCA\ERP\Db\DocumentLayout;
use OCA\ERP\Service\CompanyProfileService;
use OCA\ERP\Service\ContactsService;
use OCA\ERP\Service\DocumentHtmlBuilder;
use OCA\ERP\Service\DocumentLayoutService;
use OCA\ERP\Service\DocumentTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class DocumentHtmlBuilderLayoutTest extends TestCase {
	public function testHeaderRendersConfiguredTextSlotsWithOnlyAllowlistedEscapedValues(): void {
		$profile = new CompanyProfile();
		$profile->setName('Acme <GmbH>');
		$profile->setHeaderText('Absender: {{company.name}}');
		$profile->setFooterText('Global {{company.email}}');
		$profile->setEmail('office@example.test');
		$companyProfiles = $this->createMock(CompanyProfileService::class);
		$companyProfiles->method('get')->willReturn($profile);
		$contacts = $this->createMock(ContactsService::class);
		$contacts->method('detailsFor')->willReturn(['displayName' => '<Muster>', 'addressLines' => ['Straße 1']]);
		$layout = new DocumentLayout();
		$layout->setDocumentType('quote');
		$layout->setSubject('Angebot {{document.number}}');
		$layout->setIntroText('Hallo {{customer.name}}');
		$layout->setFooterText('Layout {{document.validUntil}}');
		$layouts = $this->createMock(DocumentLayoutService::class);
		$layouts->method('get')->with('quote')->willReturn($layout);

		$builder = new DocumentHtmlBuilder($companyProfiles, $contacts, $layouts, new DocumentTemplateRenderer());
		$html = $builder->header('Angebot', 'A-1', 'Ignored title', 1780000000, 1781000000, 'customer-1', 'quote');
		$html .= $builder->footer('quote', 'A-1', 'Ignored title', 1780000000, 1781000000, 'customer-1');

		self::assertStringContainsString('Angebot A-1', $html);
		self::assertStringContainsString('Absender: Acme &lt;GmbH&gt;', $html);
		self::assertStringContainsString('Hallo &lt;Muster&gt;', $html);
		self::assertStringContainsString('Layout 09.06.2026', $html);
		self::assertStringContainsString('Global office@example.test', $html);
	}

	public function testRenderingWithIssuedSnapshotDoesNotUseChangedCompanyOrLayout(): void {
		$issuedProfile = new CompanyProfile();
		$issuedProfile->setName('Issued Company');
		$issuedProfile->setHeaderText('Issued header {{company.name}}');
		$issuedProfile->setFooterText('Issued footer');
		$changedProfile = new CompanyProfile();
		$changedProfile->setName('Changed Company');
		$changedProfile->setHeaderText('Changed header');
		$changedProfile->setFooterText('Changed footer');
		$companyProfiles = $this->createMock(CompanyProfileService::class);
		$companyProfiles->method('get')->willReturnOnConsecutiveCalls($issuedProfile, $changedProfile);

		$issuedLayout = new DocumentLayout();
		$issuedLayout->setDocumentType('quote');
		$issuedLayout->setSubject('Issued subject');
		$issuedLayout->setIntroText('Issued intro');
		$issuedLayout->setFooterText('Issued layout footer');
		$issuedLayout->setShowUnitPrice(false);
		$changedLayout = new DocumentLayout();
		$changedLayout->setDocumentType('quote');
		$changedLayout->setSubject('Changed subject');
		$changedLayout->setIntroText('Changed intro');
		$changedLayout->setFooterText('Changed layout footer');
		$changedLayout->setShowUnitPrice(true);
		$layouts = $this->createMock(DocumentLayoutService::class);
		$layouts->method('get')->willReturnOnConsecutiveCalls($issuedLayout, $changedLayout);
		$contacts = $this->createMock(ContactsService::class);
		$contacts->method('detailsFor')->willReturn(['displayName' => 'Customer', 'addressLines' => []]);
		$builder = new DocumentHtmlBuilder($companyProfiles, $contacts, $layouts, new DocumentTemplateRenderer());

		$snapshot = $builder->snapshot('quote', 'A-1', 'Draft title', 1780000000, null, 'customer-1');
		$html = $builder->header('Angebot', 'A-1', 'Draft title', 1780000000, null, 'customer-1', 'quote', null, $snapshot);
		$html .= $builder->positionsTable([], [['description' => 'Position', 'quantity' => 1, 'unit' => 'Stk', 'unitPriceNet' => 10, 'discountPercent' => 0, 'vatRatePercent' => 19, 'netTotal' => 10]], true, 'quote', $snapshot);
		$html .= $builder->footer('quote', 'A-1', 'Draft title', 1780000000, null, 'customer-1', null, $snapshot);

		self::assertStringContainsString('Issued Company', $html);
		self::assertStringContainsString('Issued header Issued Company', $html);
		self::assertStringContainsString('Issued subject', $html);
		self::assertStringContainsString('Issued intro', $html);
		self::assertStringContainsString('Issued layout footer', $html);
		self::assertStringNotContainsString('Changed Company', $html);
		self::assertStringNotContainsString('Changed subject', $html);
		self::assertStringNotContainsString('EP netto', $html);
	}
}
