<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Article;
use OCA\ERP\Db\ArticleMapper;
use OCA\ERP\Db\ArticleSupplierPrice;
use OCA\ERP\Db\ArticleSupplierPriceMapper;

/** Artikelstamm + Lieferantenpreise (Roadmap Phase 5, ADR-0011). */
class ArticleService {
	public function __construct(
		private ArticleMapper $mapper,
		private ArticleSupplierPriceMapper $priceMapper,
	) {
	}

	/** @return Article[] */
	public function listAll(): array {
		return $this->mapper->findAll();
	}

	/** @throws \OutOfBoundsException */
	public function get(int $id): Article {
		$article = $this->mapper->findById($id);
		if ($article === null) {
			throw new \OutOfBoundsException("Article $id not found");
		}
		return $article;
	}

	/** Artikel inkl. Lieferantenpreise für die Detailansicht. */
	public function getWithPrices(int $id): array {
		$article = $this->get($id);
		return [
			...$article->jsonSerialize(),
			'supplierPrices' => $this->priceMapper->findByArticle($id),
		];
	}

	public function create(
		string $name,
		?string $manufacturer,
		?string $manufacturerArticleNo,
		string $unit,
		?string $category,
		?int $vatRateId,
		?string $notes,
	): Article {
		$now = time();
		$article = new Article();
		$article->setName($name);
		$article->setManufacturer($manufacturer);
		$article->setManufacturerArticleNo($manufacturerArticleNo);
		$article->setUnit($unit !== '' ? $unit : 'Stk');
		$article->setCategory($category);
		$article->setVatRateId($vatRateId);
		$article->setNotes($notes);
		$article->setCreatedAt($now);
		$article->setUpdatedAt($now);
		return $this->mapper->insert($article);
	}

	/** @throws \OutOfBoundsException */
	public function update(
		int $id,
		string $name,
		?string $manufacturer,
		?string $manufacturerArticleNo,
		string $unit,
		?string $category,
		?int $vatRateId,
		?string $notes,
	): Article {
		$article = $this->get($id);
		$article->setName($name);
		$article->setManufacturer($manufacturer);
		$article->setManufacturerArticleNo($manufacturerArticleNo);
		$article->setUnit($unit !== '' ? $unit : 'Stk');
		$article->setCategory($category);
		$article->setVatRateId($vatRateId);
		$article->setNotes($notes);
		$article->setUpdatedAt(time());
		return $this->mapper->update($article);
	}

	/** Günstigster aktiver Einkaufspreis, oder null wenn keiner hinterlegt ist. */
	public function bestPurchasePrice(int $articleId): ?float {
		$prices = $this->priceMapper->findByArticle($articleId);
		if ($prices === []) {
			return null;
		}
		return min(array_map(static fn (ArticleSupplierPrice $p) => $p->getPurchasePrice(), $prices));
	}

	public function addSupplierPrice(
		int $articleId,
		string $supplierContactUid,
		?string $supplierArticleNo,
		float $purchasePrice,
		string $currency,
		?float $minOrderQuantity,
		?string $deliveryTime,
	): ArticleSupplierPrice {
		$this->get($articleId); // wirft OutOfBoundsException, falls Artikel nicht existiert
		$now = time();
		$price = new ArticleSupplierPrice();
		$price->setArticleId($articleId);
		$price->setSupplierContactUid($supplierContactUid);
		$price->setSupplierArticleNo($supplierArticleNo);
		$price->setPurchasePrice($purchasePrice);
		$price->setCurrency($currency !== '' ? $currency : 'EUR');
		$price->setMinOrderQuantity($minOrderQuantity);
		$price->setDeliveryTime($deliveryTime);
		$price->setCreatedAt($now);
		$price->setUpdatedAt($now);
		return $this->priceMapper->insert($price);
	}

	/** @throws \OutOfBoundsException */
	public function removeSupplierPrice(int $articleId, int $priceId): void {
		$price = $this->priceMapper->findOne($articleId, $priceId);
		if ($price === null) {
			throw new \OutOfBoundsException("Supplier price $priceId not found for article $articleId");
		}
		$this->priceMapper->delete($price);
	}
}
