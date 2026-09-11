<?php

declare(strict_types=1);
namespace OCA\ERP\Service;

/** Server-side allow-list and conservative payload limits for syncable blocks. */
class MeasurementBlockValidator {
	private const TYPES = ['text', 'measurement', 'image', 'plan', 'drawing'];
	/** @param array<string,mixed> $payload @return array<string,mixed> */
	public function validate(string $type, array $payload): array {
		if (!in_array($type, self::TYPES, true)) throw new \InvalidArgumentException('Unsupported measurement block type');
		return match ($type) {
			'text' => $this->text($payload), 'measurement' => $this->measurement($payload),
			'image' => $this->asset($payload, ['image/png', 'image/jpeg']), 'plan' => $this->asset($payload, ['image/png', 'image/jpeg', 'application/pdf']),
			'drawing' => $this->drawing($payload),
		};
	}
	/** @param array<string,mixed> $payload @return array{text:string} */
	private function text(array $payload): array { $this->only($payload, ['text']); if (!isset($payload['text']) || !is_string($payload['text']) || mb_strlen($payload['text']) > 10000) throw new \InvalidArgumentException('text must be a string of at most 10000 characters'); return ['text'=>$payload['text']]; }
	/** @param array<string,mixed> $payload @return array{quantity:float,unit:string,label?:string} */
	private function measurement(array $payload): array { $this->only($payload, ['quantity','unit','label']); if (!isset($payload['quantity']) || !is_numeric($payload['quantity']) || (float)$payload['quantity'] <= 0 || (float)$payload['quantity'] > 1000000000) throw new \InvalidArgumentException('quantity must be positive and bounded'); if (!isset($payload['unit']) || !is_string($payload['unit']) || !preg_match('/^[A-Za-zµ²³]{1,16}$/u',$payload['unit'])) throw new \InvalidArgumentException('unit is invalid'); $out=['quantity'=>(float)$payload['quantity'],'unit'=>$payload['unit']]; if (isset($payload['label'])) { if (!is_string($payload['label']) || mb_strlen($payload['label'])>255) throw new \InvalidArgumentException('label is invalid'); $out['label']=$payload['label']; } return $out; }
	/** @param array<string,mixed> $payload @param list<string> $mimes @return array{assetFileId:int,mimeType:string} */
	private function asset(array $payload, array $mimes): array { $this->only($payload, ['assetFileId','mimeType']); if (!isset($payload['assetFileId']) || filter_var($payload['assetFileId'], FILTER_VALIDATE_INT) === false || (int)$payload['assetFileId'] <= 0) throw new \InvalidArgumentException('assetFileId must be a positive file reference'); if (!isset($payload['mimeType']) || !is_string($payload['mimeType']) || !in_array($payload['mimeType'],$mimes,true)) throw new \InvalidArgumentException('asset mime type is invalid'); return ['assetFileId'=>(int)$payload['assetFileId'],'mimeType'=>$payload['mimeType']]; }
	/** @param array<string,mixed> $payload @return array{strokes:list<list<list<float>>>} */
	private function drawing(array $payload): array { $this->only($payload,['strokes']); if (!isset($payload['strokes']) || !is_array($payload['strokes']) || count($payload['strokes']) > 100) throw new \InvalidArgumentException('drawing strokes are invalid'); $points=0; foreach ($payload['strokes'] as $stroke) { if (!is_array($stroke) || count($stroke) > 2000) throw new \InvalidArgumentException('drawing stroke is invalid'); foreach ($stroke as $point) { if (!is_array($point) || count($point)!==2 || !is_numeric($point[0]??null) || !is_numeric($point[1]??null) || abs((float)$point[0])>100000 || abs((float)$point[1])>100000) throw new \InvalidArgumentException('drawing point is invalid'); if (++$points > 10000) throw new \InvalidArgumentException('drawing has too many points'); } } return ['strokes'=>$payload['strokes']]; }
	/** @param array<string,mixed> $payload @param list<string> $allowed */
	private function only(array $payload, array $allowed): void { if (array_diff(array_keys($payload),$allowed)!==[]) throw new \InvalidArgumentException('Unknown measurement block payload field'); }
}
