<?php
/**
 * Wunschlisten-Seite. Wird über den Shortcode [mlw_wishlist_page] eingebunden
 * (siehe inc/wishlist/class-frontend.php).
 *
 * Serverseitig einmal mit den aktuellen Items vorgerendert (kein Flackern
 * beim ersten Laden), danach übernimmt wishlist.js alle Änderungen
 * (Menge, Entfernen, Absenden) per Ajax und rendert die Liste neu.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$mlw_items            = MediaLab_Wishlist_Storage::get_items_for_display();
$mlw_wishlist_label    = MediaLab_Inquiry_Settings::wording( 'wishlist_label' );
$mlw_submit_label      = MediaLab_Inquiry_Settings::wording( 'submit_button' );
$mlw_extra_fields      = MediaLab_Inquiry_Settings::get_form_fields_localized();
$mlw_privacy_required  = MediaLab_Inquiry_Settings::privacy_required();
$mlw_privacy_text      = MediaLab_Inquiry_Settings::privacy_text();
// Falls der Kunde die Kontaktdaten bereits im Konfigurator-Wizard eingegeben
// hat (bevor er "Zur Wunschliste hinzufügen" klickte), Formular vorausfüllen.
$mlw_last_contact      = MediaLab_Wishlist_Storage::get_last_contact();
?>
<div class="mlw-wishlist-page">
    <h1 class="mlw-wishlist-page__title"><?php echo esc_html( $mlw_wishlist_label ); ?></h1>

    <div class="mlw-wishlist-page__layout">

        <!-- Produktliste -->
        <div class="mlw-wishlist-page__items-col">
            <div class="mlw-wishlist-items">
                <?php if ( empty( $mlw_items ) ) : ?>
                    <p class="mlw-wishlist-empty"><?php esc_html_e( 'Ihre Wunschliste ist leer.', 'media-lab-woocommerce' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $mlw_items as $item ) : ?>
                        <?php include MEDIA_LAB_WC_PATH . 'templates/wishlist/item-row.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="mlw-wishlist-grand-total" style="<?php echo empty( $mlw_items ) ? 'display:none;' : ''; ?>">
                <span><?php esc_html_e( 'Gesamtsumme:', 'media-lab-woocommerce' ); ?></span>
                <strong class="mlw-wishlist-grand-total__amount"><?php echo wp_kses_post( wc_price( MediaLab_Wishlist_Storage::get_grand_total() ) ); ?></strong>
            </div>
        </div>

        <!-- Absende-Formular -->
        <div class="mlw-wishlist-page__form-col">
            <form id="mlw-wishlist-form" class="mlw-wishlist-form" novalidate>
                <h2 class="mlw-wishlist-form__title"><?php esc_html_e( 'Ihre Kontaktdaten', 'media-lab-woocommerce' ); ?></h2>

                <p class="mlw-form-row">
                    <label for="mlw_wf_name"><?php esc_html_e( 'Name', 'media-lab-woocommerce' ); ?> <span class="required">*</span></label>
                    <input type="text" name="name" id="mlw_wf_name" value="<?php echo esc_attr( $mlw_last_contact['name'] ?? '' ); ?>" required>
                </p>

                <p class="mlw-form-row">
                    <label for="mlw_wf_email"><?php esc_html_e( 'E-Mail', 'media-lab-woocommerce' ); ?> <span class="required">*</span></label>
                    <input type="email" name="email" id="mlw_wf_email" value="<?php echo esc_attr( $mlw_last_contact['email'] ?? '' ); ?>" required>
                </p>

                <p class="mlw-form-row">
                    <label for="mlw_wf_phone"><?php esc_html_e( 'Telefonnummer', 'media-lab-woocommerce' ); ?></label>
                    <input type="tel" name="phone" id="mlw_wf_phone" value="<?php echo esc_attr( $mlw_last_contact['phone'] ?? '' ); ?>">
                </p>

                <?php foreach ( $mlw_extra_fields as $field ) :
                    $key         = esc_attr( $field['field_key'] ?? '' );
                    $label       = esc_html( $field['label'] ?? $key );
                    $required    = ! empty( $field['required'] );
                    $placeholder = esc_attr( $field['placeholder'] ?? '' );
                    if ( ! $key ) continue;
                ?>
                    <p class="mlw-form-row">
                        <label for="mlw_wf_<?php echo $key; ?>"><?php echo $label; ?><?php if ( $required ) : ?> <span class="required">*</span><?php endif; ?></label>
                        <?php if ( ( $field['field_type'] ?? 'text' ) === 'textarea' ) : ?>
                            <textarea name="<?php echo $key; ?>" id="mlw_wf_<?php echo $key; ?>" rows="3" placeholder="<?php echo $placeholder; ?>" <?php echo $required ? 'required' : ''; ?>></textarea>
                        <?php elseif ( ( $field['field_type'] ?? 'text' ) === 'select' ) : ?>
                            <select name="<?php echo $key; ?>" id="mlw_wf_<?php echo $key; ?>" <?php echo $required ? 'required' : ''; ?>>
                                <option value=""></option>
                                <?php foreach ( array_filter( array_map( 'trim', explode( ',', (string) ( $field['options'] ?? '' ) ) ) ) as $option ) : ?>
                                    <option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ( ( $field['field_type'] ?? 'text' ) === 'checkbox' ) : ?>
                            <label class="mlw-checkbox-label"><input type="checkbox" name="<?php echo $key; ?>" id="mlw_wf_<?php echo $key; ?>" value="1"> <?php echo $label; ?></label>
                        <?php else : ?>
                            <input type="<?php echo esc_attr( $field['field_type'] ?? 'text' ); ?>" name="<?php echo $key; ?>" id="mlw_wf_<?php echo $key; ?>" placeholder="<?php echo $placeholder; ?>" value="<?php echo esc_attr( $key === 'company' ? ( $mlw_last_contact['company'] ?? '' ) : '' ); ?>" <?php echo $required ? 'required' : ''; ?>>
                        <?php endif; ?>
                    </p>
                <?php endforeach; ?>

                <p class="mlw-form-row">
                    <label for="mlw_wf_message"><?php esc_html_e( 'Ihre Nachricht', 'media-lab-woocommerce' ); ?></label>
                    <textarea name="message" id="mlw_wf_message" rows="4"><?php echo esc_textarea( $mlw_last_contact['message'] ?? '' ); ?></textarea>
                </p>

                <?php if ( $mlw_privacy_required ) : ?>
                    <p class="mlw-form-row mlw-form-row--privacy">
                        <label class="mlw-checkbox-label">
                            <input type="checkbox" name="privacy_consent" id="mlw_wf_privacy" value="1" required>
                            <span><?php echo wp_kses_post( $mlw_privacy_text ); ?></span>
                        </label>
                    </p>
                <?php endif; ?>

                <?php if ( function_exists( 'medialab_honeypot_render' ) ) : ?>
                    <?php echo medialab_honeypot_render(); ?>
                <?php endif; ?>

                <p class="mlw-form-row">
                    <button type="submit" class="mlw-wishlist-submit" <?php echo empty( $mlw_items ) ? 'disabled' : ''; ?>>
                        <?php echo esc_html( $mlw_submit_label ); ?>
                    </button>
                </p>

                <div class="mlw-wishlist-message" style="display:none;"></div>
            </form>
        </div>

    </div>
</div>
