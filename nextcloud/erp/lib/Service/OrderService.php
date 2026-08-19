<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use OCA\ERP\Db\Order;
use OCA\ERP\Db\OrderMapper;
use OCA\ERP\Projects\OrderStatus;

/**
 * Einfache Auftragserfassung pro Projekt (ADR-0010) — noch ohne Bezug zu
 * Angebotspositionen, die erst ab Phase 5 existieren.
 */
class OrderService {
	public function __construct(
		private OrderMapper $mapper,
	) {
	}

	/** @return Order[] */
	public function listOrders(int $projectId): array {
		return $this->mapper->findByProject($projectId);
	}

	public function createOrder(int $projectId, string $title, ?string $description): Order {
		$now = time();
		$order = new Order();
		$order->setProjectId($projectId);
		$order->setTitle($title);
		$order->setStatus(OrderStatus::Draft->value);
		$order->setDescription($description);
		$order->setCreatedAt($now);
		$order->setUpdatedAt($now);
		return $this->mapper->insert($order);
	}

	/** @throws \OutOfBoundsException */
	public function updateOrder(int $projectId, int $id, string $title, OrderStatus $status, ?string $description): Order {
		$order = $this->mapper->findOne($projectId, $id);
		if ($order === null) {
			throw new \OutOfBoundsException("Order $id not found in project $projectId");
		}
		$order->setTitle($title);
		$order->setStatus($status->value);
		$order->setDescription($description);
		$order->setUpdatedAt(time());
		return $this->mapper->update($order);
	}
}
