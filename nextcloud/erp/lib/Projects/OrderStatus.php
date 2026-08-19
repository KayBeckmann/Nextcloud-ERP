<?php

declare(strict_types=1);

namespace OCA\ERP\Projects;

enum OrderStatus: string {
	case Draft = 'draft';
	case Confirmed = 'confirmed';
	case Done = 'done';
}
