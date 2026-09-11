<?php

declare(strict_types=1);

namespace OCA\ERP\Tests\Unit\Service;

use OCA\ERP\Db\MeasurementRecord;
use OCA\ERP\Db\MeasurementRecordMapper;
use OCA\ERP\Service\MeasurementRecordService;
use PHPUnit\Framework\TestCase;

final class MeasurementRecordServiceTest extends TestCase {
	public function testCreatesProjectBoundDraftWithUuidAndTransitionsToApproved(): void {
		$record = null;
		$mapper = $this->createMock(MeasurementRecordMapper::class);
		$mapper->method('insert')->willReturnCallback(static function (MeasurementRecord $created) use (&$record): MeasurementRecord {
			$created->setId(42);
			$record = $created;
			return $created;
		});
		$mapper->method('update')->willReturnArgument(0);
		$mapper->method('findById')->willReturnCallback(static function (int $id) use (&$record): ?MeasurementRecord {
			return $id === 42 ? $record : null;
		});
		$service = new MeasurementRecordService($mapper);

		$draft = $service->createDraft(7, 'phpunit-user', 'Kitchen measure');
		self::assertSame(7, $draft->getProjectId());
		self::assertSame('draft', $draft->getStatus());
		self::assertSame('phpunit-user', $draft->getCreatedBy());
		self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $draft->getUuid());

		$submitted = $service->transitionStatus(42, 'submitted', 'phpunit-user');
		self::assertSame('submitted', $submitted->getStatus());
		$approved = $service->transitionStatus(42, 'approved', 'phpunit-user');
		self::assertSame('approved', $approved->getStatus());
	}

	public function testRejectsMutationOfApprovedRecord(): void {
		$record = new MeasurementRecord();
		$record->setId(9);
		$record->setProjectId(7);
		$record->setStatus('approved');
		$mapper = $this->createMock(MeasurementRecordMapper::class);
		$mapper->method('findById')->with(9)->willReturn($record);
		$service = new MeasurementRecordService($mapper);

		$this->expectException(\DomainException::class);
		$service->assertDraft(9);
	}
}
