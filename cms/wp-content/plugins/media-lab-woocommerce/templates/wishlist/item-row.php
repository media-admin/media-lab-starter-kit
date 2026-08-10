<?php
/**
 * Eine Zeile in der Wunschlisten-Übersicht.
 *
 * Erwartet $item im Format von MediaLab_Wishlist_Storage::get_items_for_display().
 * WICHTIG: Die JS-Entsprechung (wishlist.js, renderItemRow()) muss bei
 * strukturellen Änderungen an diesem Template manuell synchron gehalten
 * werden, da Ajax-Updates die Liste clientseitig neu rendern.
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

        <?php if ( $item['unit_price'] !== null && function_exists( 'wc_price' ) ) : ?>
            <div class="mlw-wishlist-item__price">
                <?php echo wp_kses_post( wc_price( $item['unit_price'] ) ); ?> <?php esc_html_e( 'pro Stück', 'media-lab-woocommerce' ); ?>
                · <strong><?php echo wp_kses_post( wc_price( $item['line_total'] ) ); ?></strong> <?php esc_html_e( 'gesamt', 'media-lab-woocommerce' ); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="mlw-wishlist-item__quantity">
        <input type="number" class="mlw-wishlist-item__qty-input" min="1" value="<?php echo esc_attr( $item['quantity'] ); ?>" data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>">
    </div>

    <div class="mlw-wishlist-item__remove">
        <button type="button" class="mlw-wishlist-item__remove-btn" data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>" aria-label="<?php esc_attr_e( 'Entfernen', 'media-lab-woocommerce' ); ?>">✕</button>
    </div>

</div>
