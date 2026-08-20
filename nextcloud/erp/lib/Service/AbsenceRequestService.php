<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\AbsenceRequest;
use OCA\ERP\Db\AbsenceRequestMapper;
use OCA\ERP\Db\AbsenceTypeMapper;
use OCP\IUserManager;

/**
 * Urlaubs-/Abwesenheitsanträge inkl. Freigabe-Workflow (ADR-0012). Nutzt bei
 * Genehmigung die bestehende Calendar-Verknüpfung aus ADR-0009
 * (erp_calendar_links, resourceType='absence') statt einer eigenen Tabelle.
 */
class AbsenceRequestService {
	public function __construct(
		private AbsenceRequestMapper $mapper,
		private AbsenceTypeMapper $absenceTypeMapper,
		private CalendarService $calendarService,
		private IUserManager $userManager,
	) {
	}

	/** @return AbsenceRequest[] */
	public function listForUser(string $userId): array {
		return $this->mapper->findByUser($userId);
	}

	/** @return AbsenceRequest[] */
	public function listByStatus(string $status): array {
		return $this->mapper->findByStatus($status);
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): AbsenceRequest {
		$request = $this->mapper->findById($id);
		if ($request === null) {
			throw new \OutOfBoundsException("Absence request $id not found");
		}
		return $request;
	}

	/** @throws \OutOfBoundsException wenn absenceTypeId unbekannt ist */
	public function create(string $userId, int $absenceTypeId, string $startDate, string $endDate, ?string $notes): AbsenceRequest {
		$this->absenceTypeMapper->findById($absenceTypeId) ?? throw new \OutOfBoundsException("Absence type $absenceTypeId not found");

		$now = time();
		$request = new AbsenceRequest();
		$request->setUserId($userId);
		$request->setAbsenceTypeId($absenceTypeId);
		$request->setStartDate($startDate);
		$request->setEndDate($endDate);
		$request->setStatus('requested');
		$request->setNotes($notes);
		$request->setCreatedAt($now);
		$request->setUpdatedAt($now);
		return $this->mapper->insert($request);
	}

	/**
	 * Genehmigt einen offenen Antrag und versucht, einen Kalendertermin im
	 * ersten beschreibbaren Kalender des Antragstellers anzulegen — rein
	 * optional (ADR-0012: "legt optional einen Kalendertermin an"), ein
	 * fehlender/nicht beschreibbarer Kalender lässt die Genehmigung nicht
	 * scheitern.
	 *
	 * @throws \OutOfBoundsException wenn der Antrag nicht existiert
	 * @throws \DomainException wenn der Antrag nicht im Status 'requested' ist
	 */
	public function approve(int $id): AbsenceRequest {
		$request = $this->get($id);
		if ($request->getStatus() !== 'requested') {
			throw new \DomainException("Absence request $id is not in status 'requested'");
		}

		$request->setStatus('approved');
		$request->setUpdatedAt(time());
		$approved = $this->mapper->update($request);

		$this->tryCreateCalendarEvent($approved);

		return $approved;
	}

	private function tryCreateCalendarEvent(AbsenceRequest $request): void {
		$requester = $this->userManager->get($request->getUserId());
		if ($requester === null) {
			return;
		}
		$calendars = $this->calendarService->listCalendars($requester);
		$writable = array_values(array_filter($calendars, static fn (array $c) => $c['writable']));
		if ($writable === []) {
			return;
		}
		$absenceType = $this->absenceTypeMapper->findById($request->getAbsenceTypeId());
		$summary = 'Abwesenheit: ' . ($absenceType?->getName() ?? 'Unbekannt');

		try {
			$this->calendarService->createEvent(
				$requester,
				$writable[0]['uri'],
				'absence',
				(string)$request->getId(),
				$summary,
				new \DateTimeImmutable($request->getStartDate()),
				(new \DateTimeImmutable($request->getEndDate()))->modify('+1 day'),
			);
		} catch (\OutOfBoundsException|\InvalidArgumentException) {
			// Kalender optional — Genehmigung bleibt trotzdem gültig.
		}
	}

	/** @throws \OutOfBoundsException|\DomainException */
	public function reject(int $id): AbsenceRequest {
		$request = $this->get($id);
		if ($request->getStatus() !== 'requested') {
			throw new \DomainException("Absence request $id is not in status 'requested'");
		}
		$request->setStatus('rejected');
		$request->setUpdatedAt(time());
		return $this->mapper->update($request);
	}
}
