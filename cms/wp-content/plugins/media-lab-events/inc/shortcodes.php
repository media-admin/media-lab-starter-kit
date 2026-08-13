<?php
/**
 * Event Shortcodes
 */

if (!defined('ABSPATH')) exit;

/**
 * Events Grid
 * 
 * Usage: [events_grid columns="3" limit="6" category="workshop" show_past="false"]
 */
function media_lab_events_grid_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns'    => '3',
        'limit'      => '6',
        'category'   => '',
        'show_past'  => 'false',
        'orderby'    => 'event_date_start',
        'order'      => 'ASC',
    ), $atts);

    $args = array(
        'post_type'      => 'event',
        'posts_per_page' => intval($atts['limit']),
        'post_status'    => 'publish',
        'meta_key'       => 'event_date_start',
        'orderby'        => 'meta_value',
        // meta_type => 'DATETIME' sorgt für eine korrekte chronologische
        // MySQL-seitige Sortierung des event_date_start-Feldes (das ACF
        // intern als 'Y-m-d H:i:s' speichert), statt einer reinen
        // String-Sortierung ohne Typ-Hinweis.
        'meta_type'      => 'DATETIME',
        'order'          => $atts['order'],
    );

    // Filter vergangene Events
    //
    // WICHTIG: Vergleichswert muss zum in der DB gespeicherten Format
    // passen (ACF speichert date_time_picker-Werte immer als
    // 'Y-m-d H:i:s', unabhängig vom im ACF-Feld konfigurierten
    // return_format). Vorher wurde hier date('d.m.Y H:i') verglichen -
    // das deutsche Format ist weder chronologisch string-sortierbar noch
    // passend zum tatsächlich gespeicherten DB-Format, wodurch der
    // "vergangene Events ausblenden"-Filter je nach Tag/Monat-Konstellation
    // zufällig falsche Events ein-/ausgeblendet hat. current_time('mysql')
    // liefert das korrekte 'Y-m-d H:i:s'-Format in der WordPress-Zeitzone.
    if ($atts['show_past'] !== 'true') {
        $args['meta_query'] = array(array(
            'key'     => 'event_date_start',
            'value'   => current_time('mysql'),
            'compare' => '>=',
            'type'    => 'DATETIME',
        ));
    }

    // Kategorie Filter
    if ($atts['category']) {
        $args['tax_query'] = array(array(
            'taxonomy' => 'event_category',
            'field'    => 'slug',
            'terms'    => explode(',', $atts['category']),
        ));
    }

    $query = new WP_Query($args);

    ob_start();

    if ($query->have_posts()) {
        echo '<div class="events-grid events-grid--columns-' . esc_attr($atts['columns']) . '">';

        while ($query->have_posts()) {
            $query->the_post();
            $post_id    = get_the_ID();

            // get_field() liefert seit dem return_format-Fix in inc/acf.php
            // 'Y-m-d H:i:s' statt eines bereits fertig formatierten deutschen
            // Datums - für die Anzeige hier zurück ins gewohnte Format
            // konvertieren.
            $date_start_raw = get_field('event_date_start', $post_id);
            $date_end_raw   = get_field('event_date_end', $post_id);
            $date_start = $date_start_raw ? date_i18n('d.m.Y H:i', strtotime($date_start_raw)) : '';
            $date_end   = $date_end_raw ? date_i18n('d.m.Y H:i', strtotime($date_end_raw)) : '';

            $location   = get_field('event_location', $post_id);
            $price      = get_field('event_price', $post_id);
            $thumbnail  = get_the_post_thumbnail_url($post_id, 'medium_large');
            $categories = get_the_terms($post_id, 'event_category');
            ?>
            <article class="event-card">

                <?php if ($thumbnail) : ?>
                <div class="event-card__thumbnail">
                    <a href="<?php the_permalink(); ?>">
                        <img src="<?php echo esc_url($thumbnail); ?>"
                             alt="<?php the_title_attribute(); ?>"
                             loading="lazy">
                    </a>
                    <?php if ($price) : ?>
                        <span class="event-card__price"><?php echo esc_html($price); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="event-card__content">

                    <?php if ($categories && !is_wp_error($categories)) : ?>
                    <div class="event-card__categories">
                        <?php foreach ($categories as $cat) : ?>
                            <span class="event-card__category"><?php echo esc_html($cat->name); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <h3 class="event-card__title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>

                    <div class="event-card__meta">
                        <?php if ($date_start) : ?>
                        <div class="event-card__date">
                            <span class="event-card__date-icon">📅</span>
                            <span><?php echo esc_html($date_start); ?>
                                <?php if ($date_end) : ?>
                                    – <?php echo esc_html($date_end); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php if ($location) : ?>
                        <div class="event-card__location">
                            <span class="event-card__location-icon">📍</span>
                            <span><?php echo esc_html($location); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (has_excerpt()) : ?>
                    <div class="event-card__excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>" class="event-card__link">
                        Details ansehen →
                    </a>

                </div>
            </article>
            <?php
        }

        echo '</div>';
        wp_reset_postdata();

    } else {
        echo '<p class="events-no-results">Keine Events gefunden.</p>';
    }

    return ob_get_clean();
}
add_shortcode('events_grid', 'media_lab_events_grid_shortcode');


/**
 * Single Event (für template parts)
 * 
 * Usage: [event_detail id="123"]
 */
function media_lab_event_detail_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => get_the_ID(),
    ), $atts);

    $post_id = intval($atts['id']);

    // Siehe media_lab_events_grid_shortcode() weiter oben: get_field()
    // liefert seit dem return_format-Fix 'Y-m-d H:i:s', hier für die
    // Anzeige zurück ins deutsche Format konvertiert.
    $date_start_raw = get_field('event_date_start', $post_id);
    $date_end_raw   = get_field('event_date_end', $post_id);
    $date_start = $date_start_raw ? date_i18n('d.m.Y H:i', strtotime($date_start_raw)) : '';
    $date_end   = $date_end_raw ? date_i18n('d.m.Y H:i', strtotime($date_end_raw)) : '';

    $location   = get_field('event_location', $post_id);
    $price      = get_field('event_price', $post_id);

    ob_start();
    ?>
    <div class="event-detail">
        <div class="event-detail__meta">
            <?php if ($date_start) : ?>
            <div class="event-detail__meta-item">
                <strong>📅 Datum:</strong>
                <?php echo esc_html($date_start); ?>
                <?php if ($date_end) echo ' – ' . esc_html($date_end); ?>
            </div>
            <?php endif; ?>

            <?php if ($location) : ?>
            <div class="event-detail__meta-item">
                <strong>📍 Ort:</strong>
                <?php echo esc_html($location); ?>
            </div>
            <?php endif; ?>

            <?php if ($price) : ?>
            <div class="event-detail__meta-item">
                <strong>🎫 Preis:</strong>
                <?php echo esc_html($price); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('event_detail', 'media_lab_event_detail_shortcode');
