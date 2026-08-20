<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Product;
use OCA\ERP\Db\ProductComponent;
use OCA\ERP\Db\ProductComponentMapper;
use OCA\ERP\Db\ProductLabor;
use OCA\ERP\Db\ProductLaborMapper;
use OCA\ERP\Db\ProductMapper;

/** Produkte/Bundles aus Artikeln + Arbeitsleistungen (Roadmap Phase 5, ADR-0011). */
class ProductService {
	public function __construct(
		private ProductMapper $mapper,
		private ProductComponentMapper $componentMapper,
		private ProductLaborMapper $laborMapper,
	) {
	}

	/** @return Product[] */
	public function listAll(): array {
		return $this->mapper->findAll();
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): Product {
		$product = $this->mapper->findById($id);
		if ($product === null) {
			throw new \OutOfBoundsException("Product $id not found");
		}
		return $product;
	}

	public function getWithComponents(int $id): array {
		$product = $this->get($id);
		return [
			...$product->jsonSerialize(),
			'components' => $this->componentMapper->findByProduct($id),
			'labor' => $this->laborMapper->findByProduct($id),
		];
	}

	public function create(string $name, ?string $description, ?int $vatRateId, ?string $notes): Product {
		$now = time();
		$product = new Product();
		$product->setName($name);
		$product->setDescription($description);
		$product->setVatRateId($vatRateId);
		$product->setNotes($notes);
		$product->setCreatedAt($now);
		$product->setUpdatedAt($now);
		return $this->mapper->insert($product);
	}

	/** @throws \OutOfBoundsException */
	public function update(int $id, string $name, ?string $description, ?int $vatRateId, ?string $notes): Product {
		$product = $this->get($id);
		$product->setName($name);
		$product->setDescription($description);
		$product->setVatRateId($vatRateId);
		$product->setNotes($notes);
		$product->setUpdatedAt(time());
		return $this->mapper->update($product);
	}

	/** @throws \OutOfBoundsException */
	public function addComponent(int $productId, int $articleId, float $quantity, string $unit): ProductComponent {
		$this->get($productId);
		$component = new ProductComponent();
		$component->setProductId($productId);
		$component->setArticleId($articleId);
		$component->setQuantity($quantity);
		$component->setUnit($unit !== '' ? $unit : 'Stk');
		return $this->componentMapper->insert($component);
	}

	/** @throws \OutOfBoundsException */
	public function removeComponent(int $productId, int $id): void {
		$component = $this->componentMapper->findOne($productId, $id);
		if ($component === null) {
			throw new \OutOfBoundsException("Component $id not found for product $productId");
		}
		$this->componentMapper->delete($component);
	}

	/** @throws \OutOfBoundsException */
	public function addLabor(int $productId, int $workTypeId, float $hours): ProductLabor {
		$this->get($productId);
		$labor = new ProductLabor();
		$labor->setProductId($productId);
		$labor->setWorkTypeId($workTypeId);
		$labor->setHours($hours);
		return $this->laborMapper->insert($labor);
	}

	/** @throws \OutOfBoundsException */
	public function removeLabor(int $productId, int $id): void {
		$labor = $this->laborMapper->findOne($productId, $id);
		if ($labor === null) {
			throw new \OutOfBoundsException("Labor entry $id not found for product $productId");
		}
		$this->laborMapper->delete($labor);
	}
}
