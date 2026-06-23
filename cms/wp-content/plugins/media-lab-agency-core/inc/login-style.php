<?php
/**
 * WP Login – Theme-Style
 *
 * Lädt das Theme-Stylesheet auf der Login-Seite, damit CSS Custom Properties
 * (--color-primary etc.) verfügbar sind. Zusätzlich wird login-styles.css
 * mit den Button-Overrides geladen.
 *
 * Eingebunden in: media-lab-agency-core.php via
 *   require_once MEDIALAB_CORE_PATH . 'inc/login-style.php';
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theme-CSS + Plugin-Overrides auf der Login-Seite enqueue'n.
 */
function medialab_login_enqueue_styles(): void {
	// Theme-Haupt-CSS (enthält die CSS Custom Properties)
	$theme_css = get_stylesheet_directory_uri() . '/assets/dist/css/style.css';

	// Prüfen ob die Datei existiert (Vite-Build-Pfad)
	$theme_css_path = get_stylesheet_directory() . '/assets/dist/css/style.css';

	if ( file_exists( $theme_css_path ) ) {
		wp_enqueue_style(
			'medialab-theme-vars',
			$theme_css,
			array(),
			filemtime( $theme_css_path )
		);
	}

	// Login-Overrides
	wp_enqueue_style(
		'medialab-login-style',
		MEDIALAB_CORE_URL . 'assets/css/login-styles.css',
		array(),
		MEDIALAB_CORE_VERSION
	);
}
add_action( 'login_enqueue_scripts', 'medialab_login_enqueue_styles' );

/**
 * Login-Logo URL → zur Startseite
 */
add_filter( 'login_headerurl', fn() => home_url( '/' ) );

/**
 * Login-Logo Title → Seitenname
 */
add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );
