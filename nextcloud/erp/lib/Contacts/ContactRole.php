<?php

declare(strict_types=1);

namespace OCA\ERP\Contacts;

enum ContactRole: string {
	case Customer = 'customer';
	case Supplier = 'supplier';
}
