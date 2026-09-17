<?php
/**
 * Verknüpft das Produkt-Dokumente-ACF-Feld (Datenblätter, Manuals, Spec Sheets)
 * mit dem RAG-Index: die Produktseite selbst bekommt einen Hinweis auf ihre
 * Dokumente im indexierten Text, damit die Suche auch dann zum richtigen
 * Dokument führt, wenn die PDF-Textextraktion selbst (z.B. bei formellastigen
 * Inhalten) schwächer trifft als der sauber strukturierte Produkttext.
 *
 * Nutzt bewusst den bestehenden Erweiterungspunkt mlt_ai_rag_indexable_text_parts
 * statt den Core-Indexer direkt zu ändern — bleibt damit optional und
 * unabhängig aktivierbar/deaktivierbar über das ACF-Feld selbst.
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('mlt_ai_rag_indexable_text_parts', function (array $parts, WP_Post $post): array {
    if ($post->post_type !== 'product' || !function_exists('get_field')) {
        return $parts;
    }

    $documents = get_field('mlt_ai_product_documents', $post->ID);
    if (empty($documents) || !is_array($documents)) {
        return $parts;
    }

    $names = [];
    foreach ($documents as $row) {
        $file = $row['file'] ?? null;
        $filename = is_array($file) ? ($file['filename'] ?? '') : '';
        if ($filename === '' && is_numeric($file)) {
            // ACF-Feldgruppe evtl. nicht synchronisiert — get_field() liefert dann
            // die rohe Attachment-ID statt des formatierten Arrays. Dateiname
            // trotzdem über WordPress-Bordmittel auflösen, statt die Zeile zu
            // überspringen.
            $filename = basename(get_attached_file((int) $file) ?: '');
        }
        if ($filename === '') {
            continue;
        }
        $label = trim((string) ($row['label'] ?? ''));
        $names[] = $label !== '' ? $label : $filename;
    }

    if (!empty($names)) {
        $parts[] = 'Verfügbare Dokumente zu diesem Produkt: ' . implode(', ', $names)
            . ' (Inhalt dieser Dokumente ist separat durchsuchbar, falls Datei-Indexierung aktiv ist).';
    }

    return $parts;
}, 10, 2);
