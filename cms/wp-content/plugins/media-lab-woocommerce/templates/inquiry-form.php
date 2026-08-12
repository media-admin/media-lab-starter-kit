<?php
/**
 * Anfrageformular (Catalog Mode)
 *
 * Rendert die in den Inquiry-Einstellungen konfigurierten Zusatzfelder
 * (Pflicht/Optional, je nach Projekt editierbar) sowie die Datenschutz-
 * Zustimmung dynamisch - siehe inc/inquiry/class-settings.php.
 *
 * Aufruf: Shortcode [mlw_inquiry_form], siehe inc/inquiry/class-shortcode.php.
 * Für die Nutzung außerhalb des automatischen Catalog-Mode-Checkout-Flows
 * gedacht, z.B. auf einer eigenen Landingpage oder in einem Content-Block.
 */
defined('ABSPATH') || exit;

$mlw_extra_fields     = class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::get_form_fields_localized() : [];
$mlw_privacy_required = class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::privacy_required() : false;
$mlw_privacy_text     = class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::privacy_text() : '';
$mlw_submit_label     = class_exists( 'MediaLab_Inquiry_Settings' ) ? MediaLab_Inquiry_Settings::wording( 'submit_button' ) : 'Anfrage senden';
?>

<div class="woocommerce-catalog-inquiry">
    <h2>Produktanfrage senden</h2>
    
    <?php if (WC()->cart->get_cart_contents_count() > 0) : ?>
        
        <div class="inquiry-cart-review">
            <h3>Ihre ausgewählten Produkte:</h3>
            <ul>
                <?php foreach (WC()->cart->get_cart() as $cart_item) : 
                    $product = $cart_item['data'];
                ?>
                    <li>
                        <?php echo esc_html($product->get_name()); ?> 
                        (Menge: <?php echo esc_html($cart_item['quantity']); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <form id="catalog-inquiry-form" class="inquiry-form">
            <p class="form-row">
                <label for="inquiry_name">Name *</label>
                <input type="text" name="name" id="inquiry_name" required>
            </p>
            
            <p class="form-row">
                <label for="inquiry_email">E-Mail *</label>
                <input type="email" name="email" id="inquiry_email" required>
            </p>
            
            <p class="form-row">
                <label for="inquiry_phone">Telefonnummer</label>
                <input type="tel" name="phone" id="inquiry_phone">
            </p>

            <?php foreach ( $mlw_extra_fields as $field ) :
                $key         = esc_attr( $field['field_key'] ?? '' );
                $label       = esc_html( $field['label'] ?? $key );
                $required    = ! empty( $field['required'] );
                $placeholder = esc_attr( $field['placeholder'] ?? '' );
                if ( ! $key ) continue;
            ?>
                <p class="form-row">
                    <label for="inquiry_<?php echo $key; ?>"><?php echo $label; ?><?php echo $required ? ' *' : ''; ?></label>
                    <?php if ( ( $field['field_type'] ?? 'text' ) === 'textarea' ) : ?>
                        <textarea name="<?php echo $key; ?>" id="inquiry_<?php echo $key; ?>" placeholder="<?php echo $placeholder; ?>" rows="3" <?php echo $required ? 'required' : ''; ?>></textarea>
                    <?php elseif ( ( $field['field_type'] ?? 'text' ) === 'select' ) : ?>
                        <select name="<?php echo $key; ?>" id="inquiry_<?php echo $key; ?>" <?php echo $required ? 'required' : ''; ?>>
                            <option value=""></option>
                            <?php foreach ( array_filter( array_map( 'trim', explode( ',', (string) ( $field['options'] ?? '' ) ) ) ) as $option ) : ?>
                                <option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ( ( $field['field_type'] ?? 'text' ) === 'checkbox' ) : ?>
                        <input type="checkbox" name="<?php echo $key; ?>" id="inquiry_<?php echo $key; ?>" value="1">
                    <?php else : ?>
                        <input type="<?php echo esc_attr( $field['field_type'] ?? 'text' ); ?>" name="<?php echo $key; ?>" id="inquiry_<?php echo $key; ?>" placeholder="<?php echo $placeholder; ?>" <?php echo $required ? 'required' : ''; ?>>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>

            <p class="form-row">
                <label for="inquiry_message">Ihre Nachricht</label>
                <textarea name="message" id="inquiry_message" rows="4"></textarea>
            </p>

            <?php if ( $mlw_privacy_required ) : ?>
                <p class="form-row form-row-privacy">
                    <label>
                        <input type="checkbox" name="privacy_consent" id="inquiry_privacy_consent" value="1" required>
                        <?php echo wp_kses_post( $mlw_privacy_text ); ?>
                    </label>
                </p>
            <?php endif; ?>

            <p class="form-row">
                <button type="submit" class="button"><?php echo esc_html( $mlw_submit_label ); ?></button>
            </p>
            
            <div class="inquiry-message" style="display:none;"></div>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#catalog-inquiry-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $btn = $form.find('button');
                var $msg = $('.inquiry-message');

                // Alle Formularfelder generisch einsammeln (Basisfelder + konfigurierte
                // Zusatzfelder + Datenschutz-Checkbox) statt einzeln aufzuzählen -
                // damit neue, im Backend hinzugefügte Felder automatisch mitgeschickt werden.
                var data = {
                    action: 'wc_catalog_inquiry',
                    nonce: '<?php echo wp_create_nonce('wc_catalog_inquiry'); ?>'
                };
                $form.find('input[name], textarea[name], select[name]').each(function() {
                    var $el = $(this);
                    if ($el.attr('type') === 'checkbox') {
                        data[$el.attr('name')] = $el.is(':checked') ? '1' : '';
                    } else {
                        data[$el.attr('name')] = $el.val();
                    }
                });

                $btn.prop('disabled', true).text('Wird gesendet...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            $msg.html('<p class="success">' + response.data + '</p>').show();
                            $form.hide();
                            setTimeout(function() {
                                window.location.href = '<?php echo home_url(); ?>';
                            }, 3000);
                        } else {
                            $msg.html('<p class="error">' + response.data + '</p>').show();
                            $btn.prop('disabled', false).text('<?php echo esc_js( $mlw_submit_label ); ?>');
                        }
                    }
                });
            });
        });
        </script>
        
    <?php else : ?>
        <p>Ihr Warenkorb ist leer.</p>
        <a href="<?php echo get_permalink(wc_get_page_id('shop')); ?>" class="button">Zum Shop</a>
    <?php endif; ?>
</div>
