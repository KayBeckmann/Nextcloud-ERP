<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Contacts\ContactRole;
use OCA\ERP\Db\ContactLink;
use OCA\ERP\Db\ContactLinkMapper;
use OCA\ERP\Db\ContactPerson;
use OCA\ERP\Db\ContactPersonMapper;
use OCA\ERP\Db\Project;
use OCA\ERP\Db\ProjectContactOverrideMapper;
use OCA\ERP\Db\ProjectMapper;
use OCA\ERP\Documents\DocumentType;
use OCA\ERP\Projects\ProjectStatus;
use OCA\ERP\Service\ProjectContactOverrideService;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
final class ProjectContactOverrideServiceTest extends TestCase {
	private const TEST_UID = 'phpunit-cpoverrides-1';

	private ProjectContactOverrideService $service;
	private ProjectContactOverrideMapper $mapper;
	private ProjectMapper $projectMapper;
	private ContactLinkMapper $linkMapper;
	private ContactPersonMapper $personMapper;
	private int $projectId;
	private int $linkId;
	private int $personId;
	private int $otherPersonId;

	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->get(IDBConnection::class);
		$this->mapper = new ProjectContactOverrideMapper($db);
		$this->projectMapper = new ProjectMapper($db);
		$this->linkMapper = new ContactLinkMapper($db);
		$this->personMapper = new ContactPersonMapper($db);
		$this->service = new ProjectContactOverrideService($this->mapper, $this->projectMapper, $this->linkMapper, $this->personMapper);

		$now = time();
		$project = new Project();
		$project->setTitle('phpunit-cpoverrides-project');
		$project->setCustomerContactUid(self::TEST_UID);
		$project->setStatus(ProjectStatus::Draft->value);
		$project->setCreatedAt($now);
		$project->setUpdatedAt($now);
		$this->projectId = $this->projectMapper->insert($project)->getId();

		$link = new ContactLink();
		$link->setContactUid(self::TEST_UID);
		$link->setRole(ContactRole::Customer->value);
		$link->setCreatedAt($now);
		$link->setUpdatedAt($now);
		$this->linkId = $this->linkMapper->insert($link)->getId();

		$this->personId = $this->createPerson($this->linkId, 'Finn Könnecker');

		$otherLink = new ContactLink();
		$otherLink->setContactUid('phpunit-cpoverrides-other');
		$otherLink->setRole(ContactRole::Customer->value);
		$otherLink->setCreatedAt($now);
		$otherLink->setUpdatedAt($now);
		$otherLinkId = $this->linkMapper->insert($otherLink)->getId();
		$this->otherPersonId = $this->createPerson($otherLinkId, 'Fremde Person');
	}

	protected function tearDown(): void {
		foreach ($this->mapper->findByProject($this->projectId) as $o) {
			$this->mapper->delete($o);
		}
		$project = $this->projectMapper->findById($this->projectId);
		if ($project !== null) {
			$this->projectMapper->delete($project);
		}
		foreach ($this->linkMapper->findByRole(ContactRole::Customer->value) as $link) {
			if (!str_starts_with($link->getContactUid(), 'phpunit-cpoverrides-')) {
				continue;
			}
			foreach ($this->personMapper->findByContactLink($link->getId()) as $p) {
				$this->personMapper->delete($p);
			}
			$this->linkMapper->delete($link);
		}
		parent::tearDown();
	}

	private function createPerson(int $linkId, string $name): int {
		$now = time();
		$person = new ContactPerson();
		$person->setContactLinkId($linkId);
		$person->setName($name);
		$person->setCreatedAt($now);
		$person->setUpdatedAt($now);
		return $this->personMapper->insert($person)->getId();
	}

	public function testSetAndGetRoundTrips(): void {
		$this->service->set($this->projectId, DocumentType::Invoice, $this->personId);

		self::assertSame($this->personId, $this->service->getForProject($this->projectId)[DocumentType::Invoice->value]);
	}

	public function testSetNullClearsExistingOverride(): void {
		$this->service->set($this->projectId, DocumentType::Invoice, $this->personId);
		$this->service->set($this->projectId, DocumentType::Invoice, null);

		self::assertNull($this->service->getForProject($this->projectId)[DocumentType::Invoice->value]);
	}

	public function testSetRejectsPersonNotBelongingToProjectCustomer(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->set($this->projectId, DocumentType::Invoice, $this->otherPersonId);
	}

	public function testSetRejectsUnknownProject(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->service->set(999999999, DocumentType::Invoice, $this->personId);
	}
}
