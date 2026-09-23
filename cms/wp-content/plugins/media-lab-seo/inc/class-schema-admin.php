<?php
/**
 * MLT_Schema_Admin
 *
 * 1) Untermenü „Schema" unter „SEO Toolkit" (Organisationstyp, Adresse, sameAs,
 *    Öffnungszeiten, Einzugsgebiet).
 * 2) Metabox „Schema (SEO / AEO)" pro Beitrag/Seite/CPT:
 *      - Schema für diese Seite deaktivieren
 *      - Seitentyp überschreiben (AboutPage, ContactPage, …)
 *      - Eigenes JSON-LD ergänzen (nur Administratoren)
 *
 * Standard bleibt: alles automatisch. Die Metabox ist nur der Ausnahme-Weg.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Schema_Admin {

    const NONCE = 'mlt_schema_meta';

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_menu' ], 20 );
        add_action( 'admin_init',    [ $this, 'register_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post',     [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_notices', [ $this, 'notices' ] );
        add_filter( 'user_contactmethods', [ $this, 'user_contactmethods' ] );
    }

    /** Profil-Links am WP-Benutzer (fließen als sameAs in das Autoren-Schema). */
    public function user_contactmethods( $methods ) {
        foreach ( MLT_Schema::AUTHOR_PROFILES as $key => $label ) {
            $methods[ $key ] = $label . ' (URL)';
        }

        return $methods;
    }

    // ── Settings-Seite ────────────────────────────────────────────────────────

    public function add_menu() {
        add_submenu_page(
            'media-lab-seo',
            __( 'Schema', 'media-lab-seo' ),
            __( 'Schema', 'media-lab-seo' ),
            'manage_options',
            'mlt-schema',
            [ $this, 'render_settings' ]
        );
    }

    public function register_settings() {
        $text = [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ];

        register_setting( 'mlt_schema', 'mlt_schema_org_type', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_org_type' ],
            'default'           => 'Organization',
        ] );
        register_setting( 'mlt_schema', 'mlt_schema_phone', $text );
        register_setting( 'mlt_schema', 'mlt_schema_email', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_email' ] );
        register_setting( 'mlt_schema', 'mlt_schema_street', $text );
        register_setting( 'mlt_schema', 'mlt_schema_postal_code', $text );
        register_setting( 'mlt_schema', 'mlt_schema_locality',    $text );
        register_setting( 'mlt_schema', 'mlt_schema_country', [
            'type'              => 'string',
            'sanitize_callback' => static fn( $v ) => strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $v ), 0, 2 ) ),
        ] );
        register_setting( 'mlt_schema', 'mlt_schema_same_as', [
            'type'              => 'string',
            'sanitize_callback' => static fn( $v ) => implode( "\n", array_filter( array_map( 'esc_url_raw', preg_split( '/\R/', trim( (string) $v ) ) ) ) ),
        ] );
        register_setting( 'mlt_schema', 'mlt_schema_opening_hours', [
            'type'              => 'string',
            'sanitize_callback' => static fn( $v ) => implode( "\n", array_filter( array_map( 'sanitize_text_field', preg_split( '/\R/', trim( (string) $v ) ) ) ) ),
        ] );
        register_setting( 'mlt_schema', 'mlt_schema_area_served', $text );

        // llms.txt (inc/class-llms-txt.php) – eigener, kleiner Abschnitt auf derselben Seite,
        // kein eigenes Untermenü nötig für zwei Felder.
        register_setting( 'mlt_schema', 'mlt_llms_txt_enabled', [
            'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true,
        ] );
        register_setting( 'mlt_schema', 'mlt_llms_txt_post_limit', [
            'type' => 'integer',
            'sanitize_callback' => static fn( $v ) => max( 0, absint( $v ) ),
            'default' => 20,
        ] );
    }

    public function sanitize_org_type( $value ) {
        $allowed = apply_filters( 'mlt_schema_org_types', MLT_Schema::ORG_TYPES );

        return in_array( $value, $allowed, true ) ? $value : 'Organization';
    }

    public function render_settings() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $types = [
            'Organization'        => 'Organisation (Standard)',
            'LocalBusiness'       => 'Lokales Unternehmen (allgemein)',
            'ProfessionalService' => 'Dienstleister / Agentur',
            'ArtGallery'          => 'Galerie',
            'Store'               => 'Geschäft',
            'Restaurant'          => 'Restaurant',
            'Hotel'               => 'Hotel',
            'MedicalBusiness'     => 'Medizinischer Betrieb',
            'HealthAndBeautyBusiness' => 'Health & Beauty',
            'AutomotiveBusiness'  => 'Autohaus / Kfz',
            'FoodEstablishment'   => 'Gastronomie (allgemein)',
            'LodgingBusiness'     => 'Unterkunft',
        ];
        $current = get_option( 'mlt_schema_org_type', 'Organization' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Schema.org – Einstellungen', 'media-lab-seo' ); ?></h1>
            <p>
                Schema wird automatisch für alle Seiten erzeugt. Hier pflegst du nur die Stammdaten der Organisation.
                Leere Kontaktfelder werden – sofern dort aktiviert – aus <em>Agency Core → Top Header / Kontaktdaten</em>
                übernommen (Telefon, E-Mail, Adresse, Social-Links). Das Logo kommt aus <em>Logo / Globale Einstellungen</em>.
                Autoren-Profile (LinkedIn, Xing, …) pflegst du im jeweiligen Benutzerprofil.
            </p>
            <form method="post" action="options.php">
                <?php settings_fields( 'mlt_schema' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mlt_schema_org_type">Organisationstyp</label></th>
                        <td>
                            <select name="mlt_schema_org_type" id="mlt_schema_org_type">
                                <?php foreach ( $types as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Bei lokalen Unternehmen einen passenden Typ wählen – Öffnungszeiten werden dann mit ausgegeben.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_phone">Telefon</label></th>
                        <td><input type="text" class="regular-text" name="mlt_schema_phone" id="mlt_schema_phone" value="<?php echo esc_attr( get_option( 'mlt_schema_phone', '' ) ); ?>" placeholder="+43 1 234 56 78"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_email">E-Mail</label></th>
                        <td><input type="email" class="regular-text" name="mlt_schema_email" id="mlt_schema_email" value="<?php echo esc_attr( get_option( 'mlt_schema_email', '' ) ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_street">Straße &amp; Hausnr.</label></th>
                        <td><input type="text" class="regular-text" name="mlt_schema_street" id="mlt_schema_street" value="<?php echo esc_attr( get_option( 'mlt_schema_street', '' ) ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_postal_code">PLZ</label></th>
                        <td><input type="text" class="regular-text" name="mlt_schema_postal_code" id="mlt_schema_postal_code" value="<?php echo esc_attr( get_option( 'mlt_schema_postal_code', '' ) ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_locality">Ort</label></th>
                        <td><input type="text" class="regular-text" name="mlt_schema_locality" id="mlt_schema_locality" value="<?php echo esc_attr( get_option( 'mlt_schema_locality', '' ) ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_country">Land (ISO-Code)</label></th>
                        <td><input type="text" class="small-text" maxlength="2" name="mlt_schema_country" id="mlt_schema_country" value="<?php echo esc_attr( get_option( 'mlt_schema_country', '' ) ); ?>" placeholder="AT"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_opening_hours">Öffnungszeiten</label></th>
                        <td>
                            <textarea name="mlt_schema_opening_hours" id="mlt_schema_opening_hours" rows="4" class="large-text code"><?php echo esc_textarea( get_option( 'mlt_schema_opening_hours', '' ) ); ?></textarea>
                            <p class="description">Eine Zeile pro Eintrag, z. B. <code>Mo-Fr 08:00-17:00</code> oder <code>Sa 09:00-12:00</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_area_served">Einzugsgebiet</label></th>
                        <td>
                            <input type="text" class="large-text" name="mlt_schema_area_served" id="mlt_schema_area_served" value="<?php echo esc_attr( get_option( 'mlt_schema_area_served', '' ) ); ?>">
                            <p class="description">Kommagetrennt, z. B. <code>Neunkirchen, Wiener Neustadt, Niederösterreich</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_schema_same_as">Profile (sameAs)</label></th>
                        <td>
                            <textarea name="mlt_schema_same_as" id="mlt_schema_same_as" rows="5" class="large-text code"><?php echo esc_textarea( get_option( 'mlt_schema_same_as', '' ) ); ?></textarea>
                            <p class="description">Eine URL pro Zeile: Social-Profile, Google-Unternehmensprofil, Wikipedia/Wikidata, Firmenbuch …</p>
                        </td>
                    </tr>
                </table>
                <h2 style="margin-top:2em;">llms.txt</h2>
                <p class="description">
                    Kuratierte, an KI-Systeme gerichtete Seitenübersicht unter
                    <code><?php echo esc_html( home_url( '/llms.txt' ) ); ?></code> – Community-Format,
                    kein offizieller Standard, Unterstützung durch große KI-Anbieter nicht garantiert.
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Aktiviert</th>
                        <td>
                            <label>
                                <input type="checkbox" name="mlt_llms_txt_enabled" value="1" <?php checked( get_option( 'mlt_llms_txt_enabled', 1 ) ); ?>>
                                /llms.txt ausgeben (Seiten, Leistungen, Blog automatisch aus veröffentlichten Inhalten)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mlt_llms_txt_post_limit">Blogbeiträge</label></th>
                        <td>
                            <input type="number" min="0" step="1" class="small-text"
                                   name="mlt_llms_txt_post_limit" id="mlt_llms_txt_post_limit"
                                   value="<?php echo esc_attr( get_option( 'mlt_llms_txt_post_limit', 20 ) ); ?>">
                            <p class="description">Anzahl der neuesten Beiträge im Abschnitt „Blog". <code>0</code> blendet den Abschnitt aus.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // ── Metabox ───────────────────────────────────────────────────────────────

    public function add_meta_box() {
        $types = get_post_types( [ 'public' => true ] );
        unset( $types['attachment'] );

        foreach ( $types as $type ) {
            add_meta_box( 'mlt_schema', 'Schema (SEO / AEO)', [ $this, 'render_meta_box' ], $type, 'side', 'low' );
        }
    }

    public function render_meta_box( WP_Post $post ) {
        wp_nonce_field( self::NONCE, self::NONCE );

        $disabled = (bool) get_post_meta( $post->ID, '_mlt_schema_disable', true );
        $type     = (string) get_post_meta( $post->ID, '_mlt_schema_webpage_type', true );
        $custom   = (string) get_post_meta( $post->ID, '_mlt_schema_custom', true );
        if ( $custom ) {
            $decoded = json_decode( $custom, true );
            $custom  = $decoded ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $custom;
        }
        ?>
        <p class="description">Schema wird automatisch erzeugt (Seite, Breadcrumbs, FAQ, je Inhaltstyp). Nur bei Ausnahmen hier eingreifen.</p>

        <p>
            <label>
                <input type="checkbox" name="mlt_schema_disable" value="1" <?php checked( $disabled ); ?>>
                Schema für diese Seite deaktivieren
            </label>
        </p>

        <p>
            <label for="mlt_schema_webpage_type"><strong>Seitentyp</strong></label><br>
            <select name="mlt_schema_webpage_type" id="mlt_schema_webpage_type" style="width:100%">
                <option value="">Automatisch</option>
                <?php foreach ( MLT_Schema::WEBPAGE_TYPES as $t ) : ?>
                    <option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( $t ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <p>
                <label for="mlt_schema_custom"><strong>Eigenes JSON-LD (optional)</strong></label><br>
                <textarea name="mlt_schema_custom" id="mlt_schema_custom" rows="6" style="width:100%;font-family:monospace;font-size:12px" placeholder='{"@type":"Event","name":"…"}'><?php echo esc_textarea( $custom ); ?></textarea>
                <span class="description">Ein Node oder eine Liste von Nodes, ohne <code>&lt;script&gt;</code> und ohne <code>@context</code>. Wird in den Graph aufgenommen.</span>
            </p>
        <?php endif;
    }

    public function save_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! isset( $_POST[ self::NONCE ] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Deaktivieren
        if ( ! empty( $_POST['mlt_schema_disable'] ) ) {
            update_post_meta( $post_id, '_mlt_schema_disable', 1 );
        } else {
            delete_post_meta( $post_id, '_mlt_schema_disable' );
        }

        // Seitentyp
        $type = isset( $_POST['mlt_schema_webpage_type'] ) ? sanitize_text_field( wp_unslash( $_POST['mlt_schema_webpage_type'] ) ) : '';
        if ( in_array( $type, MLT_Schema::WEBPAGE_TYPES, true ) ) {
            update_post_meta( $post_id, '_mlt_schema_webpage_type', $type );
        } else {
            delete_post_meta( $post_id, '_mlt_schema_webpage_type' );
        }

        // Eigenes JSON-LD – nur Administratoren
        if ( current_user_can( 'manage_options' ) && isset( $_POST['mlt_schema_custom'] ) ) {
            $raw = trim( wp_unslash( $_POST['mlt_schema_custom'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

            if ( '' === $raw ) {
                delete_post_meta( $post_id, '_mlt_schema_custom' );
            } else {
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) {
                    // Neu kodiert speichern (kein Roh-Input im Output); wp_slash wegen update_post_meta
                    update_post_meta( $post_id, '_mlt_schema_custom', wp_slash( wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
                } else {
                    set_transient( 'mlt_schema_json_error_' . get_current_user_id(), 1, 60 );
                }
            }
        }
    }

    public function notices() {
        $key = 'mlt_schema_json_error_' . get_current_user_id();
        if ( get_transient( $key ) ) {
            delete_transient( $key );
            echo '<div class="notice notice-error is-dismissible"><p><strong>Schema:</strong> Das eigene JSON-LD ist kein gültiges JSON und wurde nicht gespeichert.</p></div>';
        }
    }
}
