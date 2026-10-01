<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

/**
 * Ein einzelnes Feld einer DATEV-EXTF-Zeile — hält zusätzlich zum Wert
 * fest, ob es in Anführungszeichen gehört (Text) oder nicht (Zahl,
 * Datum, Leerfeld). Siehe DatevExportService::csvLine()/raw()/text()
 * für die Begründung, warum das nicht am PHP-Typ hängt.
 */
final class DatevField {
	public function __construct(
		public readonly string $value,
		public readonly bool $quoted,
	) {
	}
}
