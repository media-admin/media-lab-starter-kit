<?php
/**
 * Plugin Name: Media Lab Backup
 * Plugin URI:  https://media-lab.at
 * Description: Automatische WordPress-Backups (Datenbank + Dateien) zur Hetzner Storage Box via SFTP. Unterstützt manuelle und geplante Backups mit konfigurierbarer Aufbewahrungszeit.
 * Version:     2.0.12
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:      Media Lab Tritremmel GmbH
 * Author URI:  https://media-lab.at
 * Text Domain: media-lab-backup
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

// Doppeltes Laden verhindern (passiert beim ZIP-Upload im WP-Admin)
if ( defined( 'MLBKP_VERSION' ) ) {
    return;
}

// ─── Plugin-Konstanten ───────────────────────────────────────────────────────
define( 'MLBKP_VERSION',       '2.0.12' );
define( 'MLBKP_PLUGIN_FILE',   __FILE__ );
define( 'MLBKP_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'MLBKP_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'MLBKP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ─── Composer Autoload ───────────────────────────────────────────────────────
if ( file_exists( MLBKP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once MLBKP_PLUGIN_DIR . 'vendor/autoload.php';
}

// ─── Klassen laden ───────────────────────────────────────────────────────────
if ( ! class_exists( 'MLBKP_Logger' ) )          require_once MLBKP_PLUGIN_DIR . 'includes/class-mlb-logger.php';
if ( ! class_exists( 'MLBKP_SFTP' ) )            require_once MLBKP_PLUGIN_DIR . 'includes/class-mlb-sftp.php';
if ( ! class_exists( 'MLBKP_Database_Backup' ) ) require_once MLBKP_PLUGIN_DIR . 'includes/class-mlb-database-backup.php';
if ( ! class_exists( 'MLBKP_File_Backup' ) )     require_once MLBKP_PLUGIN_DIR . 'includes/class-mlb-file-backup.php';
if ( ! class_exists( 'MLBKP_Backup_Runner' ) )   require_once MLBKP_PLUGIN_DIR . 'includes/class-mlb-backup-runner.php';
if ( ! class_exists( 'MLBKP_Session' ) )         require_once MLBKP_PLUGIN_DIR . 'includes/class-mlbkp-session.php';
if ( ! class_exists( 'MLBKP_Chunk_Runner' ) )    require_once MLBKP_PLUGIN_DIR . 'includes/class-mlbkp-chunk-runner.php';
if ( ! class_exists( 'MLBKP_Scheduler' ) )       require_once MLBKP_PLUGIN_DIR . 'includes/class-mlb-scheduler.php';
if ( ! class_exists( 'MLBKP_Admin' ) )           require_once MLBKP_PLUGIN_DIR . 'includes/class-mlb-admin.php';

// ─── WP-CLI ──────────────────────────────────────────────────────────────────
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once MLBKP_PLUGIN_DIR . 'includes/class-mlbkp-cli.php';
    WP_CLI::add_command( 'mlbkp', 'MLBKP_CLI' );
}

// ─── WooCommerce-Kompatibilität ───────────────────────────────────────────────
// Deklariert HPOS-Kompatibilität — Media Lab Backup berührt keine WC-Orders.
add_action( 'before_woocommerce_init', static function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            MLBKP_PLUGIN_FILE,
            true
        );
    }
} );

// ─── Plugin initialisieren ───────────────────────────────────────────────────
add_action( 'plugins_loaded', static function () {
    MLBKP_Admin::init();
    MLBKP_Scheduler::init();
} );

// ─── Activation / Deactivation ───────────────────────────────────────────────
register_activation_hook( __FILE__, static function () {
    MLBKP_Logger::create_table();
    MLBKP_Scheduler::activate();
} );

register_deactivation_hook( __FILE__, static function () {
    MLBKP_Scheduler::deactivate();
} );
