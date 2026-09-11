<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Db;

use OCA\ERP\Db\MeasurementBlock;
use Test\TestCase;

/**
 * Regression: QBMapper inserts only Entity fields marked as updated. A text
 * block must therefore mark blockType as updated when created.
 */
class MeasurementBlockTest extends TestCase {
	public function testTextBlockTypeIsMarkedForInsert(): void {
		$block = new MeasurementBlock();
		$block->setBlockType('text');

		self::assertArrayHasKey('blockType', $block->getUpdatedFields());
	}
}
