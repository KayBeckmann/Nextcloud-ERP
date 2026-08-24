<?php

declare(strict_types=1);

namespace OCA\ERP\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use OCP\Files\Folder;

/**
 * Rendert HTML zu PDF und legt das Ergebnis mit Zeitstempel im
 * Dateinamen in einem Ordner ab (ADR-0021). Domänenspezifisch bleibt nur
 * die HTML-Erzeugung (je Belegtyp im jeweiligen Service, z. B.
 * InvoiceService::renderHtml()) — dieser Service kennt keine
 * Angebots-/Rechnungs-/Belegtypen, nur HTML-in-PDF-Bytes-aus.
 */
class DocumentPdfService {
	/**
	 * Schreibt eine neue PDF-Datei in $folder und gibt deren fileId zurück.
	 * Dateiname: `<Belegnummer>_<Y-m-d>T<H-i>.pdf` — der Zeitstempel sorgt
	 * dafür, dass eine erneute Erzeugung nie eine vorhandene Datei
	 * stillschweigend überschreibt (ADR-0021).
	 */
	public function writePdf(Folder $folder, string $documentNumber, string $html): int {
		$options = new Options();
		// Keine externen Ressourcen (Bilder/Fonts per URL) laden — die
		// Belege sind reine Text-/Tabellenlayouts, und ein serverseitiger
		// Renderer sollte nie fremde URLs nachladen (SSRF-Risiko).
		$options->set('isRemoteEnabled', false);
		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();

		$fileName = $this->uniqueFileName($folder, $documentNumber);
		$file = $folder->newFile($fileName);
		$file->putContent($dompdf->output());
		return $file->getId();
	}

	private function uniqueFileName(Folder $folder, string $documentNumber): string {
		$safeNumber = preg_replace('/[^A-Za-z0-9_.-]/', '_', $documentNumber) ?? 'dokument';
		$base = $safeNumber . '_' . date('Y-m-d\TH-i');
		$fileName = $base . '.pdf';
		// Praktisch nie nötig (Trigger-Punkte feuern je Beleg nur einmal
		// pro Minute), aber ein Kollisionsschutz ist billiger als ein
		// Absturz beim Ausstellen eines Belegs.
		$suffix = 2;
		while ($folder->nodeExists($fileName)) {
			$fileName = $base . '_' . $suffix . '.pdf';
			$suffix++;
		}
		return $fileName;
	}
}
