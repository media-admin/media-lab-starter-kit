<?php
/**
 * Plugin Name:       Media Lab AI Agent
 * Plugin URI:        https://media-lab.at
 * Description:       Datenschutzkonformer AI-Chat-Assistent für mehrsprachige WordPress-Sites, mit austauschbarem Anbieter (Anthropic, OpenAI, ...).
 * Version:           1.6.1
 * Requires PHP:      8.1
 * Author:            Media Lab Tritremmel GmbH
 * Text Domain:        media-lab-ai-agent
 *
 * @package MediaLab_AI_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MLT_AI_AGENT_VERSION', '1.6.1');
define('MLT_AI_AGENT_PATH', plugin_dir_path(__FILE__));
define('MLT_AI_AGENT_URL', plugin_dir_url(__FILE__));

/**
 * Includes. Reihenfolge relevant: Crypto/Budget vor den Providern (werden dort
 * genutzt), Provider-Interface vor den konkreten Implementierungen.
 */
require_once MLT_AI_AGENT_PATH . 'inc/class-ai-agent-crypto.php';
require_once MLT_AI_AGENT_PATH . 'inc/class-ai-agent-budget.php';
require_once MLT_AI_AGENT_PATH . 'inc/class-ai-agent-install.php';
require_once MLT_AI_AGENT_PATH . 'inc/providers/interface-ai-provider.php';
require_once MLT_AI_AGENT_PATH . 'inc/providers/class-provider-anthropic.php';
require_once MLT_AI_AGENT_PATH . 'inc/providers/class-provider-openai.php';
require_once MLT_AI_AGENT_PATH . 'inc/providers/class-provider-registry.php';
require_once MLT_AI_AGENT_PATH . 'inc/class-ai-agent-rest.php';
require_once MLT_AI_AGENT_PATH . 'inc/class-ai-agent-dashboard.php';

// RAG-Modul: komplett optional, wird aber immer geladen (Zustand steuert
// mlt_ai_rag_enabled) — so lässt es sich zur Laufzeit ohne Deploy zu-/abschalten.
require_once MLT_AI_AGENT_PATH . 'inc/rag/class-ai-agent-rag-install.php';
require_once MLT_AI_AGENT_PATH . 'inc/rag/class-ai-agent-embeddings.php';
require_once MLT_AI_AGENT_PATH . 'inc/rag/class-ai-agent-file-extractor.php';

// Vendor-Autoloader (FPDI/FPDF, für PDF-Seiten-Splitting) — lokal gebaut und
// committed statt per Server-Composer installiert (siehe vendor/autoload.php).
// Guarded: falls der Ordner mal fehlt, bricht das Plugin nicht komplett ab,
// PDF-Splitting fällt dann einfach auf "keine Aufteilung" zurück (siehe
// MLT_AI_Pdf_Splitter::library_available()).
if (file_exists(MLT_AI_AGENT_PATH . 'vendor/autoload.php')) {
    require_once MLT_AI_AGENT_PATH . 'vendor/autoload.php';
}
require_once MLT_AI_AGENT_PATH . 'inc/rag/class-ai-agent-pdf-splitter.php';

require_once MLT_AI_AGENT_PATH . 'inc/rag/class-ai-agent-retriever.php';
require_once MLT_AI_AGENT_PATH . 'inc/rag/class-ai-agent-indexer.php';
require_once MLT_AI_AGENT_PATH . 'inc/rag/class-ai-agent-product-documents.php';

register_activation_hook(__FILE__, [MLT_AI_Agent_Install::class, 'activate']);
register_activation_hook(__FILE__, [MLT_AI_Rag_Install::class, 'activate']);
register_deactivation_hook(__FILE__, [MLT_AI_Agent_Install::class, 'deactivate']);

// Deployment erfolgt per Full-Directory-Replace via SFTP ohne erneuten
// Activation-Hook — daher zusätzlich ein Versions-Check bei jedem Laden.
add_action('plugins_loaded', [MLT_AI_Agent_Install::class, 'maybe_upgrade']);
add_action('plugins_loaded', [MLT_AI_Rag_Install::class, 'maybe_upgrade']);

/**
 * ACF Options-Page registrieren. Eigene Seite (nicht Teil des Agency-Core-Menüs),
 * da das Plugin eigenständig aktivierbar/deaktivierbar sein soll.
 *
 * acf_add_options_page() existiert nur in ACF PRO (nicht in ACF Free) — falls
 * die Funktion fehlt, brechen wir sichtbar mit einem Admin-Hinweis ab, statt
 * die Seite still wegzulassen (das hätte sonst z.B. das Kosten-Dashboard als
 * verwaistes Submenu hinterlassen, das auf die Startseite umleitet).
 */
add_action('acf/init', function () {
    if (!function_exists('acf_add_options_page')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Media Lab AI Agent:</strong> '
                . 'Dieses Plugin benötigt Advanced Custom Fields PRO (die kostenlose Version reicht nicht, '
                . 'da Options-Pages ein PRO-Feature sind). Bitte ACF PRO installieren/aktivieren.</p></div>';
        });
        return;
    }

    acf_add_options_page([
        'page_title' => 'AI Agent',
        'menu_title' => 'AI Agent',
        'menu_slug'  => 'acf-options-ai-agent',
        'capability' => 'manage_options',
        'icon_url'   => 'dashicons-format-chat',
        'position'   => 80,
    ]);
});

/**
 * Zentraler Check, den andere Klassen (Dashboard) nutzen, um zu wissen, ob die
 * Options-Page tatsächlich existiert, bevor sie sich als Submenu daran hängen.
 */
function mlt_ai_options_page_available(): bool {
    return function_exists('acf_add_options_page');
}

/**
 * Lädt die ACF Field-Group aus der JSON-Datei, analog zum Sync-Mechanismus
 * der anderen media-lab-* Plugins (acf-json Ordner-Konvention).
 */
add_filter('acf/settings/load_json', function (array $paths): array {
    $paths[] = MLT_AI_AGENT_PATH . 'acf';
    return $paths;
});

/**
 * Frontend-Widget einbinden. Nur wenn aktiv, um unnötige Requests auf
 * Client-Sites zu vermeiden, die das Plugin installiert, aber nicht
 * eingeschaltet haben.
 */
add_action('wp_enqueue_scripts', function () {
    if (!get_field('mlt_ai_enabled', 'option')) {
        return;
    }

    wp_enqueue_style(
        'mlt-ai-agent-widget',
        MLT_AI_AGENT_URL . 'assets/css/ai-agent-widget.css',
        [],
        MLT_AI_AGENT_VERSION
    );

    wp_enqueue_script(
        'mlt-ai-agent-widget',
        MLT_AI_AGENT_URL . 'assets/js/ai-agent-widget.js',
        [],
        MLT_AI_AGENT_VERSION,
        true
    );

    wp_localize_script('mlt-ai-agent-widget', 'mltAiConfig', [
        'maxMessages' => (int) (get_field('mlt_ai_max_messages_per_session', 'option') ?: 15),
    ]);
});

/**
 * Rendert den Widget-Container. Aufruf im Theme via:
 *   <?php if (function_exists('mlt_ai_render_widget')) { mlt_ai_render_widget(); } ?>
 * oder als eigener Shortcode [mlt_ai_widget], falls der Client-Theme keinen
 * direkten Funktionsaufruf einbauen soll.
 */
function mlt_ai_render_widget(): void {
    if (!get_field('mlt_ai_enabled', 'option')) {
        return;
    }

    $lang = function_exists('pll_current_language') ? pll_current_language() : 'de';
    $lang = $lang ?: 'de';

    /**
     * Consent-Cookie-Name/-Wert sind filterbar, damit sich das Widget an ein
     * bereits vorhandenes Consent-System auf der Client-Site (z.B. das
     * Starter-Kit-eigene cookie-notice.js, oder ein Drittanbieter-CMP)
     * ankoppeln lässt, ohne den Core anzufassen:
     *
     * add_filter('mlt_ai_consent_cookie_name', fn() => 'mein_cookie_name');
     */
    $consent_cookie_name = apply_filters('mlt_ai_consent_cookie_name', 'mlt_consent_ai_agent');
    $consent_cookie_value = apply_filters('mlt_ai_consent_cookie_value', 'granted');

    printf(
        '<div data-mlt-ai-widget data-lang="%s" data-endpoint="%s" data-nonce="%s" data-consent-cookie="%s" data-consent-value="%s"></div>',
        esc_attr($lang),
        esc_url(rest_url('medialab/v1/ai-chat')),
        esc_attr(wp_create_nonce('wp_rest')),
        esc_attr($consent_cookie_name),
        esc_attr($consent_cookie_value)
    );
}
add_shortcode('mlt_ai_widget', function () {
    ob_start();
    mlt_ai_render_widget();
    return ob_get_clean();
});

/**
 * Manueller Reindex-Trigger im Werkzeuge-Menü — startet den Batch-Reindex
 * aus class-ai-agent-indexer.php (20 Posts pro Cron-Durchlauf). Zweiter,
 * unabhängiger Button für Dateien (PDF/PPTX/DOCX), da diese über einen
 * eigenen Batch-Prozess mit kleinerer Batch-Größe laufen (Textextraktion
 * braucht mehr Rechenzeit als reine Content-Posts).
 */
add_action('admin_menu', function () {
    add_management_page(
        'AI Agent Reindex',
        'AI Agent Reindex',
        'manage_options',
        'mlt-ai-reindex',
        function () {
            if (isset($_POST['mlt_ai_start_reindex']) && check_admin_referer('mlt_ai_reindex')) {
                do_action('mlt_ai_rag_bulk_reindex', 0);
                echo '<div class="notice notice-success"><p>Reindexierung gestartet, läuft im Hintergrund per WP-Cron.</p></div>';
            }
            if (isset($_POST['mlt_ai_start_file_reindex']) && check_admin_referer('mlt_ai_file_reindex')) {
                do_action('mlt_ai_rag_bulk_reindex_files', 0);
                echo '<div class="notice notice-success"><p>Datei-Reindexierung gestartet, läuft im Hintergrund per WP-Cron.</p></div>';
            }

            $status = get_option('mlt_ai_rag_reindex_status', 'noch nicht gestartet');
            $file_status = get_option('mlt_ai_rag_file_reindex_status', 'noch nicht gestartet');

            echo '<div class="wrap"><h1>AI Agent — Website-Inhalte neu indexieren</h1>';

            echo '<h2>Beiträge, Seiten, Produkte</h2>';
            echo '<p>Status: ' . esc_html($status) . '</p>';
            echo '<form method="post">';
            wp_nonce_field('mlt_ai_reindex');
            submit_button('Neuindexierung starten', 'primary', 'mlt_ai_start_reindex');
            echo '</form>';

            echo '<h2 style="margin-top:2em;">Dateien (PDF, PowerPoint, Word)</h2>';
            echo '<p>Status: ' . esc_html($file_status) . '</p>';
            echo '<form method="post">';
            wp_nonce_field('mlt_ai_file_reindex');
            submit_button('Dateien neu indexieren', 'secondary', 'mlt_ai_start_file_reindex');
            echo '</form></div>';
        }
    );
});
