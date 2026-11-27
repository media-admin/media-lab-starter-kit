<?php
/**
 * Enqueue WooCommerce styles
 *
 * Lädt assets/dist/css/woocommerce.css aus dem aktiven Theme, falls die Datei
 * existiert. Die Standard-Themes des Starter Kits kompilieren die WooCommerce-
 * Styles in css/style.css, dort läuft dieser Block deshalb nicht.
 *
 * Abhängigkeit: Das Haupt-Stylesheet des Themes heißt je Projekt anders
 * (`custom-theme-style` im Kit, `{theme-slug}-style` nach dem Umbenennen).
 * Ein fest verdrahtetes Handle, das es nicht gibt, lässt WordPress das Stylesheet
 * stillschweigend weglassen. Das Handle wird deshalb zur Laufzeit ermittelt.
 *
 * Filter `medialab_wc_style_dependencies` (array): Abhängigkeiten überschreiben.
 */

if (!defined('ABSPATH')) exit;

/**
 * Handle des Theme-Hauptstylesheets bestimmen (nur bereits registrierte).
 *
 * @return string[]
 */
function medialab_wc_theme_style_dependencies(): array {
    $candidates = array_unique(array_filter(array(
        get_stylesheet() . '-style',
        get_template() . '-style',
        'custom-theme-style',
    )));

    foreach ($candidates as $handle) {
        if (wp_style_is($handle, 'registered')) {
            return array($handle);
        }
    }

    return array();
}

// Priorität 20: nach dem Theme (Standard 10), damit dessen Handle schon registriert ist.
add_action('wp_enqueue_scripts', function() {
    // Nur laden wenn WooCommerce Seite
    if (!is_woocommerce() && !is_cart() && !is_checkout() && !is_account_page()) return;

    // Custom WooCommerce styles (from theme if exists)
    $style_path = get_template_directory() . '/assets/dist/css/woocommerce.css';
    if (file_exists($style_path)) {
        $deps = apply_filters('medialab_wc_style_dependencies', medialab_wc_theme_style_dependencies());

        wp_enqueue_style(
            'media-lab-woocommerce',
            get_template_directory_uri() . '/assets/dist/css/woocommerce.css',
            is_array($deps) ? $deps : array(),
            filemtime($style_path)
        );
    }
}, 20);
