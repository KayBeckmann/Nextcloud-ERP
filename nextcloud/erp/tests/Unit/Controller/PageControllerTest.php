<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Controller\PageController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use Test\TestCase;

class PageControllerTest extends TestCase {
	public function testIndexRendersSpaShellTemplate(): void {
		$request = $this->createMock(IRequest::class);
		$controller = new PageController('erp', $request);

		$response = $controller->index();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame('index', $response->getTemplateName());
	}
}
