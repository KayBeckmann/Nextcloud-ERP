<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Controller;

use OCA\ERP\Controller\DocumentsController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DocumentsControllerTest extends TestCase {
	private IRootFolder&MockObject $rootFolder;
	private IUserSession&MockObject $userSession;
	private DocumentsController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->controller = new DocumentsController(
			'erp',
			$this->createMock(IRequest::class),
			$this->rootFolder,
			$this->userSession,
		);
	}

	public function testShowRejectsWithoutActiveSession(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->show(1);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testShowReturnsFileInlineWhenNodeIsVisibleToUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('phpunit-user');
		$this->userSession->method('getUser')->willReturn($user);

		$file = $this->createMock(File::class);
		$file->method('getMimeType')->willReturn('application/pdf');
		$file->method('getName')->willReturn('A-00001_2026-01-01T00-00.pdf');
		$file->method('getEtag')->willReturn('etag');
		$file->method('getMTime')->willReturn(time());

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->with(42)->willReturn([$file]);
		$this->rootFolder->method('getUserFolder')->with('phpunit-user')->willReturn($userFolder);

		$response = $this->controller->show(42);

		$this->assertInstanceOf(FileDisplayResponse::class, $response);
	}

	/** Ein fremder/unbekannter Dateiindex darf nicht als 500er durchschlagen. */
	public function testShowReturnsNotFoundForUnknownFileId(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('phpunit-user');
		$this->userSession->method('getUser')->willReturn($user);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->willReturn([]);
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);

		$response = $this->controller->show(999);

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
