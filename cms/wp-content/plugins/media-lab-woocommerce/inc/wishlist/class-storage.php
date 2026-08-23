<?php
/**
 * Wunschlisten-Datenhaltung.
 *
 * WICHTIG: Nutzt NICHT WC()->cart - eigene, unabhängige Datenhaltung, damit
 * die Wunschliste auch bei komplett deaktiviertem Checkout/Cart (Catalog Mode)
 * funktioniert und nicht mit einem parallel genutzten echten Warenkorb kollidiert.
 *
 * Speicherort:
 *  - Gast:        WC()->session (kein eigener PHP-Session-Handler nötig, da
 *                  WooCommerce für dieses Plugin ohnehin Voraussetzung ist -
 *                  siehe media-lab-woocommerce.php, Gate auf 'plugins_loaded')
 *  - Eingeloggt:   User-Meta (geräteübergreifend persistent)
 *
 * Item-Schema (siehe auch class-inquiry-engine.php):
 *   [
 *       'item_id'         => string  Eindeutige ID innerhalb der Wunschliste.
 *       'product_id'      => int,
 *       'quantity'        => int,
 *       'config'          => array|null   Rohe Konfigurator-Antworten.
 *       'config_display'  => array|null   Label => lesbarer Wert (fürs Anzeigen/Mail).
 *       'price_breakdown' => array|null   Wie in class-price-calculator.php.
 *       'attachments'     => int[]        Attachment-IDs (Konfigurator-Datei-Uploads).
 *       'added_at'        => int          Timestamp.
 *   ]
 *
 * Item-Identität: Ein "einfaches" Produkt ohne Konfiguration bekommt die
 * item_id "product_{$product_id}" - mehrfaches Hinzufügen erhöht nur die
 * Menge. Ein konfiguriertes Produkt bekommt eine Hash-ID aus Produkt + Konfiguration,
 * damit unterschiedliche Konfigurationen desselben Produkts als getrennte
 * Wunschlisten-Einträge geführt werden.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Wishlist_Storage {

    const SESSION_KEY  = 'mlw_wishlist';
    const USER_META_KEY = 'mlw_wishlist_items';

    public static function init(): void {
        // Beim Login: Gast-Wunschliste (Session) in die persistente User-Meta-Wunschliste übernehmen.
        add_action( 'wp_login', [ __CLASS__, 'merge_session_into_user_on_login' ], 10, 2 );
    }

    // ── Öffentliche API ──────────────────────────────────────────────────────

    public static function get_items(): array {
        $items = is_user_logged_in() ? self::get_user_items() : self::get_session_items();
        return is_array( $items ) ? array_values( $items ) : [];
    }

    /**
     * Wie get_user_items(), aber öffentlich nutzbar für einen beliebigen
     * User statt nur den aktuell eingeloggten - gebraucht für die Token-
     * Sharing-Ansicht (siehe MediaLab_Wishlist_Share::get_items_by_token()).
     */
    public static function get_items_for_user_id( int $user_id ): array {
        $items = get_user_meta( $user_id, self::USER_META_KEY, true );
        return is_array( $items ) ? $items : [];
    }


    /**
     * Anzahl der Positionen (Zeilen) in der Wunschliste, NICHT die Summe
     * aller Mengen - ein Eintrag mit Menge 90 zählt hier als "1", nicht "90".
     */
    public static function count(): int {
        return count( self::get_items() );
    }

    /**
     * Fügt ein Produkt hinzu (oder erhöht die Menge, falls identisch bereits vorhanden).
     *
     * @param array $data [ 'product_id' => int, 'quantity' => int, 'config' => array|null, 'config_display' => array|null, 'price_breakdown' => array|null, 'attachments' => int[] ]
     * @return array|WP_Error Aktualisierte Item-Liste oder Fehler.
     */
    public static function add( array $data ) {
        $product_id = (int) ( $data['product_id'] ?? 0 );
        $product    = $product_id ? wc_get_product( $product_id ) : null;
        if ( ! $product ) {
            return new WP_Error( 'mlw_invalid_product', __( 'Ungültiges Produkt.', 'media-lab-woocommerce' ) );
        }

        $quantity = max( 1, (int) ( $data['quantity'] ?? 1 ) );
        $config   = is_array( $data['config'] ?? null ) ? $data['config'] : null;

        $item_id = $config
            ? 'cfg_' . substr( md5( $product_id . wp_json_encode( $config ) ), 0, 20 )
            : 'product_' . $product_id;

        $items = self::get_items();

        foreach ( $items as &$existing ) {
            if ( $existing['item_id'] === $item_id ) {
                $existing['quantity'] = max( 1, (int) $existing['quantity'] + $quantity );
                self::persist( $items );
                return $items;
            }
        }
        unset( $existing );

        $items[] = [
            'item_id'         => $item_id,
            'product_id'      => $product_id,
            'quantity'        => $quantity,
            'config'          => $config,
            'config_display'  => is_array( $data['config_display'] ?? null ) ? $data['config_display'] : null,
            'price_breakdown' => is_array( $data['price_breakdown'] ?? null ) ? $data['price_breakdown'] : null,
            'attachments'     => array_map( 'intval', $data['attachments'] ?? [] ),
            'added_at'        => time(),
        ];

        self::persist( $items );
        return $items;
    }

    public static function remove( string $item_id ): array {
        $items = array_values( array_filter( self::get_items(), fn( $i ) => $i['item_id'] !== $item_id ) );
        self::persist( $items );
        return $items;
    }

    public static function update_quantity( string $item_id, int $quantity ): array {
        $items = self::get_items();
        if ( $quantity < 1 ) {
            return self::remove( $item_id );
        }
        foreach ( $items as &$item ) {
            if ( $item['item_id'] === $item_id ) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        unset( $item );
        self::persist( $items );
        return $items;
    }

    public static function clear(): void {
        self::persist( [] );
    }

    /**
     * Items angereichert mit aktuellen Produktdaten (Name, Bild, Link) fürs Frontend-Rendering.
     * Enthält zusätzlich 'unit_price' (Einzelpreis) und 'line_total' (Menge × Einzelpreis),
     * damit Template und JS-Nachrendern nach Ajax-Änderungen dieselbe Zahl anzeigen,
     * statt die Berechnung an zwei Stellen zu duplizieren.
     */
    /**
     * @param array|null $items  Optional: explizite Item-Liste statt der
     *                           des aktuellen Besuchers (z.B. für die
     *                           öffentliche Token-Ansicht in
     *                           MediaLab_Wishlist_Share::render_shared_view()).
     */
    public static function get_items_for_display( ?array $items = null ): array {
        $out = [];
        foreach ( ( $items ?? self::get_items() ) as $item ) {
            $product = wc_get_product( $item['product_id'] );

            // Einzelpreis: bei konfigurierten Produkten aus 'unit_price' der
            // Preisaufschlüsselung (siehe class-price-calculator.php - das ist
            // der ECHTE Preis pro Stück, bereits korrekt netto/brutto gewählt).
            // WICHTIG: NICHT 'total' verwenden - das ist der Gesamtpreis für
            // die GESAMTE Konfigurations-Menge (z.B. 90 Stück bei einer
            // Größen-Matrix), kein Einzelpreis. Das führte bei Produkten mit
            // Mengen-/Größen-Matrix-Step zu einer doppelten Multiplikation
            // (line_total = bereits-multipliziertes 'total' × quantity nochmal).
            $unit_price = null;
            if ( isset( $item['price_breakdown']['unit_price'] ) && $item['price_breakdown']['unit_price'] !== null ) {
                $unit_price = (float) $item['price_breakdown']['unit_price'];
            } elseif ( $product ) {
                $unit_price = (float) $product->get_price();
            }

            $quantity   = (int) ( $item['quantity'] ?? 1 );
            $line_total = $unit_price !== null ? $unit_price * $quantity : null;

            $out[] = array_merge( $item, [
                'name'          => $product ? $product->get_name() : __( 'Produkt nicht mehr verfügbar', 'media-lab-woocommerce' ),
                'sku'           => $product ? $product->get_sku() : '',
                'permalink'     => $product ? get_permalink( $product->get_id() ) : '',
                'image'         => $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '',
                'exists'        => (bool) $product,
                'unit_price'    => $unit_price,
                // Fertig formatiertes HTML (wc_price()) zusätzlich zum rohen
                // Float mitliefern - wishlist.js übernimmt das direkt statt
                // selbst zu formatieren, damit die Darstellung nach Ajax-
                // Updates (Menge ändern, entfernen) exakt der des initialen
                // PHP-Renders entspricht (Währungssymbol-Position, Dezimal-
                // trennzeichen etc. laut WooCommerce-Einstellungen).
                'unit_price_html' => $unit_price !== null ? wc_price( $unit_price ) : '',
                'line_total'    => $line_total,
                'line_total_html' => $line_total !== null ? wc_price( $line_total ) : '',
                'attachment_urls' => array_map( fn( $id ) => [ 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'filename' => basename( (string) wp_get_attachment_url( $id ) ) ], $item['attachments'] ?? [] ),
            ] );
        }
        return $out;
    }

    /**
     * Gesamtsumme über alle Wunschlisten-Items (Summe der Zeilensummen).
     * Items ohne ermittelbaren Preis (z.B. gelöschtes Produkt) fließen nicht ein.
     */
    public static function get_grand_total(): float {
        $total = 0.0;
        foreach ( self::get_items_for_display() as $item ) {
            if ( $item['line_total'] !== null ) $total += $item['line_total'];
        }
        return $total;
    }

    // ── Zuletzt genutzte Kontaktdaten ────────────────────────────────────────
    // Damit das Absende-Formular auf der Wunschlisten-Seite vorausgefüllt
    // werden kann, wenn der Kunde die Kontaktdaten bereits im Konfigurator-
    // Wizard eingegeben hat (bevor er "Zur Wunschliste hinzufügen" klickte).

    const CONTACT_SESSION_KEY  = 'mlw_wishlist_contact';
    const CONTACT_USER_META_KEY = 'mlw_wishlist_last_contact';

    public static function save_last_contact( array $contact ): void {
        // Nur die relevanten Felder übernehmen, kein beliebiges Array durchreichen.
        $clean = [
            'name'    => sanitize_text_field( $contact['name']    ?? '' ),
            'email'   => sanitize_email( $contact['email']        ?? '' ),
            'phone'   => sanitize_text_field( $contact['phone']   ?? '' ),
            'company' => sanitize_text_field( $contact['company'] ?? '' ),
            'message' => sanitize_textarea_field( $contact['message'] ?? '' ),
        ];
        if ( ! $clean['name'] && ! $clean['email'] ) return; // nichts Sinnvolles zu speichern

        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), self::CONTACT_USER_META_KEY, $clean );
        } elseif ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set( self::CONTACT_SESSION_KEY, $clean );
        }
    }

    public static function get_last_contact(): array {
        if ( is_user_logged_in() ) {
            $contact = get_user_meta( get_current_user_id(), self::CONTACT_USER_META_KEY, true );
        } elseif ( function_exists( 'WC' ) && WC()->session ) {
            $contact = WC()->session->get( self::CONTACT_SESSION_KEY );
        } else {
            $contact = null;
        }
        return is_array( $contact ) ? $contact : [ 'name' => '', 'email' => '', 'phone' => '', 'company' => '', 'message' => '' ];
    }

    // ── Speicher-Backends ────────────────────────────────────────────────────

    private static function persist( array $items ): void {
        if ( is_user_logged_in() ) {
            update_user_meta( get_current_user_id(), self::USER_META_KEY, $items );
        } else {
            self::session_set( $items );
        }
    }

    private static function get_user_items(): array {
        $items = get_user_meta( get_current_user_id(), self::USER_META_KEY, true );
        return is_array( $items ) ? $items : [];
    }

    private static function get_session_items(): array {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) return [];
        $items = WC()->session->get( self::SESSION_KEY );
        return is_array( $items ) ? $items : [];
    }

    private static function session_set( array $items ): void {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) return;
        WC()->session->set( self::SESSION_KEY, $items );

        // WICHTIG: WooCommerce setzt seinen Session-Cookie normalerweise über
        // einen Hook, der bei einem normalen Seitenaufruf feuert - bei einem
        // reinen Ajax-Request (admin-ajax.php, wie hier) greift dieser Hook
        // nicht zuverlässig. Ohne diesen expliziten Aufruf bekommt der Gast
        // nie einen Session-Cookie, wodurch die Wunschliste beim nächsten
        // Seitenaufruf (z.B. nach einem Redirect) als leer erscheint, obwohl
        // die Daten serverseitig korrekt gespeichert wurden.
        if ( method_exists( WC()->session, 'set_customer_session_cookie' ) && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }

    // ── Login-Merge ──────────────────────────────────────────────────────────

    /**
     * Übernimmt beim Login eine bestehende Gast-Wunschliste (Session) in die
     * persistente User-Meta-Wunschliste des jetzt eingeloggten Users. Gleiche
     * item_ids werden addiert, statt dupliziert.
     */
    public static function merge_session_into_user_on_login( string $user_login, WP_User $user ): void {
        $session_items = self::get_session_items();
        if ( empty( $session_items ) ) return;

        $user_items = get_user_meta( $user->ID, self::USER_META_KEY, true );
        $user_items = is_array( $user_items ) ? $user_items : [];

        foreach ( $session_items as $s_item ) {
            $found = false;
            foreach ( $user_items as &$u_item ) {
                if ( $u_item['item_id'] === $s_item['item_id'] ) {
                    $u_item['quantity'] = (int) $u_item['quantity'] + (int) $s_item['quantity'];
                    $found = true;
                    break;
                }
            }
            unset( $u_item );
            if ( ! $found ) $user_items[] = $s_item;
        }

        update_user_meta( $user->ID, self::USER_META_KEY, $user_items );
        self::session_set( [] ); // Gast-Session leeren, jetzt in User-Meta übernommen
    }
}

MediaLab_Wishlist_Storage::init();
