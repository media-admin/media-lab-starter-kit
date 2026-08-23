<?php
/**
 * Eine Zeile in der Wunschlisten-Übersicht.
 *
 * Struktur als Grid-Spalten (siehe assets/src/scss/components/_wishlist.scss):
 * Bild | Details (Name/SKU/Konfiguration) | Einzelpreis | Menge | Positions-
 * gesamtpreis | Entfernen. Die "Positionsgesamtpreis"-Spalte ist bewusst von
 * "Details" getrennt, damit sie exakt über der Gesamtsumme-Zeile
 * (.mlw-wishlist-grand-total) ausgerichtet werden kann - beide nutzen
 * dasselbe Grid-Template.
 *
 * WICHTIG: Diese Struktur wird von assets/js/wishlist.js::renderItemRow()
 * 1:1 gespiegelt. Bei Änderungen HIER immer auch dort anpassen.
 *
 * Erwartet: $item (ein einzelnes Element aus
 * MediaLab_Wishlist_Storage::get_items_for_display()).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="mlw-wishlist-item" data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>">

    <div class="mlw-wishlist-item__image">
        <?php if ( ! empty( $item['image'] ) ) : ?>
            <img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>">
        <?php else : ?>
            <img src="<?php echo esc_url( wc_placeholder_img_src() ); ?>" alt="">
        <?php endif; ?>
    </div>

    <div class="mlw-wishlist-item__details">
        <?php if ( ! empty( $item['exists'] ) && ! empty( $item['permalink'] ) ) : ?>
            <a class="mlw-wishlist-item__name" href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
        <?php else : ?>
            <span class="mlw-wishlist-item__name mlw-wishlist-item__name--gone"><?php echo esc_html( $item['name'] ); ?></span>
        <?php endif; ?>

        <?php if ( ! empty( $item['sku'] ) ) : ?>
            <span class="mlw-wishlist-item__sku"><?php esc_html_e( 'Art.-Nr.:', 'media-lab-woocommerce' ); ?> <?php echo esc_html( $item['sku'] ); ?></span>
        <?php endif; ?>

        <?php if ( ! empty( $item['config_display'] ) && is_array( $item['config_display'] ) ) : ?>
            <ul class="mlw-wishlist-item__config">
                <?php foreach ( $item['config_display'] as $label => $value ) : ?>
                    <li><span><?php echo esc_html( $label ); ?>:</span> <?php echo esc_html( $value ); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ( ! empty( $item['attachment_urls'] ) ) : ?>
            <div class="mlw-wishlist-item__attachments">
                <?php foreach ( $item['attachment_urls'] as $att ) : ?>
                    <a href="<?php echo esc_url( $att['url'] ); ?>" target="_blank">📎 <?php echo esc_html( $att['filename'] ); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ( $item['unit_price'] !== null && function_exists( 'wc_price' ) ) : ?>
        <div class="mlw-wishlist-item__price">
            <?php echo wp_kses_post( wc_price( $item['unit_price'] ) ); ?>
        </div>
    <?php else : ?>
        <div class="mlw-wishlist-item__price"></div>
    <?php endif; ?>

    <div class="mlw-wishlist-item__quantity">
        <input type="number" class="mlw-wishlist-item__qty-input" min="1" value="<?php echo esc_attr( $item['quantity'] ); ?>" data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>">
    </div>

    <?php if ( $item['line_total'] !== null && function_exists( 'wc_price' ) ) : ?>
        <div class="mlw-wishlist-item__line-total">
            <?php echo wp_kses_post( wc_price( $item['line_total'] ) ); ?>
        </div>
    <?php else : ?>
        <div class="mlw-wishlist-item__line-total"></div>
    <?php endif; ?>

    <div class="mlw-wishlist-item__remove">
        <button type="button" class="mlw-wishlist-item__remove-btn" data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>" aria-label="<?php esc_attr_e( 'Entfernen', 'media-lab-woocommerce' ); ?>">✕</button>
    </div>

</div>
