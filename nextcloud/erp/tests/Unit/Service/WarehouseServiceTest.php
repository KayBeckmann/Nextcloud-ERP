<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\Project;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Db\WarehouseMapper;
use OCA\ERP\Service\WarehouseService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class WarehouseServiceTest extends TestCase {
	private WarehouseMapper $warehouseMapper;
	private ProjectMapper $projectMapper;
	private WarehouseService $service;
	/** @var int[] */
	private array $projectIds = [];
	/** @var int[] */
	private array $warehouseIds = [];

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->warehouseMapper = new WarehouseMapper($db);
		$this->projectMapper = new ProjectMapper($db);
		$this->service = new WarehouseService($this->warehouseMapper, $this->projectMapper);
	}

	protected function tearDown(): void {
		foreach ($this->warehouseIds as $id) {
			$warehouse = $this->warehouseMapper->findById($id);
			if ($warehouse !== null) {
				$this->warehouseMapper->delete($warehouse);
			}
		}
		foreach ($this->projectIds as $id) {
			$project = $this->projectMapper->findById($id);
			if ($project !== null) {
				$this->projectMapper->delete($project);
			}
		}
		parent::tearDown();
	}

	public function testCreateSiteWarehouseBindsAnInProgressProject(): void {
		$projectId = $this->createProject('in_progress');

		$warehouse = $this->service->create('phpunit-site-warehouse', 'site', $projectId, null);
		$this->warehouseIds[] = $warehouse->getId();

		$this->assertSame($projectId, $warehouse->getProjectId());
	}

	public function testCreateSiteWarehouseRejectsCompletedProject(): void {
		$projectId = $this->createProject('done');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('projectId must reference an active project');
		$this->service->create('phpunit-completed-site-warehouse', 'site', $projectId, null);
	}

	public function testUpdateSiteWarehouseChangesItsActiveProject(): void {
		$firstProjectId = $this->createProject('in_progress');
		$secondProjectId = $this->createProject('waiting');
		$warehouse = $this->service->create('phpunit-site-warehouse', 'site', $firstProjectId, null);
		$this->warehouseIds[] = $warehouse->getId();

		$updated = $this->service->update($warehouse->getId(), 'phpunit-site-warehouse', true, null, 'site', $secondProjectId);

		$this->assertSame($secondProjectId, $updated->getProjectId());
	}

	public function testUpdateToNonSiteWarehouseClearsProjectBinding(): void {
		$projectId = $this->createProject('in_progress');
		$warehouse = $this->service->create('phpunit-site-warehouse', 'site', $projectId, null);
		$this->warehouseIds[] = $warehouse->getId();

		$updated = $this->service->update($warehouse->getId(), 'phpunit-central-warehouse', true, null, 'central');

		$this->assertNull($updated->getProjectId());
	}

	private function createProject(string $status): int {
		$project = new Project();
		$project->setTitle('phpunit-warehouse-project-' . uniqid());
		$project->setStatus($status);
		$project->setCreatedAt(time());
		$project->setUpdatedAt(time());
		$project = $this->projectMapper->insert($project);
		$this->projectIds[] = $project->getId();
		return $project->getId();
	}
}
