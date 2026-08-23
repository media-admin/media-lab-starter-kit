<?php
/**
 * Read-Only-Ansicht einer geteilten Wunschliste (per Token-Link, ?mlw_share=).
 * Kein Entfernen/Mengen-Ändern - nur Ansehen + "In den Warenkorb"/
 * "Produkt ansehen" pro Artikel.
 *
 * Erwartet: $shared_items (array, bereits über
 * MediaLab_Wishlist_Storage::get_items_for_display() angereichert - siehe
 * class-frontend.php::render_wishlist_page()).
 *
 * ⚠️ Catalog-Mode-Vorbehalt: Der "In den Warenkorb"-Link nutzt den
 * WooCommerce-Standard-Add-to-Cart-Mechanismus (?add-to-cart=ID). Falls bei
 * euch Catalog Mode aktiv ist (Checkout deaktiviert, Anfrage statt Kauf),
 * muss geprüft werden, ob dessen Umleitungs-Logik an genau diesem
 * Standard-Hook hängt - bitte vor Go-Live gegentesten.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="mlw-wishlist mlw-wishlist--shared">

    <p class="mlw-wishlist-notice mlw-wishlist-notice--shared">
        <?php esc_html_e( 'Dies ist eine geteilte Wunschliste - du siehst sie im Nur-Lese-Modus.', 'media-lab-woocommerce' ); ?>
    </p>

    <?php if ( empty( $shared_items ) ) : ?>

        <p class="mlw-wishlist-empty">
            <?php esc_html_e( 'Diese Wunschliste ist leer.', 'media-lab-woocommerce' ); ?>
        </p>

    <?php else : ?>

        <div class="mlw-wishlist-items mlw-wishlist-items--shared">
            <?php foreach ( $shared_items as $item ) : ?>
                <div class="mlw-wishlist-item mlw-wishlist-item--shared" data-item-id="<?php echo esc_attr( $item['item_id'] ); ?>">

                    <div class="mlw-wishlist-item__image">
                        <?php if ( ! empty( $item['image'] ) ) : ?>
                            <img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>">
                        <?php else : ?>
                            <img src="<?php echo esc_url( wc_placeholder_img_src() ); ?>" alt="">
                        <?php endif; ?>
                    </div>

                    <div class="mlw-wishlist-item__details">
                        <?php if ( ! empty( $item['exists'] ) && ! empty( $item['permalink'] ) ) : ?>
                            <a class="mlw-wishlist-item__name" href="<?php echo esc_url( $item['permalink'] ); ?>">
                                <?php echo esc_html( $item['name'] ); ?>
                            </a>
                        <?php else : ?>
                            <span class="mlw-wishlist-item__name mlw-wishlist-item__name--gone">
                                <?php echo esc_html( $item['name'] ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( ! empty( $item['sku'] ) ) : ?>
                            <span class="mlw-wishlist-item__sku">
                                <?php esc_html_e( 'Art.-Nr.:', 'media-lab-woocommerce' ); ?> <?php echo esc_html( $item['sku'] ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( ! empty( $item['config_display'] ) && is_array( $item['config_display'] ) ) : ?>
                            <ul class="mlw-wishlist-item__config">
                                <?php foreach ( $item['config_display'] as $label => $value ) : ?>
                                    <li><span><?php echo esc_html( $label ); ?>:</span> <?php echo esc_html( $value ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ( $item['unit_price'] !== null && function_exists( 'wc_price' ) ) : ?>
                            <div class="mlw-wishlist-item__price">
                                <?php echo wp_kses_post( wc_price( $item['unit_price'] ) ); ?> <?php esc_html_e( 'pro Stück', 'media-lab-woocommerce' ); ?>
                                <?php if ( $item['line_total'] !== null ) : ?>
                                    · <strong><?php echo wp_kses_post( wc_price( $item['line_total'] ) ); ?></strong> <?php esc_html_e( 'gesamt', 'media-lab-woocommerce' ); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <span class="mlw-wishlist-item__qty-readonly">
                            <?php printf( esc_html__( 'Menge: %d', 'media-lab-woocommerce' ), (int) $item['quantity'] ); ?>
                        </span>
                    </div>

                    <div class="mlw-wishlist-item__shared-cta">
                        <?php if ( empty( $item['exists'] ) ) : ?>

                            <span class="mlw-wishlist-item__name--gone">
                                <?php esc_html_e( 'Nicht mehr verfügbar', 'media-lab-woocommerce' ); ?>
                            </span>

                        <?php elseif ( ! empty( $item['config'] ) ) : ?>

                            <!--
                            Konfigurierte Produkte: kein direktes Hinzufügen zum
                            Warenkorb möglich (Konfiguration/Preisaufschlüsselung
                            lässt sich nicht per Query-String übergeben) -
                            stattdessen Link zur Produktseite, wo der Wizard
                            erneut durchlaufen werden kann.
                            -->
                            <a href="<?php echo esc_url( $item['permalink'] ); ?>" class="mlw-wishlist-item__shared-btn button">
                                <?php esc_html_e( 'Produkt ansehen', 'media-lab-woocommerce' ); ?>
                            </a>

                        <?php else : ?>

                            <?php
                            $add_to_cart_url = add_query_arg( 'add-to-cart', $item['product_id'], wc_get_cart_url() );
                            ?>
                            <a href="<?php echo esc_url( $add_to_cart_url ); ?>" class="mlw-wishlist-item__shared-btn button">
                                <?php esc_html_e( 'In den Warenkorb', 'media-lab-woocommerce' ); ?>
                            </a>

                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>
