<?php
/**
 * Wunschlisten-Sharing: dauerhafter Token-Link zum Teilen einer Liste mit Dritten.
 *
 * Zwei grundverschiedene Strategien je nach Besucher-Typ:
 *
 *  - Eingeloggte Kunden: Der Token verweist nur auf die User-ID. Die
 *    eigentlichen Items werden bei JEDEM Aufruf live aus der ohnehin schon
 *    dauerhaften User-Meta-Wunschliste geladen (siehe class-storage.php).
 *    Kein Snapshot nötig, der Link zeigt daher immer den aktuellen Stand.
 *
 *  - Gäste: Die Wunschliste liegt nur in der (nicht dauerhaften)
 *    WooCommerce-Session. Beim Teilen wird ein JSON-Snapshot der
 *    aktuellen Liste in wp_mlw_shared_wishlists geschrieben. Der Token
 *    wird zusätzlich in der Session gemerkt (WC()->session), damit
 *    wiederholtes Klicken auf "Teilen" denselben Link liefert und nur
 *    den Snapshot-Inhalt aktualisiert, statt neue Zeilen anzuhäufen.
 *    Trade-off: legt der Gast NACH dem Teilen weitere Artikel auf die
 *    Liste, sieht der Empfänger die erst nach erneutem Teilen.
 *
 *  Gast-Snapshots werden nach GUEST_RETENTION_DAYS automatisch bereinigt
 *  (täglicher Cron). User-Token bleiben dauerhaft bestehen (ein Datensatz
 *  pro Kunde, der je geteilt hat - vernachlässigbare Datenmenge).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Wishlist_Share {

    const TABLE_SUFFIX          = 'mlw_shared_wishlists';
    const SESSION_TOKEN_KEY     = 'mlw_wishlist_share_token';
    const CRON_HOOK             = 'mlw_wishlist_share_cleanup';
    const GUEST_RETENTION_DAYS  = 90; // via mlw_wishlist_share_guest_retention_days filterbar

    public static function init(): void {
        add_shortcode( 'mlw_wishlist_share_link', [ __CLASS__, 'shortcode_share_button' ] );

        add_action( self::CRON_HOOK, [ __CLASS__, 'cleanup_expired_guest_snapshots' ] );
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * Aufgerufen beim Plugin-Aktivieren (siehe media-lab-woocommerce.php,
     * register_activation_hook - dort ergänzen, analog zu MLBKP_Logger::create_table()
     * bei media-lab-backup oder MLT_Redirects bei media-lab-seo).
     */
    public static function create_table(): void {
        global $wpdb;
        $table           = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token          VARCHAR(40)     NOT NULL,
            owner_type     VARCHAR(10)     NOT NULL,
            owner_id       BIGINT UNSIGNED NULL,
            items_snapshot LONGTEXT        NULL,
            created_at     DATETIME        NOT NULL,
            updated_at     DATETIME        NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY owner (owner_type, owner_id)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Analog zu MLT_Redirects::drop_tables() bei media-lab-seo - für
     * uninstall.php.
     */
    public static function drop_table(): void {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() );
    }

    // ── Token holen/erzeugen ──────────────────────────────────────────────────

    /**
     * Gibt den Share-Token des aktuellen Besuchers zurück, legt bei Bedarf
     * einen neuen Datensatz an. Bei Gästen wird der Snapshot bei jedem
     * Aufruf mit dem aktuellen Listeninhalt aktualisiert.
     */
    public static function get_or_create_token(): string {
        return is_user_logged_in() ? self::token_for_user() : self::token_for_guest();
    }

    private static function token_for_user(): string {
        global $wpdb;
        $user_id = get_current_user_id();

        $existing = $wpdb->get_var( $wpdb->prepare(
            'SELECT token FROM ' . self::table() . ' WHERE owner_type = %s AND owner_id = %d',
            'user', $user_id
        ) );
        if ( $existing ) return $existing;

        $token = self::generate_token();
        $now   = current_time( 'mysql' );
        $wpdb->insert( self::table(), [
            'token'          => $token,
            'owner_type'     => 'user',
            'owner_id'       => $user_id,
            'items_snapshot' => null, // Live-Load, siehe Klassen-Docblock
            'created_at'     => $now,
            'updated_at'     => $now,
        ] );

        return $token;
    }

    private static function token_for_guest(): string {
        global $wpdb;

        $items = wp_json_encode( MediaLab_Wishlist_Storage::get_items() );
        $now   = current_time( 'mysql' );

        $existing_token = ( function_exists( 'WC' ) && WC()->session )
            ? WC()->session->get( self::SESSION_TOKEN_KEY )
            : null;

        if ( $existing_token ) {
            $row_exists = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM ' . self::table() . ' WHERE token = %s AND owner_type = %s',
                $existing_token, 'guest'
            ) );
            if ( $row_exists ) {
                // Snapshot mit aktuellem Listeninhalt auffrischen.
                $wpdb->update(
                    self::table(),
                    [ 'items_snapshot' => $items, 'updated_at' => $now ],
                    [ 'token' => $existing_token ]
                );
                return $existing_token;
            }
        }

        $token = self::generate_token();
        $wpdb->insert( self::table(), [
            'token'          => $token,
            'owner_type'     => 'guest',
            'owner_id'       => null,
            'items_snapshot' => $items,
            'created_at'     => $now,
            'updated_at'     => $now,
        ] );

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set( self::SESSION_TOKEN_KEY, $token );
        }

        return $token;
    }

    private static function generate_token(): string {
        return wp_generate_password( 32, false, false ); // alnum, URL-sicher ohne Encoding
    }

    // ── Items anhand eines Tokens laden ──────────────────────────────────────

    /**
     * @return array|null  null = Token unbekannt/ungültig.
     */
    public static function get_items_by_token( string $token ): ?array {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT owner_type, owner_id, items_snapshot FROM ' . self::table() . ' WHERE token = %s',
            $token
        ), ARRAY_A );

        if ( ! $row ) return null;

        if ( $row['owner_type'] === 'user' ) {
            return MediaLab_Wishlist_Storage::get_items_for_user_id( (int) $row['owner_id'] );
        }

        $decoded = json_decode( (string) $row['items_snapshot'], true );
        return is_array( $decoded ) ? $decoded : [];
    }

    // ── Share-Button-Shortcode ────────────────────────────────────────────────

    /**
     * Verwendung: [mlw_wishlist_share_link] auf der Wunschlisten-Seite
     * (templates/wishlist/page.php) platzieren. Baut die Token-URL und
     * reicht sie an das bestehende medialab_share() aus agency-core durch.
     */
    public static function shortcode_share_button(): string {
        if ( ! function_exists( 'medialab_share' ) ) return ''; // agency-core nicht aktiv/zu alt

        $token     = self::get_or_create_token();
        $share_url = add_query_arg( 'mlw_share', $token, self::wishlist_page_url() );

        ob_start();
        medialab_share( [
            'url'   => $share_url,
            'title' => MediaLab_Inquiry_Settings::wording( 'wishlist_label' ),
        ] );
        return ob_get_clean();
    }

    private static function wishlist_page_url(): string {
        // Eigene, un-parametrisierte Basis-URL der aktuellen Seite (ohne
        // ein evtl. bereits vorhandenes ?mlw_share= aus der aktuellen
        // Anfrage mit reinzuziehen).
        global $wp;
        return home_url( $wp->request ?? '' );
    }

    // ── Cleanup ──────────────────────────────────────────────────────────────

    public static function cleanup_expired_guest_snapshots(): void {
        global $wpdb;

        $days = (int) apply_filters( 'mlw_wishlist_share_guest_retention_days', self::GUEST_RETENTION_DAYS );
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        $wpdb->query( $wpdb->prepare(
            'DELETE FROM ' . self::table() . ' WHERE owner_type = %s AND updated_at < %s',
            'guest', $cutoff
        ) );
    }
}

MediaLab_Wishlist_Share::init();