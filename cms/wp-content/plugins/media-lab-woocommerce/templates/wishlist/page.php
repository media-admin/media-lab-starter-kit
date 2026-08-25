<?php
/**
 * Wunschlisten-Seite: [mlw_wishlist_page]
 *
 * Klassen/IDs/Feldnamen sind exakt gegen assets/js/wishlist.js und
 * inc/wishlist/class-ajax.php abgeglichen. Honeypot via
 * medialab_honeypot_render() aus agency-core (inc/honeypot.php).
 *
 * Bewusst KEINE eigene <h1>/<h2>-Überschrift hier - die Seite, auf der
 * dieser Shortcode liegt, hat i.d.R. schon einen eigenen WP-Seitentitel;
 * eine zusätzliche Überschrift hier führte zu doppelt wirkenden Titeln.
 * Falls der Shortcode mal ohne eigenen Seitentitel eingebettet wird
 * (z.B. Widget), muss die aufrufende Stelle selbst eine Überschrift setzen.
 *
 * Verzweigt je nach Catalog Mode:
 *   - Catalog Mode AKTIV: Anfrage-Formular (ursprüngliches Verhalten,
 *     für Projekte ohne direkten Checkout).
 *   - Catalog Mode INAKTIV (echter WooCommerce-Checkout): Tabelle mit
 *     Checkboxen, Hinzufügedatum, Lagerstatus, direktem "In den
 *     Warenkorb" pro Zeile + gesammelt (templates/wishlist/item-row-shop.php).
 *     Kein Anfrage-Formular, da bei echtem Checkout niemand ein
 *     Kontaktformular ausfüllen will, um zu kaufen.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$items       = MediaLab_Wishlist_Storage::get_items_for_display();
$grand_total = MediaLab_Wishlist_Storage::get_grand_total();
$contact     = MediaLab_Wishlist_Storage::get_last_contact();
$form_fields = class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::get_form_fields_localized() : [];

$is_catalog_mode = function_exists( 'get_field' ) && get_field( 'wc_catalog_mode_enabled', 'option' ) === true;
?>
<div class="mlw-wishlist">

    <div class="mlw-wishlist__header">
        <?php if ( ! empty( $items ) && $is_catalog_mode && class_exists( 'MediaLab_Wishlist_Share' ) ) : ?>
            <div class="mlw-wishlist__share">
                <?php echo MediaLab_Wishlist_Share::shortcode_share_button(); // phpcs:ignore WordPress.Security.EscapeOutput -- medialab_share() escaped intern ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="mlw-wishlist-notice mlw-wishlist-notice--empty" style="display:<?php echo empty( $items ) ? 'block' : 'none'; ?>;">
        <p><?php echo esc_html( MediaLab_Inquiry_Settings::wording( 'wishlist_empty' ) ); ?></p>
    </div>
    <a
        href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
        class="mlw-wishlist-back-to-shop button"
        style="display:<?php echo empty( $items ) ? 'inline-block' : 'none'; ?>;"
    >
        <?php esc_html_e( 'Zurück zum Shop', 'media-lab-woocommerce' ); ?>
    </a>

    <?php if ( ! empty( $items ) && $is_catalog_mode ) : ?>

        <!-- ═══════════════════════════════════════════════════════════════
             CATALOG MODE: Grid + Anfrage-Formular (ursprüngliches Verhalten)
             ═══════════════════════════════════════════════════════════════ -->

        <!--
        Plain <div>, kein <ul> - wishlist.js ersetzt bei Ajax-Änderungen
        '.mlw-wishlist-items' innerHTML direkt mit <div class="mlw-wishlist-item">-
        Geschwistern (siehe renderItems()/renderItemRow() im JS). Ein <ul>-Wrapper
        hier würde danach ungültig verschachteltes Markup ergeben.
        -->
        <div class="mlw-wishlist-items">
            <?php foreach ( $items as $item ) : ?>
                <?php include MEDIA_LAB_WC_PATH . 'templates/wishlist/item-row.php'; ?>
            <?php endforeach; ?>
        </div>

        <div class="mlw-wishlist-grand-total">
            <span class="mlw-wishlist-grand-total__label"><?php esc_html_e( 'Gesamt', 'media-lab-woocommerce' ); ?></span>
            <span class="mlw-wishlist-grand-total__amount">
                <?php echo wc_price( $grand_total ); // phpcs:ignore WordPress.Security.EscapeOutput -- wc_price() escaped ?>
            </span>
        </div>

        <form id="mlw-wishlist-form" novalidate>

            <div class="mlw-wishlist-message" style="display:none;" role="status" aria-live="polite"></div>

            <div class="mlw-wishlist__form-row">
                <label for="mlw-wishlist-name"><?php esc_html_e( 'Name', 'media-lab-woocommerce' ); ?> *</label>
                <input type="text" id="mlw-wishlist-name" name="name" required value="<?php echo esc_attr( $contact['name'] ); ?>">
            </div>

            <div class="mlw-wishlist__form-row">
                <label for="mlw-wishlist-email"><?php esc_html_e( 'E-Mail', 'media-lab-woocommerce' ); ?> *</label>
                <input type="email" id="mlw-wishlist-email" name="email" required value="<?php echo esc_attr( $contact['email'] ); ?>">
            </div>

            <div class="mlw-wishlist__form-row">
                <label for="mlw-wishlist-phone"><?php esc_html_e( 'Telefon', 'media-lab-woocommerce' ); ?></label>
                <input type="tel" id="mlw-wishlist-phone" name="phone" value="<?php echo esc_attr( $contact['phone'] ); ?>">
            </div>

            <?php foreach ( $form_fields as $field ) :
                $field_key = esc_attr( $field['field_key'] ?? '' );
                if ( ! $field_key ) continue;
                $field_type  = $field['field_type']  ?? 'text';
                $field_label = $field['label']       ?? '';
                $required    = ! empty( $field['required'] );
            ?>
                <div class="mlw-wishlist__form-row">
                    <label for="mlw-wishlist-<?php echo $field_key; ?>">
                        <?php echo esc_html( $field_label ); ?><?php if ( $required ) echo ' *'; ?>
                    </label>

                    <?php // WICHTIG: name FLACH (nicht fields[...]) - class-ajax.php::submit()
                          // liest $_POST[$key] direkt pro konfiguriertem Zusatzfeld. ?>

                    <?php if ( $field_type === 'textarea' ) : ?>
                        <textarea id="mlw-wishlist-<?php echo $field_key; ?>" name="<?php echo $field_key; ?>" <?php echo $required ? 'required' : ''; ?>></textarea>
                    <?php elseif ( $field_type === 'select' ) : ?>
                        <select id="mlw-wishlist-<?php echo $field_key; ?>" name="<?php echo $field_key; ?>" <?php echo $required ? 'required' : ''; ?>>
                            <?php foreach ( array_filter( array_map( 'trim', explode( ',', $field['options'] ?? '' ) ) ) as $option ) : ?>
                                <option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ( $field_type === 'checkbox' ) : ?>
                        <input type="checkbox" id="mlw-wishlist-<?php echo $field_key; ?>" name="<?php echo $field_key; ?>" value="1">
                    <?php else : ?>
                        <input
                            type="<?php echo esc_attr( $field_type ); ?>"
                            id="mlw-wishlist-<?php echo $field_key; ?>"
                            name="<?php echo $field_key; ?>"
                            placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                            <?php echo $required ? 'required' : ''; ?>
                        >
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="mlw-wishlist__form-row">
                <label for="mlw-wishlist-message"><?php esc_html_e( 'Nachricht', 'media-lab-woocommerce' ); ?></label>
                <textarea id="mlw-wishlist-message" name="message"><?php echo esc_textarea( $contact['message'] ); ?></textarea>
            </div>

            <?php if ( MediaLab_Inquiry_Settings::privacy_required() ) : ?>
                <div class="mlw-wishlist__form-row mlw-wishlist__form-row--privacy">
                    <label>
                        <input type="checkbox" name="privacy_consent" required>
                        <?php echo wp_kses_post( MediaLab_Inquiry_Settings::privacy_text() ); ?>
                    </label>
                </div>
            <?php endif; ?>

            <?php
            if ( function_exists( 'medialab_honeypot_render' ) ) {
                echo medialab_honeypot_render(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped intern (esc_attr auf allen dynamischen Teilen)
            }
            ?>

            <button type="submit" class="mlw-wishlist-submit button" <?php disabled( empty( $items ) ); ?>>
                <?php echo esc_html( MediaLab_Inquiry_Settings::wording( 'submit_button' ) ); ?>
            </button>

        </form>

    <?php elseif ( ! empty( $items ) ) : ?>

        <!-- ═══════════════════════════════════════════════════════════════
             SHOP MODUS: Tabelle mit direktem "In den Warenkorb"
             (kein Catalog Mode - echter WooCommerce-Checkout aktiv)
             ═══════════════════════════════════════════════════════════════ -->

        <table class="mlw-wishlist-table">
            <colgroup>
                <col style="width: 32px;">
                <col style="width: 88px;">
                <col style="width: 30%;">
                <col style="width: 90px;">
                <col style="width: 120px;">
                <col style="width: 140px;">
                <col style="width: 160px;">
            </colgroup>
            <thead>
                <tr>
                    <th class="mlw-wishlist-table__select-all">
                        <input type="checkbox" id="mlw-wishlist-select-all" aria-label="<?php esc_attr_e( 'Alle auswählen', 'media-lab-woocommerce' ); ?>">
                    </th>
                    <th></th>
                    <th><?php esc_html_e( 'Produktname', 'media-lab-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Preis', 'media-lab-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Hinzugefügt am', 'media-lab-woocommerce' ); ?></th>
                    <th><?php esc_html_e( 'Lagerstatus', 'media-lab-woocommerce' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ) : ?>
                    <?php include MEDIA_LAB_WC_PATH . 'templates/wishlist/item-row-shop.php'; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mlw-wishlist-message" style="display:none;" role="status" aria-live="polite"></div>

        <div class="mlw-wishlist__bulk-actions">
            <button type="button" id="mlw-wishlist-bulk-add" class="button" disabled>
                <?php esc_html_e( 'Ausgewählte in den Warenkorb', 'media-lab-woocommerce' ); ?>
            </button>
        </div>

        <?php if ( class_exists( 'MediaLab_Wishlist_Share' ) ) : ?>
            <div class="mlw-wishlist__share mlw-wishlist__share--footer">
                <?php echo MediaLab_Wishlist_Share::shortcode_share_button(); // phpcs:ignore WordPress.Security.EscapeOutput -- medialab_share() escaped intern; rendert selbst schon ein "Teilen"-Label, kein eigenes daneben nötig ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
