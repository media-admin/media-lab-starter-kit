<?php
/**
 * Anfrageformular Checkout (Catalog Mode)
 */
get_header();
?>

<div class="woocommerce">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:2rem;">
        <h1 class="page-title" style="margin-bottom:2rem;">Produktanfrage</h1>
        
        <?php if (WC()->cart->get_cart_contents_count() > 0) : ?>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
                
                <!-- Produkt-Übersicht -->
                <div class="inquiry-cart-review" style="background:#fff;padding:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-bottom:1.5rem;font-size:20px;">Ihre Produktanfrage</h2>
                    
                    <form id="cart-update-form">
                        <table class="shop_table" style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="border-bottom:2px solid #e0e0e0;">
                                    <th style="text-align:left;padding:1rem 0;">Produkt</th>
                                    <th style="text-align:center;padding:1rem 0;">Menge</th>
                                    <th style="text-align:right;padding:1rem 0;">Preis</th>
                                    <th style="text-align:right;padding:1rem 0;">Gesamt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $cart_subtotal = 0;
                                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) : 
                                    $product = $cart_item['data'];
                                    $product_id = $cart_item['product_id'];
                                    $quantity = $cart_item['quantity'];
                                    $price = $product->get_price();
                                    $subtotal = $price * $quantity;
                                    $cart_subtotal += $subtotal;
                                ?>
                                    <tr style="border-bottom:1px solid #e0e0e0;" data-key="<?php echo esc_attr($cart_item_key); ?>">
                                        <td style="padding:1rem 0;">
                                            <div style="display:flex;gap:1rem;align-items:center;">
                                                <?php 
                                                $thumbnail = $product->get_image('thumbnail');
                                                if ($thumbnail) {
                                                    echo '<div style="width:60px;height:60px;flex-shrink:0;border-radius:8px;overflow:hidden;">' . $thumbnail . '</div>';
                                                }
                                                ?>
                                                <div>
                                                    <strong style="display:block;margin-bottom:0.25rem;"><?php echo esc_html($product->get_name()); ?></strong>
                                                    <?php
                                                    if ($cart_item['variation_id']) {
                                                        echo '<small style="color:#666;">';
                                                        echo wc_get_formatted_cart_item_data($cart_item);
                                                        echo '</small>';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:center;padding:1rem 0;">
                                            <input type="number" 
                                                   name="cart[<?php echo esc_attr($cart_item_key); ?>][qty]" 
                                                   value="<?php echo esc_attr($quantity); ?>" 
                                                   min="1" 
                                                   class="qty-input"
                                                   data-key="<?php echo esc_attr($cart_item_key); ?>"
                                                   style="width:70px;text-align:center;padding:0.5rem;border:2px solid #e0e0e0;border-radius:8px;font-size:16px;font-weight:600;">
                                        </td>
                                        <td style="text-align:right;padding:1rem 0;">
                                            <?php echo wc_price($price); ?>
                                        </td>
                                        <td style="text-align:right;padding:1rem 0;" class="line-total">
                                            <strong><?php echo wc_price($subtotal); ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <?php
                                // MwSt berechnen
                                $tax_rate = 20; // Österreich Standard-Satz
                                $tax_enabled = wc_tax_enabled();
                                
                                if ($tax_enabled) {
                                    $tax_amount = $cart_subtotal * ($tax_rate / 100);
                                    $cart_total = $cart_subtotal + $tax_amount;
                                } else {
                                    // Falls MwSt deaktiviert, aus Preis rausrechnen
                                    $net_amount = $cart_subtotal / (1 + $tax_rate / 100);
                                    $tax_amount = $cart_subtotal - $net_amount;
                                    $cart_total = $cart_subtotal;
                                }
                                ?>
                                
                                <tr style="border-top:1px solid #e0e0e0;">
                                    <td colspan="3" style="text-align:right;padding:1rem 0;font-weight:600;">
                                        Zwischensumme:
                                    </td>
                                    <td style="text-align:right;padding:1rem 0;" class="cart-subtotal">
                                        <strong><?php echo wc_price($cart_subtotal); ?></strong>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td colspan="3" style="text-align:right;padding:0.5rem 0;color:#666;font-size:14px;">
                                        inkl. MwSt (<?php echo $tax_rate; ?>%):
                                    </td>
                                    <td style="text-align:right;padding:0.5rem 0;color:#666;font-size:14px;" class="cart-tax">
                                        <?php echo wc_price($tax_amount); ?>
                                    </td>
                                </tr>
                                
                                <tr style="border-top:2px solid #e0e0e0;">
                                    <td colspan="3" style="text-align:right;padding:1.5rem 0;font-size:18px;font-weight:700;">
                                        Gesamtsumme:
                                    </td>
                                    <td style="text-align:right;padding:1.5rem 0;font-size:20px;font-weight:700;color:red;" class="cart-total">
                                        <?php echo wc_price($cart_total); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </form>
                    
                    <div style="margin-top:1.5rem;padding:1rem;background:#f8f9fa;border-radius:8px;font-size:14px;color:#666;">
                        <strong>Hinweis:</strong> Dies ist eine unverbindliche Anfrage. Sie erhalten ein individuelles Angebot per E-Mail.
                    </div>
                </div>
                
                <!-- Kontaktformular -->
                <div>
                    <form id="catalog-inquiry-form" class="inquiry-form" style="background:#fff;padding:2rem;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                        <h2 style="margin-bottom:1.5rem;font-size:20px;">Ihre Kontaktdaten</h2>
                        
                        <p class="form-row" style="margin-bottom:1.5rem;">
                            <label for="inquiry_name" style="display:block;margin-bottom:0.5rem;font-weight:600;">
                                Name <span class="required" style="color:red;">*</span>
                            </label>
                            <input type="text" name="name" id="inquiry_name" class="input-text" required 
                                style="width:100%;padding:0.75rem;border:2px solid #e0e0e0;border-radius:8px;font-size:16px;"
                                placeholder="Max Mustermann">
                        </p>
                        
                        <p class="form-row" style="margin-bottom:1.5rem;">
                            <label for="inquiry_email" style="display:block;margin-bottom:0.5rem;font-weight:600;">
                                E-Mail <span class="required" style="color:red;">*</span>
                            </label>
                            <input type="email" name="email" id="inquiry_email" class="input-text" required 
                                style="width:100%;padding:0.75rem;border:2px solid #e0e0e0;border-radius:8px;font-size:16px;"
                                placeholder="max@beispiel.de">
                        </p>
                        
                        <p class="form-row" style="margin-bottom:1.5rem;">
                            <label for="inquiry_phone" style="display:block;margin-bottom:0.5rem;font-weight:600;">
                                Telefonnummer
                            </label>
                            <input type="tel" name="phone" id="inquiry_phone" class="input-text" 
                                style="width:100%;padding:0.75rem;border:2px solid #e0e0e0;border-radius:8px;font-size:16px;"
                                placeholder="+43 123 456789">
                        </p>
                        
                        <p class="form-row" style="margin-bottom:1.5rem;">
                            <label for="inquiry_message" style="display:block;margin-bottom:0.5rem;font-weight:600;">
                                Ihre Nachricht
                            </label>
                            <textarea name="message" id="inquiry_message" rows="5" class="input-text" 
                                style="width:100%;padding:0.75rem;border:2px solid #e0e0e0;border-radius:8px;font-size:16px;resize:vertical;"
                                placeholder="Wann können Sie liefern?"></textarea>
                        </p>
                        
                        <p class="form-row">
                            <button type="submit" class="button alt" 
                                style="width:100%;padding:1rem 2rem;background:red;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:18px;cursor:pointer;transition:all 0.2s ease;">
                                Anfrage absenden
                            </button>
                        </p>
                        
                        <div class="inquiry-message" style="margin-top:20px;display:none;"></div>
                    </form>
                </div>
                
            </div>
            
            <style>
                @media (max-width: 768px) {
                    .container > div {
                        grid-template-columns: 1fr !important;
                    }
                    .shop_table td:first-child > div {
                        flex-direction: column;
                        align-items: flex-start !important;
                    }
                }
                
                .qty-input:focus {
                    outline: none;
                    border-color: red;
                    box-shadow: 0 0 0 3px rgba(255,0,0,0.1);
                }
                
                #catalog-inquiry-form input:focus,
                #catalog-inquiry-form textarea:focus {
                    outline: none;
                    border-color: red;
                    box-shadow: 0 0 0 3px rgba(255,0,0,0.1);
                }
                
                #catalog-inquiry-form button:hover {
                    background: #c00;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(255,0,0,0.3);
                }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                // Warenkorb aktualisieren bei Mengenänderung
                var updateTimeout;
                $('.qty-input').on('change', function() {
                    clearTimeout(updateTimeout);
                    var $input = $(this);
                    var cart_item_key = $input.data('key');
                    var quantity = parseInt($input.val());
                    
                    if (quantity < 1) {
                        quantity = 1;
                        $input.val(1);
                    }
                    
                    updateTimeout = setTimeout(function() {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'update_cart_quantity',
                                cart_item_key: cart_item_key,
                                quantity: quantity,
                                nonce: '<?php echo wp_create_nonce('update_cart_quantity'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Seite neu laden für aktualisierte Summen
                                    location.reload();
                                }
                            }
                        });
                    }, 500);
                });
                
                // Anfrage absenden
                $('#catalog-inquiry-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var $form = $(this);
                    var $btn = $form.find('button');
                    var $msg = $('.inquiry-message');
                    
                    $btn.prop('disabled', true).text('Wird gesendet...');
                    $msg.hide();
                    
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
                                $msg.html('<div style="padding:1rem;background:#d4edda;border:1px solid #c3e6cb;color:#155724;border-radius:8px;"><strong>✓ Erfolg!</strong> ' + response.data + '</div>').show();
                                $form.hide();
                                setTimeout(function() {
                                    window.location.href = '<?php echo home_url(); ?>';
                                }, 3000);
                            } else {
                                $msg.html('<div style="padding:1rem;background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;border-radius:8px;"><strong>⚠ Fehler:</strong> ' + response.data + '</div>').show();
                                $btn.prop('disabled', false).text('Anfrage absenden');
                            }
                        },
                        error: function() {
                            $msg.html('<div style="padding:1rem;background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;border-radius:8px;"><strong>⚠ Fehler:</strong> Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es erneut.</div>').show();
                            $btn.prop('disabled', false).text('Anfrage absenden');
                        }
                    });
                });
            });
            </script>
            
        <?php else : ?>
            <div style="text-align:center;padding:4rem 2rem;background:#fff;border-radius:12px;">
                <p style="font-size:18px;color:#666;margin-bottom:2rem;">Ihr Warenkorb ist leer.</p>
                <a href="<?php echo get_permalink(wc_get_page_id('shop')); ?>" 
                   class="button" 
                   style="display:inline-block;padding:1rem 2rem;background:red;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">
                    Zum Shop
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
