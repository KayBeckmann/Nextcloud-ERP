<?php

declare(strict_types=1);
namespace OCA\ERP\Service;
/** Fixed-token renderer for document text slots. It never renders HTML/CSS. */
class DocumentTemplateRenderer {
	/** @param array<string,string> $tokens */
	public function render(?string $template, array $tokens): string {
		if ($template === null || $template === '') return '';
		preg_match_all('/{{\s*([^}]+?)\s*}}/', $template, $matches);
		foreach ($matches[1] as $token) { if (!array_key_exists($token, $tokens)) throw new \InvalidArgumentException("Unknown document placeholder {{$token}}"); }
		return htmlspecialchars(strtr($template, array_combine($matches[0], array_map(fn(string $key) => $tokens[trim($key)], $matches[1]))), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
	/** @return list<string> */ public function allowedTokens(): array { return ['company.name','company.address','company.email','company.phone','company.vatId','customer.name','customer.address','document.number','document.date','document.subject','document.dueDate','document.validUntil']; }
	public function validate(?string $template): void { if ($template === null || $template === '') return; preg_match_all('/{{\s*([^}]+?)\s*}}/', $template, $matches); foreach ($matches[1] as $token) if (!in_array(trim($token), $this->allowedTokens(), true)) throw new \InvalidArgumentException("Unknown document placeholder {{$token}}"); }
}
