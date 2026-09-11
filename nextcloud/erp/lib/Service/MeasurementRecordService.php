<?php

declare(strict_types=1);
namespace OCA\ERP\Service;
use OCA\ERP\Db\MeasurementRecord;
use OCA\ERP\Db\MeasurementRecordMapper;
class MeasurementRecordService {
	public function __construct(private MeasurementRecordMapper $mapper) {}
	/** @return list<MeasurementRecord> */ public function listByProject(int $projectId): array { return $this->mapper->findByProject($projectId); }
	public function get(int $id): MeasurementRecord { $record=$this->mapper->findById($id); if ($record === null || $record->getDeletedAt() !== null) throw new \OutOfBoundsException("Measurement record $id not found"); return $record; }
	public function createDraft(int $projectId, string $userId, string $title): MeasurementRecord { if ($projectId <= 0) throw new \InvalidArgumentException('projectId must be positive'); if (trim($title)==='') throw new \InvalidArgumentException('title must not be empty'); $now=time(); $record=new MeasurementRecord(); $record->setUuid($this->uuid()); $record->setProjectId($projectId); $record->setTitle(trim($title)); $record->setStatus('draft'); $record->setVersion(1); $record->setCreatedBy($userId); $record->setCreatedAt($now); $record->setUpdatedAt($now); return $this->mapper->insert($record); }
	public function assertDraft(int $id): MeasurementRecord { $record=$this->get($id); if ($record->getStatus() !== 'draft') throw new \DomainException('Only draft measurement records can be mutated'); return $record; }
	public function transitionStatus(int $id, string $toStatus, string $userId): MeasurementRecord { $record=$this->get($id); $allowed=['draft'=>['submitted'], 'submitted'=>['approved'], 'approved'=>[]]; $from=$record->getStatus(); if (!in_array($toStatus,$allowed[$from] ?? [],true)) throw new \DomainException("Transition from $from to $toStatus is not allowed"); $record->setStatus($toStatus); $record->setVersion($record->getVersion()+1); $record->setUpdatedAt(time()); return $this->mapper->update($record); }
	private function uuid(): string { $bytes=random_bytes(16); $bytes[6]=chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8]=chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
}
