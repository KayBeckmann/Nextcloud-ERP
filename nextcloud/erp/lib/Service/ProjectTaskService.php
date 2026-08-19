<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\ProjectTask;
use OCA\ERP\Db\ProjectTaskMapper;

/** Aufgaben-Checkliste pro Projekt (ADR-0010) — bewusst flach. */
class ProjectTaskService {
	public function __construct(
		private ProjectTaskMapper $mapper,
	) {
	}

	/** @return ProjectTask[] */
	public function listTasks(int $projectId): array {
		return $this->mapper->findByProject($projectId);
	}

	public function createTask(int $projectId, string $title): ProjectTask {
		$now = time();
		$task = new ProjectTask();
		$task->setProjectId($projectId);
		$task->setTitle($title);
		$task->setDone(false);
		$task->setPosition(count($this->mapper->findByProject($projectId)));
		$task->setCreatedAt($now);
		$task->setUpdatedAt($now);
		return $this->mapper->insert($task);
	}

	/** @throws \OutOfBoundsException */
	public function updateTask(int $projectId, int $id, string $title, bool $done): ProjectTask {
		$task = $this->mapper->findOne($projectId, $id);
		if ($task === null) {
			throw new \OutOfBoundsException("Task $id not found in project $projectId");
		}
		$task->setTitle($title);
		$task->setDone($done);
		$task->setUpdatedAt(time());
		return $this->mapper->update($task);
	}

	/** @throws \OutOfBoundsException */
	public function deleteTask(int $projectId, int $id): void {
		$task = $this->mapper->findOne($projectId, $id);
		if ($task === null) {
			throw new \OutOfBoundsException("Task $id not found in project $projectId");
		}
		$this->mapper->delete($task);
	}
}
