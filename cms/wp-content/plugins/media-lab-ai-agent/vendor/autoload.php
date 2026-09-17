<?php
/**
 * Handgebauter Ersatz für Composers vendor/autoload.php.
 *
 * Grund: Dieses Plugin wird per SFTP-Directory-Replace auf Shared-Hosting
 * ohne SSH/Composer deployt (siehe README). Der vendor-Ordner wird deshalb
 * lokal gebaut und mit ins Repository committed, statt zur Laufzeit via
 * `composer install` erzeugt zu werden.
 *
 * Reihenfolge ist wichtig: setasign\Fpdi\FpdfTpl erbt von der globalen
 * (nicht-namespaced) \FPDF-Klasse — fpdf.php muss deshalb VOR dem
 * FPDI-Autoloader eingebunden werden, sonst schlägt das Laden der
 * Fpdi-Klasse mit "Class '\FPDF' not found" fehl.
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1) FPDF zuerst — globale, nicht-namespaced Basisklasse.
require_once __DIR__ . '/setasign/fpdf/fpdf.php';

// 2) FPDIs eigenes, mitgeliefertes Autoload-Script — registriert einen
//    spl_autoload_register()-Callback für den Namespace setasign\Fpdi\,
//    zeigt auf vendor/setasign/fpdi/src/. Unverändert aus dem offiziellen
//    FPDI-Paket übernommen (https://github.com/Setasign/FPDI), nicht
//    selbst nachgebaut, um Fehlerrisiko zu minimieren.
require_once __DIR__ . '/setasign/fpdi/src/autoload.php';
