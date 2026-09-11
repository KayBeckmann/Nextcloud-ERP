<?php

declare(strict_types=1);
namespace OCA\ERP\Tests\Unit\Service;
use OCA\ERP\Service\DocumentTemplateRenderer;
use Test\TestCase;
final class DocumentTemplateRendererTest extends TestCase {
	public function testReplacesOnlyAllowedTokensAndEscapesContent(): void {
		$renderer = new DocumentTemplateRenderer();
		$result = $renderer->render('Hallo {{ customer.name }}, Frist: {{document.validUntil}}', ['customer.name' => '<Muster & Sohn>', 'document.validUntil' => '30.09.2026']);
		self::assertSame('Hallo &lt;Muster &amp; Sohn&gt;, Frist: 30.09.2026', $result);
	}
	public function testRejectsUnknownTokens(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new DocumentTemplateRenderer())->validate('{{ dangerous.html }}');
	}
	public function testDoesNotInterpretMarkupInTextOrTokenValues(): void {
		$result = (new DocumentTemplateRenderer())->render('<script>x</script> {{company.name}}', ['company.name' => '<b>Firma</b>']);
		self::assertSame('&lt;script&gt;x&lt;/script&gt; &lt;b&gt;Firma&lt;/b&gt;', $result);
	}
}
