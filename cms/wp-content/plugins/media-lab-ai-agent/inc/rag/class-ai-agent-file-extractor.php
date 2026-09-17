<?php
/**
 * Dateiinhalt-Extraktion für das RAG-Modul (PDF, PPTX, DOCX).
 *
 * Bewusst ohne externe Bibliotheken (kein Composer-Vendor-Ordner) umgesetzt,
 * damit das Plugin weiterhin per simplem SFTP-Directory-Replace auf
 * Shared-Hosting ohne SSH/Composer lauffähig bleibt. Nutzt ausschließlich
 * PHP-Bordmittel: zlib (PDF-Streams), ZipArchive + DOM/XPath (PPTX/DOCX sind
 * beides ZIP-Archive mit XML-Inhalt).
 *
 * Einschränkung PDF: funktioniert für "born-digital" PDFs mit Standard-
 * Zeichenkodierung (aus Word/InDesign/PowerPoint exportiert — der Normalfall
 * bei Manuals/Datenblättern). Gescannte PDFs (reine Bild-Seiten ohne echten
 * Text-Layer) benötigen OCR und werden hier nicht unterstützt.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class MLT_AI_File_Extractor {

    /**
     * Zentrale Einstiegsstelle: wählt anhand des MIME-Typs die passende
     * Extraktionsmethode. Gibt bei nicht unterstütztem Typ oder Fehler immer
     * einen leeren String zurück statt zu werfen — Aufrufer (Indexer)
     * behandelt einen leeren Rückgabewert als "nichts zu indexieren".
     */
    public static function extract_text(string $file_path, string $mime_type): string {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return '';
        }

        try {
            switch ($mime_type) {
                case 'application/pdf':
                    return self::extract_pdf_text($file_path);

                case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                    return class_exists('ZipArchive') ? self::extract_pptx_text($file_path) : '';

                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    return class_exists('ZipArchive') ? self::extract_docx_text($file_path) : '';

                default:
                    return '';
            }
        } catch (Throwable $e) {
            mlt_ai_log_error('Datei-Textextraktion fehlgeschlagen: ' . $e->getMessage(), ['file' => basename($file_path)]);
            return '';
        }
    }

    /**
     * Vereinfachte PDF-Textextraktion: liest alle stream/endstream-Blöcke,
     * entkomprimiert FlateDecode-Streams via zlib, extrahiert Text aus den
     * Standard-Anzeige-Operatoren Tj (einzelner String) und TJ (Array aus
     * Strings + Kerning-Zahlen). Kein vollständiger PDF-Parser — deckt aber
     * den weit überwiegenden Teil regulärer, textbasierter PDFs ab.
     */
    private static function extract_pdf_text(string $path): string {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return '';
        }

        // Lineares strpos()-Scannen statt Regex über die Gesamtdatei: Ein
        // preg_match_all mit .*? über mehrere MB Binärdaten kann PHPs
        // PCRE-Backtracking-Limit reißen (Standard 1 Mio. Schritte) und dann
        // komplett `false` zurückgeben statt Teilergebnissen — beobachtet an
        // einer echten 14,8MB-PDF, bei der dadurch trotz korrektem Pattern
        // still gar kein Text extrahiert wurde. strpos() hat kein Backtracking
        // und keine derartigen Limits.
        $text = '';
        $offset = 0;
        $length = strlen($raw);

        while (($stream_pos = strpos($raw, 'stream', $offset)) !== false) {
            $content_start = $stream_pos + 6; // Länge von "stream"

            // PDF-Spezifikation: Zeilenende ist CR, LF oder CRLF — alle drei abdecken.
            if (substr($raw, $content_start, 2) === "\r\n") {
                $content_start += 2;
            } elseif (in_array($raw[$content_start] ?? '', ["\r", "\n"], true)) {
                $content_start += 1;
            } else {
                // Kein gültiger Stream-Start (Wort "stream" tauchte anderswo im
                // Binärmüll auf) — direkt hinter diesem Fund weitersuchen.
                $offset = $stream_pos + 6;
                continue;
            }

            $endstream_pos = strpos($raw, 'endstream', $content_start);
            if ($endstream_pos === false) {
                break; // Kein passendes Ende mehr, Dateiende erreicht.
            }

            // Große Streams (>200KB komprimiert) sind praktisch nie echte
            // Text-Content-Streams, sondern eingebettete Bilder/Schriftarten —
            // FlateDecode wird für beides genutzt. Das Text-Operator-Regex
            // darauf loszulassen kostet bei einer bebilderten 120-Seiten-PDF
            // mehrere zehn Sekunden für buchstäblich null Treffer (getestet:
            // 57s → 1,3s durch diesen Filter, identisches Textergebnis).
            $stream_length = $endstream_pos - $content_start;
            if ($stream_length > 200000) {
                $offset = $endstream_pos + 9; // Länge von "endstream"
                continue;
            }

            $stream_content = rtrim(substr($raw, $content_start, $stream_length), "\r\n");
            $decoded = @gzuncompress($stream_content);
            $content = $decoded !== false ? $decoded : $stream_content;
            $text .= self::extract_pdf_text_operators($content) . ' ';

            $offset = $endstream_pos + 9;
        }

        // WinAnsiEncoding (PDF-Standard für westliche Sprachen, praktisch
        // identisch zu Windows-1252) liefert Zeichen wie ©, „ " typografische
        // Anführungszeichen oder ° als rohe Einzelbyte-Werte — als UTF-8
        // interpretiert sind das ungültige Byte-Sequenzen. WordPress' $wpdb->insert()
        // lehnt solche Werte in einer UTF-8-Spalte kommentarlos ab (kein Fehler,
        // die Zeile fehlt einfach) — betraf in einem echten Testfall 72 von 89
        // Chunks eines technischen Manuals. Konvertierung behebt das für den
        // Standard-Zeichensatz; verbleibende Einzelfälle (z.B. Symbol-Font-
        // Zeichen ohne Windows-1252-Entsprechung) fängt ensure_valid_utf8() ab.
        $text = self::convert_from_winansi($text);
        $text = self::ensure_valid_utf8($text);

        return self::normalize_whitespace($text);
    }

    private static function convert_from_winansi(string $text): string {
        if (!function_exists('mb_convert_encoding')) {
            return $text;
        }
        $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        return $converted !== false ? $converted : $text;
    }

    /**
     * Sicherheitsnetz: entfernt verbleibende ungültige UTF-8-Byte-Sequenzen
     * (z.B. aus Symbol-Font- oder anderen Nicht-WinAnsi-Zeichenkodierungen),
     * damit ein einzelnes fehlerhaftes Zeichen nicht den kompletten Chunk beim
     * DB-Insert zum Verschwinden bringt.
     */
    private static function ensure_valid_utf8(string $text): string {
        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($clean !== false) {
                return $clean;
            }
        }
        // Fallback ohne iconv: alles über ASCII grob entfernen statt gar nichts zu tun.
        return preg_replace('/[\x80-\xFF]/', '', $text);
    }

    private static function extract_pdf_text_operators(string $content): string {
        $text = '';

        // Einfacher Fall: (Text) Tj
        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*Tj/', $content, $m)) {
            foreach ($m[0] as $chunk) {
                $text .= self::extract_pdf_paren_string($chunk) . ' ';
            }
        }

        // Array-Fall: [(Teil1) -200 (Teil2) ...] TJ
        if (preg_match_all('/\[(.*?)\]\s*TJ/', $content, $m)) {
            foreach ($m[1] as $group) {
                if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/', $group, $m2)) {
                    foreach ($m2[0] as $piece) {
                        $text .= self::extract_pdf_paren_string($piece);
                    }
                    $text .= ' ';
                }
            }
        }

        return $text;
    }

    private static function extract_pdf_paren_string(string $chunk): string {
        if (!preg_match('/\((?:\\\\.|[^\\\\()])*\)/', $chunk, $m)) {
            return '';
        }
        $inner = substr($m[0], 1, -1);
        // PDF-Escape-Sequenzen für Klammern/Backslash auflösen.
        return str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $inner);
    }

    /**
     * PPTX = ZIP-Archiv mit einer XML-Datei pro Folie unter ppt/slides/.
     * Text steht in <a:t>-Knoten (DrawingML) — per XPath mit local-name()
     * abgefragt, damit es unabhängig vom verwendeten Namespace-Prefix
     * funktioniert (manche Exporte weichen hier ab).
     */
    private static function extract_pptx_text(string $path): string {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $text = '';
        $slide_index = 1;
        while (($xml = $zip->getFromName("ppt/slides/slide{$slide_index}.xml")) !== false) {
            $text .= self::extract_text_nodes($xml, "//*[local-name()='t']") . "\n";
            $slide_index++;
        }

        $zip->close();
        return self::normalize_whitespace($text);
    }

    /**
     * DOCX = ZIP-Archiv, Haupttext in word/document.xml. Text pro Absatz
     * (<w:p>) gruppiert, damit die Struktur beim Chunking grob erhalten bleibt.
     */
    private static function extract_docx_text(string $path): string {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        $dom = new DOMDocument();
        $previous_setting = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_use_internal_errors($previous_setting);

        if (!$loaded) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $paragraphs = $xpath->query("//*[local-name()='p']");
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $runs = $xpath->query(".//*[local-name()='t']", $paragraph);
            $line = '';
            foreach ($runs as $run) {
                $line .= $run->textContent;
            }
            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private static function extract_text_nodes(string $xml, string $xpath_query): string {
        $dom = new DOMDocument();
        $previous_setting = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_use_internal_errors($previous_setting);

        if (!$loaded) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $texts = [];
        foreach ($xpath->query($xpath_query) as $node) {
            $texts[] = $node->textContent;
        }

        return implode(' ', $texts);
    }

    private static function normalize_whitespace(string $text): string {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
