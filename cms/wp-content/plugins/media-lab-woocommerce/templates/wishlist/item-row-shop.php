<?php
/**
 * Eine Zeile in der Shop-Modus-Wunschlisten-Tabelle (kein Catalog Mode -
 * Projekte mit echtem WooCommerce-Checkout, z.B. juwelier-janecka).
 *
 * Erwartet: $item (ein Element aus
 * MediaLab_Wishlist_Storage::get_items_for_display()).
 *
 * Anders als item-row.php (Catalog-Mode-Grid) gibt es hier KEINEN
 * JS-Spiegel für reaktives Neurendern - nach Entfernen/Warenkorb-Verschieben
 * wird die Zeile per removeShopRows() direkt aus dem DOM entfernt statt
 * neu gerendert (siehe wishlist.js).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$added_date = ! empty( $item['added_at'] ) ? date_i18n( get_option( 'date_format' ), (int) $item['added_at'] ) : '';
$product    = $item['exists'] ? wc_get_product( $item['product_id'] ) : null;

// Nur "einfache" Produkte lassen sich per add_to_cart(product_id, qty) ohne
// weitere Auswahl direkt in den Warenkorb legen. Variable Produkte (z.B.
// Ringe mit Größen-Varianten) brauchen eine explizite variation_id +
// Attribut-Auswahl, die wir hier nicht haben - Link zur Produktseite
// stattdessen (wie bei unseren eigenen konfigurierten Artikeln).
$is_simple = $product && $product->is_type( 'simple' );

// Zusätzlich zur Lagerstatus-Prüfung die Menge selbst gegenchecken - ein
// Produkt kann is_in_stock() === true liefern, obwohl die geführte Menge
// bei 0 steht (z.B. Lagerbestandsführung ohne Rückstands-Erlaubnis).
$in_stock = $is_simple && $product->is_in_stock() && ( $product->get_stock_quantity() === null || $product->get_stock_quantity() > 0 );

$selectable = $item['exists'] && empty( $item['config'] ) && $in_stock;
?>
<tr class="mlw-wishlist-row" data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>">

    <td class="mlw-wishlist-row__select">
        <?php if ( $selectable ) : ?>
            <input type="checkbox" class="mlw-wishlist-row__checkbox" value="<?php echo esc_attr( $item['item_id'] ); ?>">
        <?php endif; ?>
    </td>

    <td class="mlw-wishlist-row__thumb">
        <button
            type="button"
            class="mlw-wishlist-row__remove-btn"
            data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>"
            aria-label="<?php esc_attr_e( 'Entfernen', 'media-lab-woocommerce' ); ?>"
        >✕</button>
        <div class="mlw-wishlist-row__thumb-wrap">
            <?php if ( ! empty( $item['image'] ) ) : ?>
                <img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>">
            <?php else : ?>
                <img src="<?php echo esc_url( wc_placeholder_img_src() ); ?>" alt="">
            <?php endif; ?>
        </div>
    </td>

    <td class="mlw-wishlist-row__name" data-label="<?php esc_attr_e( 'Produktname', 'media-lab-woocommerce' ); ?>">
        <?php if ( $item['exists'] && $item['permalink'] ) : ?>
            <a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
        <?php else : ?>
            <span class="mlw-wishlist-item__name--gone"><?php echo esc_html( $item['name'] ); ?></span>
        <?php endif; ?>
        <?php if ( ! empty( $item['config_display'] ) && is_array( $item['config_display'] ) ) : ?>
            <ul class="mlw-wishlist-item__config">
                <?php foreach ( $item['config_display'] as $label => $value ) : ?>
                    <li><span><?php echo esc_html( $label ); ?>:</span> <?php echo esc_html( $value ); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </td>

    <td class="mlw-wishlist-row__price" data-label="<?php esc_attr_e( 'Preis', 'media-lab-woocommerce' ); ?>">
        <?php if ( $item['unit_price'] !== null ) : ?>
            <?php echo wp_kses_post( wc_price( $item['unit_price'] ) ); ?>
        <?php endif; ?>
    </td>

    <td class="mlw-wishlist-row__date" data-label="<?php esc_attr_e( 'Hinzugefügt am', 'media-lab-woocommerce' ); ?>">
        <?php echo esc_html( $added_date ); ?>
    </td>

    <td class="mlw-wishlist-row__stock" data-label="<?php esc_attr_e( 'Lagerstatus', 'media-lab-woocommerce' ); ?>">
        <?php if ( $product ) : ?>
            <?php if ( $in_stock ) :
                $qty = $product->get_stock_quantity();
            ?>
                <span class="mlw-wishlist-row__stock-icon">✔</span>
                <?php if ( $qty !== null && $qty <= 5 ) : ?>
                    <?php printf( esc_html__( 'Nur noch %d vorrätig', 'media-lab-woocommerce' ), (int) $qty ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'Auf Lager', 'media-lab-woocommerce' ); ?>
                <?php endif; ?>
            <?php else : ?>
                <span class="mlw-wishlist-row__stock-icon mlw-wishlist-row__stock-icon--out">✕</span>
                <?php esc_html_e( 'Nicht vorrätig', 'media-lab-woocommerce' ); ?>
            <?php endif; ?>
        <?php endif; ?>
    </td>

    <td class="mlw-wishlist-row__action">
        <?php if ( ! $item['exists'] ) : ?>
            <span class="mlw-wishlist-item__name--gone"><?php esc_html_e( 'Nicht mehr verfügbar', 'media-lab-woocommerce' ); ?></span>
        <?php elseif ( ! empty( $item['config'] ) || ! $is_simple ) : ?>
            <!-- Konfigurierte Produkte ODER Varianten-Produkte (z.B. Ringgröße):
                 kein direktes Hinzufügen ohne weitere Auswahl möglich -
                 Link zur Produktseite stattdessen. -->
            <a href="<?php echo esc_url( $item['permalink'] ); ?>" class="button mlw-wishlist-row__action-btn">
                <?php esc_html_e( 'Produkt ansehen', 'media-lab-woocommerce' ); ?>
            </a>
        <?php elseif ( ! $in_stock ) : ?>
            <span class="mlw-wishlist-item__name--gone"><?php esc_html_e( 'Nicht vorrätig', 'media-lab-woocommerce' ); ?></span>
        <?php else : ?>
            <button
                type="button"
                class="button mlw-wishlist-row__action-btn"
                data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>"
            >
                <?php esc_html_e( 'In den Warenkorb', 'media-lab-woocommerce' ); ?>
            </button>
        <?php endif; ?>
    </td>

</tr>
