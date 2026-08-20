<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\ProjectTaskMapper;
use OCA\ERP\Service\ProjectTaskService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class ProjectTaskServiceTest extends TestCase {
	private const PROJECT_ID = 999999001;

	private ProjectTaskService $service;
	private ProjectTaskMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new ProjectTaskMapper($db);
		$this->service = new ProjectTaskService($this->mapper);
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByProject(self::PROJECT_ID) as $task) {
			$this->mapper->delete($task);
		}
		parent::tearDown();
	}

	public function testCreateAndListTasksAreOrderedByPosition(): void {
		$this->service->createTask(self::PROJECT_ID, 'Erste Aufgabe');
		$this->service->createTask(self::PROJECT_ID, 'Zweite Aufgabe');

		$tasks = $this->service->listTasks(self::PROJECT_ID);
		$this->assertCount(2, $tasks);
		$this->assertSame('Erste Aufgabe', $tasks[0]->getTitle());
		$this->assertSame('Zweite Aufgabe', $tasks[1]->getTitle());
		$this->assertFalse($tasks[0]->getDone());
	}

	public function testUpdateTaskTogglesDone(): void {
		$task = $this->service->createTask(self::PROJECT_ID, 'Erledigen');
		$updated = $this->service->updateTask(self::PROJECT_ID, $task->getId(), 'Erledigen', true);
		$this->assertTrue($updated->getDone());
	}

	public function testDeleteTaskRemovesIt(): void {
		$task = $this->service->createTask(self::PROJECT_ID, 'Löschen');
		$this->service->deleteTask(self::PROJECT_ID, $task->getId());
		$this->assertCount(0, $this->service->listTasks(self::PROJECT_ID));
	}

	public function testUpdateUnknownTaskThrows(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->updateTask(self::PROJECT_ID, 999999999, 'x', true);
	}

	public function testTaskFromOtherProjectIsNotVisible(): void {
		$this->service->createTask(self::PROJECT_ID, 'Eigenes Projekt');
		$this->assertCount(0, $this->service->listTasks(self::PROJECT_ID + 1));
	}
}
