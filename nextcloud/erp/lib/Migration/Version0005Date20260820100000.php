<?php

declare(strict_types=1);

namespace OCA\ERP\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Artikel, Produkte, Angebote (Roadmap Phase 5, ADR-0011).
 */
class Version0005Date20260820100000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createVatRates($schema);
		$this->createWorkTypes($schema);
		$this->createArticles($schema);
		$this->createArticleSupplierPrices($schema);
		$this->createProducts($schema);
		$this->createProductComponents($schema);
		$this->createProductLabor($schema);
		$this->createQuotes($schema);
		$this->createQuoteGroups($schema);
		$this->createQuotePositions($schema);

		return $schema;
	}

	private function createVatRates(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_vat_rates')) {
			return;
		}
		$table = $schema->createTable('erp_vat_rates');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('percentage', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2]);
		$table->addColumn('is_default', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		$table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
	}

	private function createWorkTypes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_work_types')) {
			return;
		}
		$table = $schema->createTable('erp_work_types');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('hourly_rate', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('vat_rate_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
	}

	private function createArticles(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_articles')) {
			return;
		}
		$table = $schema->createTable('erp_articles');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('manufacturer', Types::STRING, ['notnull' => false, 'length' => 128]);
		$table->addColumn('manufacturer_article_no', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'Stk']);
		$table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('vat_rate_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['category'], 'erp_article_category_idx');
	}

	private function createArticleSupplierPrices(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_article_supplier_prices')) {
			return;
		}
		$table = $schema->createTable('erp_article_supplier_prices');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('article_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('supplier_contact_uid', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('supplier_article_no', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('purchase_price', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2]);
		$table->addColumn('currency', Types::STRING, ['notnull' => true, 'length' => 3, 'default' => 'EUR']);
		$table->addColumn('min_order_quantity', Types::DECIMAL, ['notnull' => false, 'precision' => 10, 'scale' => 2]);
		$table->addColumn('delivery_time', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['article_id'], 'erp_asp_article_idx');
	}

	private function createProducts(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_products')) {
			return;
		}
		$table = $schema->createTable('erp_products');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('description', Types::TEXT, ['notnull' => false]);
		$table->addColumn('vat_rate_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
	}

	private function createProductComponents(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_product_components')) {
			return;
		}
		$table = $schema->createTable('erp_product_components');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('product_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('article_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '1']);
		$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'Stk']);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['product_id'], 'erp_pc_product_idx');
	}

	private function createProductLabor(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_product_labor')) {
			return;
		}
		$table = $schema->createTable('erp_product_labor');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('product_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('work_type_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('hours', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['product_id'], 'erp_pl_product_idx');
	}

	private function createQuotes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_quotes')) {
			return;
		}
		$table = $schema->createTable('erp_quotes');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('quote_number', Types::STRING, ['notnull' => false, 'length' => 32]);
		$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('project_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('customer_contact_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft']);
		$table->addColumn('valid_until', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->addColumn('sent_at', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['quote_number'], 'erp_quote_number_idx');
		$table->addIndex(['project_id'], 'erp_quote_project_idx');
	}

	private function createQuoteGroups(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_quote_groups')) {
			return;
		}
		$table = $schema->createTable('erp_quote_groups');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('quote_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['quote_id'], 'erp_qg_quote_idx');
	}

	private function createQuotePositions(ISchemaWrapper $schema): void {
		if ($schema->hasTable('erp_quote_positions')) {
			return;
		}
		$table = $schema->createTable('erp_quote_positions');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('quote_id', Types::BIGINT, ['notnull' => true]);
		$table->addColumn('group_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('position_type', Types::STRING, ['notnull' => true, 'length' => 16]);
		$table->addColumn('reference_id', Types::BIGINT, ['notnull' => false]);
		$table->addColumn('description', Types::STRING, ['notnull' => true, 'length' => 500]);
		$table->addColumn('quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '1']);
		$table->addColumn('unit', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'Stk']);
		$table->addColumn('unit_price_net', Types::DECIMAL, ['notnull' => true, 'precision' => 10, 'scale' => 2, 'default' => '0']);
		$table->addColumn('vat_rate_percent', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 2, 'default' => '0']);
		$table->addColumn('position_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['quote_id'], 'erp_qp_quote_idx');
		$table->addIndex(['group_id'], 'erp_qp_group_idx');
	}
}
