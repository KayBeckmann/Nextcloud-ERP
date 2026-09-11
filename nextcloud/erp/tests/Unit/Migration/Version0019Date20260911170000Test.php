<?php
declare(strict_types=1);
namespace OCA\ERP\Tests\Unit\Migration;
use OC\DB\Connection;
use OC\DB\SchemaWrapper;
use OCA\ERP\Migration\Version0019Date20260911170000;
use OCP\Migration\IOutput;
use Test\TestCase;
/** @group DB */
final class Version0019Date20260911170000Test extends TestCase {
 public function testCreatesVersionedWorkspaceAndControlledAssetReference(): void {
  $schema = new SchemaWrapper(\OC::$server->get(Connection::class));
  $result = (new Version0019Date20260911170000())->changeSchema($this->createMock(IOutput::class), static fn() => $schema, []);
  self::assertTrue($result->hasTable('erp_measurement_assets'));
  foreach (['project_id','file_id','mime_type','created_at','deleted_at'] as $column) self::assertTrue($result->getTable('erp_measurement_assets')->hasColumn($column));
  self::assertTrue($result->getTable('erp_measurement_assets')->hasIndex('erp_measurement_asset_project_file_uq'));
  foreach (['uuid','version','created_at','updated_at','deleted_at'] as $column) self::assertTrue($result->getTable('erp_measurement_blocks')->hasColumn($column));
 }
}
