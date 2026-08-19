<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Project;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Projects\ProjectStatus;
use OCP\IUser;

/**
 * Projektkern (Roadmap Phase 4, ADR-0010). `update*()`-Methoden ersetzen die
 * angegebenen Felder vollständig (kein Deep-Patch) — der Client schickt immer
 * den aktuellen Feldstand mit, `null` löscht ein optionales Feld bewusst.
 */
class ProjectService {
	public function __construct(
		private ProjectMapper $mapper,
		private ErpFolderService $folderService,
	) {
	}

	/** @return Project[] */
	public function listProjects(?ProjectStatus $status = null): array {
		return $this->mapper->findAll($status?->value);
	}

	/** @throws \OutOfBoundsException */
	public function getProject(int $id): Project {
		$project = $this->mapper->findById($id);
		if ($project === null) {
			throw new \OutOfBoundsException("Project $id not found");
		}
		return $project;
	}

	public function createProject(
		IUser $creator,
		string $title,
		?string $customerContactUid,
		?string $responsibleUserId,
		?string $notes,
	): Project {
		$now = time();
		$project = new Project();
		$project->setTitle($title);
		$project->setCustomerContactUid($customerContactUid);
		$project->setResponsibleUserId($responsibleUserId);
		$project->setStatus(ProjectStatus::Draft->value);
		$project->setNotes($notes);
		$project->setCreatedAt($now);
		$project->setUpdatedAt($now);
		$project = $this->mapper->insert($project);

		// Projektnummer erst nach dem Insert bekannt, siehe ADR-0010.
		$project->setProjectNumber(sprintf('P-%05d', $project->getId()));
		$folder = $this->folderService->ensureProjectFolder($creator, $project->getProjectNumber());
		$project->setFilesFolderId($folder->getId());

		return $this->mapper->update($project);
	}

	/**
	 * @throws \OutOfBoundsException wenn das Projekt nicht existiert
	 */
	public function updateProject(
		int $id,
		string $title,
		?string $customerContactUid,
		?string $responsibleUserId,
		ProjectStatus $status,
		?string $notes,
	): Project {
		$project = $this->getProject($id);
		$project->setTitle($title);
		$project->setCustomerContactUid($customerContactUid);
		$project->setResponsibleUserId($responsibleUserId);
		$project->setStatus($status->value);
		$project->setNotes($notes);
		$project->setUpdatedAt(time());
		return $this->mapper->update($project);
	}
}
