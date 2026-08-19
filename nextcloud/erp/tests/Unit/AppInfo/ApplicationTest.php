<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\AppInfo;

use OCA\ERP\AppInfo\Application;
use Test\TestCase;

class ApplicationTest extends TestCase {
	public function testAppId(): void {
		$this->assertSame('erp', Application::APP_ID);
	}

	public function testCanBeInstantiated(): void {
		$app = new Application();
		$this->assertInstanceOf(Application::class, $app);
	}
}
