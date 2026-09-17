<?php
/**
 * Teilt lange PDFs in kleinere "Fenster"-Dateien auf, damit jede einzelne
 * Datei innerhalb von Anthropics Limit für native PDF-Dokumenten-Anhänge
 * bleibt (aktuell 100 Seiten pro Anfrage). Betrifft in der Praxis vor allem
 * lange technische Manuals/Datenblätter — ohne Splitting müsste bei diesen
 * Dokumenten immer auf reinen Text-Kontext zurückgefallen werden (siehe
 * CHANGELOG 1.5.3), was bei formel-/diagrammlastigen Inhalten die Antwort-
 * qualität spürbar verschlechtert.
 *
 * Nutzt FPDI (github.com/Setasign/FPDI) + FPDF als Backend — lokal gebaut
 * und im vendor/-Ordner committed (siehe vendor/autoload.php), da dieses
 * Plugin per SFTP-Directory-Replace ohne Server-Composer deployt wird.
 *
 * Der kostenlose FPDI-Parser kommt bei PDFs mit komprimierten Cross-
 * Reference-Tabellen/Object-Streams (PDF 1.5+) nicht klar — betrifft in der
 * Praxis einen relevanten Teil moderner PDF-Generatoren. Falls Ghostscript
 * auf dem Server verfügbar ist (exec()/shell_exec() erlaubt, `gs`-Binary
 * vorhanden — z.B. bestätigt auf Hetzner-Shared-Hosting), wird die Datei
 * damit einmalig normalisiert, bevor FPDI erneut versucht. Ohne Ghostscript:
 * sauberer Fallback auf die Originaldatei (dann ggf. deren eigenes Scheitern
 * am 100-Seiten-Limit und Rückfall auf Text-Kontext, siehe class-ai-agent-rest.php).
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_Pdf_Splitter {

    // Sicherheitsmarge unter Anthropics 100-Seiten-Limit — falls die
    // Fenstergrenze exakt auf 100 läge, würde ein Off-by-one-Fehler in der
    // Zählung sofort wieder den API-Fehler auslösen. 90 lässt Luft.
    private const MAX_PAGES_PER_WINDOW = 90;

    // Ab dieser Seitenzahl wird überhaupt gesplittet — bewusst etwas unter
    // 100, damit auch ein Dokument mit z.B. 98 Seiten (technisch noch
    // erlaubt) nicht knapp an anderen Limits (Bild-/Vektor-Komplexität pro
    // Seite) scheitert. Bei <= 95 Seiten bleibt die Originaldatei unverändert.
    private const SPLIT_THRESHOLD = 95;

    /**
     * Liefert die Liste der tatsächlich zu verwendenden PDF-Dateipfade für
     * ein gegebenes Attachment. Bei Dokumenten unterhalb des Schwellwerts
     * (oder wenn FPDI fehlt/das Splitting fehlschlägt) wird schlicht die
     * Originaldatei als einziges Element zurückgegeben — Aufrufer müssen also
     * nicht zwischen "gesplittet" und "unverändert" unterscheiden.
     *
     * Fenster-Dateien werden unter einem deterministischen Namen (abhängig
     * von der Attachment-ID) im Uploads-Verzeichnis zwischengespeichert —
     * ein erneutes Splitting (z.B. bei Reindexierung) überschreibt sie
     * einfach, statt Datenmüll anzuhäufen.
     *
     * @return string[] Absolute Dateipfade, mindestens ein Element.
     */
    public static function get_window_paths(string $source_path, int $attachment_id): array {
        if (!self::library_available()) {
            return [$source_path];
        }

        if (!file_exists($source_path) || !is_readable($source_path)) {
            return [$source_path];
        }

        // $effective_path ist die Datei, die FPDI tatsächlich zum Lesen der
        // Seiten verwendet — normalerweise die Originaldatei, außer der
        // freie FPDI-Parser kommt mit deren Struktur nicht klar (siehe unten).
        $effective_path = $source_path;

        try {
            $reader = new \setasign\Fpdi\Fpdi();
            $page_count = $reader->setSourceFile($effective_path);
        } catch (\Throwable $e) {
            // Häufigste Ursache: komprimierte Cross-Reference-Tabellen/Object-
            // Streams (PDF 1.5+) — das kann der KOSTENLOSE FPDI-Parser nicht
            // lesen (kommerzielle Erweiterung nötig, siehe README). Falls
            // Ghostscript verfügbar ist, die Datei damit einmalig neu
            // schreiben lassen (Standard-pdfwrite-Konvertierung, kein
            // Custom-PostScript, Ghostscripts Sandboxing bleibt aktiv) —
            // Ghostscript-Output nutzt praktisch immer klassische, unkompri-
            // mierte Cross-Reference-Tabellen, die FPDI danach lesen kann.
            $normalized_path = self::normalize_with_ghostscript($source_path, $attachment_id);

            if ($normalized_path === null) {
                mlt_ai_log_error(
                    'PDF-Splitting: setSourceFile fehlgeschlagen, kein Ghostscript verfügbar, nutze Originaldatei: ' . $e->getMessage(),
                    ['attachment_id' => $attachment_id]
                );
                return [$source_path];
            }

            try {
                $reader = new \setasign\Fpdi\Fpdi();
                $page_count = $reader->setSourceFile($normalized_path);
                $effective_path = $normalized_path;
            } catch (\Throwable $e2) {
                mlt_ai_log_error(
                    'PDF-Splitting: auch nach Ghostscript-Normalisierung fehlgeschlagen, nutze Originaldatei: ' . $e2->getMessage(),
                    ['attachment_id' => $attachment_id]
                );
                return [$source_path];
            }
        }

        if ($page_count <= self::SPLIT_THRESHOLD) {
            return [$source_path];
        }

        $split_dir = self::get_split_dir();
        if ($split_dir === null) {
            return [$source_path];
        }

        $window_size = self::MAX_PAGES_PER_WINDOW;
        $window_count = (int) ceil($page_count / $window_size);
        $window_paths = [];

        for ($window_index = 0; $window_index < $window_count; $window_index++) {
            $start_page = $window_index * $window_size + 1;
            $end_page = min($start_page + $window_size - 1, $page_count);
            $window_path = $split_dir . "attachment-{$attachment_id}-w{$window_index}.pdf";

            $success = self::write_window(
                $effective_path,
                $window_path,
                $start_page,
                $end_page,
                $attachment_id,
                $window_index
            );

            if ($success) {
                $window_paths[] = $window_path;
            }
        }

        // Falls aus irgendeinem Grund kein einziges Fenster erfolgreich
        // geschrieben wurde, lieber mit der (zu großen) Originaldatei
        // weitermachen als mit einer leeren Liste — der bestehende
        // 100-Seiten-Fallback im REST-Handler fängt das dann ohnehin ab.
        return !empty($window_paths) ? $window_paths : [$source_path];
    }

    /**
     * Schreibt die komplette PDF einmal über Ghostscripts pdfwrite-Device neu
     * (Standard-Konvertierung, keine Custom-PostScript-Tricks, Ghostscripts
     * eigenes Sandboxing/-dSAFER bleibt aktiv — wichtig, da wir hier mit von
     * Nutzern hochgeladenen, nicht vertrauenswürdigen PDFs arbeiten). Das
     * Ergebnis nutzt praktisch immer klassische Cross-Reference-Tabellen,
     * unabhängig davon, welche (ggf. komprimierte) Struktur das Original hatte.
     *
     * @return string|null Pfad zur normalisierten Datei, oder null falls
     *   Ghostscript nicht verfügbar ist oder die Konvertierung fehlschlägt.
     */
    private static function normalize_with_ghostscript(string $source_path, int $attachment_id): ?string {
        if (!self::ghostscript_available()) {
            return null;
        }

        $split_dir = self::get_split_dir();
        if ($split_dir === null) {
            return null;
        }

        $normalized_path = $split_dir . "attachment-{$attachment_id}-normalized.pdf";

        $command = sprintf(
            // -dCompatibilityLevel=1.4 erzwingt klassisches PDF-1.4-Format:
            // komprimierte Cross-Reference-Tabellen/Object-Streams wurden
            // erst mit PDF 1.5 eingeführt, sind in 1.4 also technisch gar
            // nicht möglich — Ghostscript MUSS dadurch klassische, für FPDI
            // lesbare Cross-Reference-Tabellen schreiben. Ohne dieses Flag
            // nutzt Ghostscript (ab Version 10.x beobachtet) standardmäßig
            // selbst wieder komprimierte Streams im Output, wodurch die
            // Normalisierung sonst wirkungslos bliebe.
            'gs -q -dNOPAUSE -dBATCH -dCompatibilityLevel=1.4 -sDEVICE=pdfwrite -o %s %s 2>&1',
            escapeshellarg($normalized_path),
            escapeshellarg($source_path)
        );

        $output = [];
        $return_code = null;
        exec($command, $output, $return_code);

        if ($return_code !== 0 || !file_exists($normalized_path)) {
            mlt_ai_log_error(
                'Ghostscript-Normalisierung fehlgeschlagen: ' . implode(' ', $output),
                ['attachment_id' => $attachment_id, 'return_code' => $return_code]
            );
            return null;
        }

        return $normalized_path;
    }

    /**
     * Prüft (und cached für die Dauer des Requests), ob Ghostscript auf
     * diesem Server nutzbar ist: exec() muss verfügbar UND nicht per
     * disable_functions gesperrt sein, UND das gs-Binary muss tatsächlich
     * antworten. Viele Shared-Hosting-Umgebungen sperren exec() komplett —
     * dann läuft das Plugin unverändert mit dem reinen FPDI-Pfad weiter.
     */
    private static function ghostscript_available(): bool {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        if (!function_exists('exec')) {
            return $available = false;
        }

        $disabled_functions = (string) ini_get('disable_functions');
        if (strpos($disabled_functions, 'exec') !== false) {
            return $available = false;
        }

        $output = [];
        $return_code = null;
        @exec('gs --version 2>&1', $output, $return_code);

        return $available = ($return_code === 0 && !empty($output));
    }

    /**
     * Schreibt ein einzelnes Seiten-Fenster als eigenständige PDF-Datei.
     * Eigener try/catch pro Fenster, damit ein fehlerhaftes Fenster (z.B.
     * eine einzelne besonders komplexe Seite) nicht die anderen, funktio-
     * nierenden Fenster mit zu Fall bringt.
     */
    private static function write_window(
        string $source_path,
        string $window_path,
        int $start_page,
        int $end_page,
        int $attachment_id,
        int $window_index
    ): bool {
        try {
            $writer = new \setasign\Fpdi\Fpdi();
            $writer->setSourceFile($source_path);

            for ($page = $start_page; $page <= $end_page; $page++) {
                $template_id = $writer->importPage($page);
                $size = $writer->getTemplateSize($template_id);
                $writer->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $writer->useTemplate($template_id);
            }

            $writer->Output('F', $window_path);

            return file_exists($window_path);
        } catch (\Throwable $e) {
            mlt_ai_log_error(
                'PDF-Splitting: Fenster fehlgeschlagen: ' . $e->getMessage(),
                ['attachment_id' => $attachment_id, 'window_index' => $window_index, 'pages' => "{$start_page}-{$end_page}"]
            );
            return false;
        }
    }

    /**
     * Löscht zwischengespeicherte Fenster- und Normalisierungs-Dateien eines
     * Attachments (z.B. wenn die Originaldatei gelöscht oder ersetzt wird).
     */
    public static function delete_windows(int $attachment_id): void {
        $split_dir = self::get_split_dir();
        if ($split_dir === null) {
            return;
        }

        $pattern = $split_dir . "attachment-{$attachment_id}-*.pdf";
        foreach (glob($pattern) ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function get_split_dir(): ?string {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return null;
        }

        $split_dir = trailingslashit($upload_dir['basedir']) . 'mlt-ai-pdf-windows/';
        if (!file_exists($split_dir)) {
            wp_mkdir_p($split_dir);
        }

        return is_dir($split_dir) && is_writable($split_dir) ? $split_dir : null;
    }

    private static function library_available(): bool {
        if (!class_exists('\setasign\Fpdi\Fpdi')) {
            $autoload = MLT_AI_AGENT_PATH . 'vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
        }

        return class_exists('\setasign\Fpdi\Fpdi');
    }
}
