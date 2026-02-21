<?php
/**
 * Anfrageformular (Catalog Mode)
 */
defined('ABSPATH') || exit;
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
            
            <p class="form-row">
                <label for="inquiry_message">Ihre Nachricht</label>
                <textarea name="message" id="inquiry_message" rows="4"></textarea>
            </p>
            
            <p class="form-row">
                <button type="submit" class="button">Anfrage senden</button>
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
                
                $btn.prop('disabled', true).text('Wird gesendet...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wc_catalog_inquiry',
                        nonce: '<?php echo wp_create_nonce('wc_catalog_inquiry'); ?>',
                        name: $form.find('[name="name"]').val(),
                        email: $form.find('[name="email"]').val(),
                        phone: $form.find('[name="phone"]').val(),
                        message: $form.find('[name="message"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $msg.html('<p class="success">' + response.data + '</p>').show();
                            $form.hide();
                            setTimeout(function() {
                                window.location.href = '<?php echo home_url(); ?>';
                            }, 3000);
                        } else {
                            $msg.html('<p class="error">' + response.data + '</p>').show();
                            $btn.prop('disabled', false).text('Anfrage senden');
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
