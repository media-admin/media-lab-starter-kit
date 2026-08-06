<?php
/**
 * Custom Post Type für alle Anfragen (Cart-Anfrage, Konfigurator-Anfrage, Wunschliste).
 *
 * Gemeinsamer Datenspeicher für die Inquiry_Engine – siehe class-inquiry-engine.php.
 * Quelle wird pro Anfrage in mlw_inquiry_source gespeichert ('cart' | 'configurator' | 'wishlist').
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Inquiry_CPT {

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', [ __CLASS__, 'register_statuses' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'pre_get_posts', [ __CLASS__, 'fix_default_admin_list_query' ] );

        // Backend-Liste: Quelle + Status als eigene Spalten
        add_filter( 'manage_mlw_inquiry_posts_columns', [ __CLASS__, 'admin_columns' ] );
        add_action( 'manage_mlw_inquiry_posts_custom_column', [ __CLASS__, 'admin_column_content' ], 10, 2 );
        add_filter( 'views_edit-mlw_inquiry', [ __CLASS__, 'status_views' ] );
    }

    /**
     * WordPress zeigt in der Standard-„Alle"-Ansicht von edit.php Custom Post
     * Types, die AUSSCHLIESSLICH eigene Custom-Post-Stati nutzen (nie
     * 'publish'), nicht zuverlässig an - obwohl die Status-Zähler oben
     * korrekt sind. Ohne explizites post_status-Query-Arg greift WP_Query
     * intern nicht auf alle registrierten Custom-Stati zu. Wir setzen den
     * Status-Filter daher explizit, wenn kein anderer aktiv ist.
     */
    public static function fix_default_admin_list_query( WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'post_type' ) !== 'mlw_inquiry' ) return;
        if ( ! empty( $_GET['post_status'] ) ) return; // explizites Filter (z.B. "Offen") nicht überschreiben

        $query->set( 'post_status', [ 'mlw-open', 'mlw-in-progress', 'mlw-done', 'mlw-archived' ] );
    }

    // ── CPT-Registrierung ───────────────────────────────────────────────────

    public static function register(): void {
        register_post_type( 'mlw_inquiry', [
            'labels' => [
                'name'               => __( 'Anfragen', 'media-lab-woocommerce' ),
                'singular_name'      => __( 'Anfrage', 'media-lab-woocommerce' ),
                'add_new'            => __( 'Hinzufügen', 'media-lab-woocommerce' ),
                'add_new_item'       => __( 'Neue Anfrage hinzufügen', 'media-lab-woocommerce' ),
                'edit_item'          => __( 'Anfrage bearbeiten', 'media-lab-woocommerce' ),
                'view_item'          => __( 'Anfrage ansehen', 'media-lab-woocommerce' ),
                'search_items'       => __( 'Anfragen durchsuchen', 'media-lab-woocommerce' ),
                'not_found'          => __( 'Keine Anfragen gefunden', 'media-lab-woocommerce' ),
                'not_found_in_trash' => __( 'Keine Anfragen im Papierkorb', 'media-lab-woocommerce' ),
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false, // eigenes Menü, siehe add_admin_menu()
            'supports'        => [ 'title' ],
            'has_archive'     => false,
            'rewrite'         => false,
            'capability_type' => 'post',
            'menu_icon'       => 'dashicons-heart',
        ] );
    }

    public static function register_statuses(): void {
        register_post_status( 'mlw-open', [
            'label'                    => __( 'Offen', 'media-lab-woocommerce' ),
            'public'                   => false,
            'show_in_admin_all_list'   => true,
            'show_in_admin_status_list'=> true,
            'label_count'              => _n_noop( 'Offen <span class="count">(%s)</span>', 'Offen <span class="count">(%s)</span>', 'media-lab-woocommerce' ),
        ] );
        register_post_status( 'mlw-in-progress', [
            'label'                    => __( 'In Bearbeitung', 'media-lab-woocommerce' ),
            'public'                   => false,
            'show_in_admin_all_list'   => true,
            'show_in_admin_status_list'=> true,
            'label_count'              => _n_noop( 'In Bearbeitung <span class="count">(%s)</span>', 'In Bearbeitung <span class="count">(%s)</span>', 'media-lab-woocommerce' ),
        ] );
        register_post_status( 'mlw-done', [
            'label'                    => __( 'Erledigt', 'media-lab-woocommerce' ),
            'public'                   => false,
            'show_in_admin_all_list'   => true,
            'show_in_admin_status_list'=> true,
            'label_count'              => _n_noop( 'Erledigt <span class="count">(%s)</span>', 'Erledigt <span class="count">(%s)</span>', 'media-lab-woocommerce' ),
        ] );
        register_post_status( 'mlw-archived', [
            'label'                    => __( 'Archiviert', 'media-lab-woocommerce' ),
            'public'                   => false,
            'show_in_admin_all_list'   => true,
            'show_in_admin_status_list'=> true,
            'label_count'              => _n_noop( 'Archiviert <span class="count">(%s)</span>', 'Archiviert <span class="count">(%s)</span>', 'media-lab-woocommerce' ),
        ] );
    }

    // ── Backend-Menü ─────────────────────────────────────────────────────────

    public static function add_admin_menu(): void {
        add_menu_page(
            __( 'Anfragen', 'media-lab-woocommerce' ),
            __( 'Anfragen', 'media-lab-woocommerce' ),
            'manage_woocommerce',
            'edit.php?post_type=mlw_inquiry',
            '',
            'dashicons-heart',
            56
        );
    }

    // ── Backend-Listenspalten ────────────────────────────────────────────────

    public static function admin_columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['mlw_source']  = __( 'Quelle', 'media-lab-woocommerce' );
                $new['mlw_channels']= __( 'Kanäle', 'media-lab-woocommerce' );
                $new['mlw_status']  = __( 'Status', 'media-lab-woocommerce' );
            }
        }
        return $new;
    }

    public static function admin_column_content( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'mlw_source':
                $source = get_post_meta( $post_id, 'mlw_inquiry_source', true );
                $labels = [
                    'cart'         => __( 'Warenkorb', 'media-lab-woocommerce' ),
                    'configurator' => __( 'Konfigurator', 'media-lab-woocommerce' ),
                    'wishlist'     => __( 'Wunschliste', 'media-lab-woocommerce' ),
                ];
                echo esc_html( $labels[ $source ] ?? $source ?: '–' );
                break;

            case 'mlw_channels':
                $channels = get_post_meta( $post_id, 'mlw_inquiry_channels_sent', true );
                echo esc_html( is_array( $channels ) ? implode( ', ', $channels ) : '–' );
                break;

            case 'mlw_status':
                $status  = get_post_status( $post_id );
                $obj     = get_post_status_object( $status );
                echo esc_html( $obj ? $obj->label : $status );
                break;
        }
    }

    public static function status_views( array $views ): array {
        // Standard-Views von WP funktionieren mit Custom-Post-Status nicht automatisch;
        // hier könnten bei Bedarf eigene Status-Filter-Links ergänzt werden.
        return $views;
    }
}

MediaLab_Inquiry_CPT::init();
