<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Projects\ProjectStatus;
use OCA\ERP\Service\ErpFolderService;
use OCA\ERP\Service\ProjectService;
use OCP\Files\Folder;
use OCP\IDBConnection;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * @group DB
 */
final class ProjectServiceTest extends TestCase {
	private ProjectService $service;
	private ProjectMapper $mapper;
	private IUser $user;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new ProjectMapper($db);

		$folder = $this->createMock(Folder::class);
		$folder->method('getId')->willReturn(4242);

		/** @var ErpFolderService&MockObject $folderService */
		$folderService = $this->createMock(ErpFolderService::class);
		$folderService->method('ensureProjectFolder')->willReturn($folder);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('phpunit-project-user');

		$this->service = new ProjectService($this->mapper, $folderService);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findAll() as $project) {
			if (str_starts_with($project->getTitle(), 'phpunit-')) {
				$this->mapper->delete($project);
			}
		}
		parent::tearDown();
	}

	public function testCreateProjectGeneratesNumberAndFolder(): void {
		$project = $this->service->createProject($this->user, 'phpunit-project-1', 'contact-1', null, 'note');

		$this->assertSame(sprintf('P-%05d', $project->getId()), $project->getProjectNumber());
		$this->assertSame(4242, $project->getFilesFolderId());
		$this->assertSame(ProjectStatus::Draft->value, $project->getStatus());
		$this->assertSame('contact-1', $project->getCustomerContactUid());
	}

	public function testUpdateProjectChangesStatusAndTitle(): void {
		$project = $this->service->createProject($this->user, 'phpunit-project-2', null, null, null);

		$updated = $this->service->updateProject(
			$project->getId(),
			'phpunit-project-2-renamed',
			null,
			null,
			ProjectStatus::InProgress,
			'x',
		);

		$this->assertSame('in_progress', $updated->getStatus());
		$this->assertSame('phpunit-project-2-renamed', $updated->getTitle());
	}

	public function testGetUnknownProjectThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->getProject(999999999);
	}

	public function testUpdateUnknownProjectThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->updateProject(999999999, 'x', null, null, ProjectStatus::Draft, null);
	}

	public function testListProjectsCanFilterByStatus(): void {
		$this->service->createProject($this->user, 'phpunit-project-3', null, null, null);

		$draftOwn = array_values(array_filter(
			$this->service->listProjects(ProjectStatus::Draft),
			static fn ($p) => $p->getTitle() === 'phpunit-project-3',
		));
		$this->assertCount(1, $draftOwn);

		$doneOwn = array_values(array_filter(
			$this->service->listProjects(ProjectStatus::Done),
			static fn ($p) => $p->getTitle() === 'phpunit-project-3',
		));
		$this->assertCount(0, $doneOwn);
	}
}
