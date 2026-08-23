<?php
/**
 * Filter-Bar HTML
 *
 * Rendert die Filter-Bar für Kategorie- und Markenseiten.
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rendert die Filter-Bar.
 * Wird via Hook aus dem Theme eingebunden.
 */
function mlwf_render_filter_bar(): void {
	if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_tax( 'product_brand' ) ) ) {
		return;
	}

	$config          = mlwf_get_current_filter_config();
	$attribute_slugs = $config['attributes'];
	$show_price      = $config['show_price'];
	$show_subcats    = $config['show_subcategories'];
	$labels          = mlwf_get_attribute_labels();

	// Kontext ermitteln
	$is_brand        = is_tax( 'product_brand' );
	$category_slug   = is_product_category() ? get_queried_object()->slug : '';
	$brand_slug      = $is_brand ? get_queried_object()->slug : '';
	$queried_term_id = get_queried_object_id();

	// Aktive Attribute aus URL
	$active_attrs = [];
	foreach ( $attribute_slugs as $attr_slug ) {
		if ( ! empty( $_GET[ $attr_slug ] ) ) {
			$active_attrs[ $attr_slug ] = array_map( 'sanitize_text_field', explode( ',', $_GET[ $attr_slug ] ) );
		}
	}

	// Produkt-IDs des aktuellen Kontexts für Term-Counts
	$context_product_ids = mlwf_get_context_product_ids( $queried_term_id, $is_brand ? 'product_brand' : 'product_cat' );
	?>
	<div class="wc-filter-bar"
		id="wc-filter-sidebar"
		data-category="<?php echo esc_attr( $category_slug ); ?>"
		data-brand="<?php echo esc_attr( $brand_slug ); ?>">

		<form class="wc-filter-bar__form js-filter-form" novalidate>
			<input type="hidden" name="category" value="<?php echo esc_attr( $category_slug ); ?>">
			<input type="hidden" name="brand"    value="<?php echo esc_attr( $brand_slug ); ?>">

			<div class="wc-filter-bar__groups">

				<?php
				// ── Preis-Slider ──────────────────────────────────────────
				if ( $show_price ) :
				?>
				<div class="wc-filter-group wc-filter-group--price js-filter-group" data-filter-type="price">
					<button class="wc-filter-group__toggle" type="button" aria-expanded="false">
						<?php echo esc_html( MediaLab_Filter_Settings::label( 'price' ) ); ?>
						<span class="wc-filter-group__icon" aria-hidden="true"></span>
					</button>
					<div class="wc-filter-group__dropdown" hidden>
						<div class="wc-price-slider js-price-slider"></div>
						<div class="wc-price-inputs">
							<input class="wc-price-input js-price-min" type="number" name="price_min" min="0" step="1">
							<span class="wc-price-separator">–</span>
							<input class="wc-price-input js-price-max" type="number" name="price_max" min="0" step="1">
						</div>
					</div>
				</div>
				<?php endif; ?>

				<?php
				// ── Unterkategorie-Filter (nur auf product_cat) ───────────
				if ( $show_subcats && $category_slug ) :
					$subcategories = get_terms( [
						'taxonomy'   => 'product_cat',
						'parent'     => $queried_term_id,
						'hide_empty' => false,
						'orderby'    => 'name',
					] );

					if ( ! is_wp_error( $subcategories ) && ! empty( $subcategories ) ) :
						$active_subcat = sanitize_text_field( $_GET['subcat'] ?? '' );
						$group_id      = 'filter-drop-subcategories';
				?>
				<div class="wc-filter-group js-filter-group" data-filter-type="subcategory">
					<button class="wc-filter-group__toggle<?php echo $active_subcat ? ' has-active' : ''; ?>"
						type="button"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $group_id ); ?>">
						<?php echo esc_html( MediaLab_Filter_Settings::label( 'category' ) ); ?>
						<?php if ( $active_subcat ) : ?>
							<span class="wc-filter-group__count">1</span>
						<?php endif; ?>
						<span class="wc-filter-group__icon" aria-hidden="true"></span>
					</button>
					<div class="wc-filter-group__dropdown" id="<?php echo esc_attr( $group_id ); ?>" hidden>
						<ul class="wc-filter-checklist" role="group">
							<?php foreach ( $subcategories as $subcat ) :
								$input_id = 'filter-subcat-' . $subcat->slug;
							?>
							<li class="wc-filter-checklist__item">
								<label class="wc-filter-option" for="<?php echo esc_attr( $input_id ); ?>">
									<input class="wc-filter-option__checkbox"
										id="<?php echo esc_attr( $input_id ); ?>"
										type="radio"
										name="subcat"
										value="<?php echo esc_attr( $subcat->slug ); ?>"
										<?php checked( $active_subcat, $subcat->slug ); ?>>
									<span class="wc-filter-option__label"><?php echo esc_html( $subcat->name ); ?></span>
									<span class="wc-filter-option__count">(<?php echo absint( $subcat->count ); ?>)</span>
								</label>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<?php
					endif;
				endif;
				?>

				<?php
				// ── Attribut-Filter ───────────────────────────────────────
				foreach ( $attribute_slugs as $attr_slug ) :
					$taxonomy = get_taxonomy( $attr_slug );
					if ( ! $taxonomy ) continue;

					// Terms laden — gefiltert auf aktuellen Kontext
					if ( $queried_term_id ) {
						$context_term = get_term( $queried_term_id );
						$terms = get_terms( [
							'taxonomy'   => $attr_slug,
							'hide_empty' => true,
							'orderby'    => 'name',
							'object_ids' => get_objects_in_term(
								$context_term->term_id,
								$is_brand ? 'product_brand' : 'product_cat'
							),
						] );
					} else {
						$terms = get_terms( [
							'taxonomy'   => $attr_slug,
							'hide_empty' => true,
							'orderby'    => 'name',
						] );
					}

					if ( is_wp_error( $terms ) || empty( $terms ) ) continue;

					$label       = $labels[ $attr_slug ] ?? $taxonomy->labels->name;
					$active_vals = $active_attrs[ $attr_slug ] ?? [];
					$group_id    = 'filter-drop-' . esc_attr( $attr_slug );
				?>
				<div class="wc-filter-group js-filter-group" data-filter-type="attribute" data-attribute="<?php echo esc_attr( $attr_slug ); ?>">
					<button class="wc-filter-group__toggle<?php echo ! empty( $active_vals ) ? ' has-active' : ''; ?>"
						type="button"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $group_id ); ?>">
						<?php echo esc_html( $label ); ?>
						<?php if ( ! empty( $active_vals ) ) : ?>
							<span class="wc-filter-group__count"><?php echo count( $active_vals ); ?></span>
						<?php endif; ?>
						<span class="wc-filter-group__icon" aria-hidden="true"></span>
					</button>
					<div class="wc-filter-group__dropdown" id="<?php echo esc_attr( $group_id ); ?>" hidden>
						<ul class="wc-filter-checklist" role="group">
							<?php foreach ( $terms as $term ) :
								$input_id = 'filter-' . $attr_slug . '-' . $term->slug;
								$count    = ! empty( $context_product_ids )
									? mlwf_count_term_in_context( $term->term_id, $attr_slug, $context_product_ids )
									: $term->count;
								if ( $count === 0 ) continue;
							?>
							<li class="wc-filter-checklist__item">
								<label class="wc-filter-option" for="<?php echo esc_attr( $input_id ); ?>">
									<input class="wc-filter-option__checkbox"
										id="<?php echo esc_attr( $input_id ); ?>"
										type="checkbox"
										name="attributes[<?php echo esc_attr( $attr_slug ); ?>][]"
										value="<?php echo esc_attr( $term->slug ); ?>"
										<?php checked( in_array( $term->slug, $active_vals, true ) ); ?>>
									<span class="wc-filter-option__label"><?php echo esc_html( $term->name ); ?></span>
									<span class="wc-filter-option__count">(<?php echo absint( $count ); ?>)</span>
								</label>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<?php endforeach; ?>

			</div><!-- .wc-filter-bar__groups -->

			<button class="wc-filter-bar__reset js-filter-reset" type="button" hidden>
				<?php echo esc_html( MediaLab_Filter_Settings::label( 'reset' ) ); ?>
			</button>

		</form>

		<?php
		/**
		 * Sortier-Layout: Filter- und Sortier-Gruppe in zwei getrennten,
		 * nebeneinanderliegenden Containern - generisch sinnvoller Kandidat
		 * für den Starter Kit (im Gegensatz zu projektspezifischen Dingen
		 * wie der brand-is-active-Filterung).
		 *
		 * Bewusst AUSSERHALB von .js-filter-form: woocommerce_catalog_ordering()
		 * gibt selbst ein <form>-Tag aus, ein verschachteltes <form> wäre
		 * ungültiges HTML und würde vom Browser aufgebrochen. Zwei getrennte
		 * Gruppen-Container nebeneinander (per CSS) vermeiden das.
		 *
		 * Per apply_filters() abschaltbar, ohne filter-config.php anfassen zu
		 * müssen:
		 *   add_filter( 'mlwf_filter_bar_show_sort', '__return_false' );
		 *
		 * Hinweis: Für eine vollständig AJAX-integrierte Sortierung (ohne
		 * volles Page-Reload beim Wechsel) muss das Sortier-Dropdown zusätzlich
		 * im Filter-JS (ajax-filters.js) auf 'change' abgefangen und der Wert
		 * als orderby-Parameter in den bestehenden Filter-Request eingespeist
		 * werden - das JS lag bei dieser Umsetzung nicht vor, daher hier nur
		 * die Markup-Seite. Bis dahin sortiert das Dropdown per normalem
		 * WooCommerce-Verhalten (GET-Parameter + Page-Reload).
		 */
		if ( apply_filters( 'mlwf_filter_bar_show_sort', true ) ) :
		?>
		<div class="wc-filter-bar__groups-sort">
			<?php woocommerce_catalog_ordering(); ?>
		</div>
		<?php endif; ?>

		<div class="wc-active-filters js-active-filters"></div>

	</div><!-- .wc-filter-bar -->
	<?php
}

/**
 * Hilfsfunktion: Produkt-IDs eines Terms inkl. Kind-Terms.
 */
function mlwf_get_context_product_ids( int $term_id, string $taxonomy ): array {
	if ( ! $term_id ) return [];

	$child_ids = get_term_children( $term_id, $taxonomy );
	$all_ids   = array_merge( [ $term_id ], is_array( $child_ids ) ? $child_ids : [] );

	$product_ids = [];
	foreach ( $all_ids as $tid ) {
		$ids         = get_objects_in_term( $tid, $taxonomy );
		$product_ids = array_merge( $product_ids, is_array( $ids ) ? $ids : [] );
	}

	return array_unique( $product_ids );
}

/**
 * An 'woocommerce_before_shop_loop' gehängt statt manuell im Theme
 * eingebunden - macht die Filter-Bar sofort in jedem Projekt einsatzbereit,
 * ohne dass jedes Theme sie separat verdrahten muss (Starter-Kit-Ziel:
 * reusable, client-deployable). Priorität 15: nach woocommerce_output_all_notices
 * (10), an der Stelle, an der woocommerce_result_count (20) und
 * woocommerce_catalog_ordering (30) im Theme bereits entfernt wurden - die
 * Filter-Bar übernimmt das Sortier-Dropdown selbst (siehe woocommerce_catalog_ordering()
 * -Aufruf oben in dieser Datei).
 */
add_action( 'woocommerce_before_shop_loop', 'mlwf_render_filter_bar', 15 );

// Alias für Theme-Kompatibilität
function janecka_render_filter_bar(): void {
	mlwf_render_filter_bar();
}


/**
 * Öffnet/schließt einen Wrapper um ul.products + Pagination, den
 * mlwf-filters.js beim AJAX-Filtern per innerHTML ersetzt. Ohne diesen
 * Wrapper läuft der Request zwar durch, aber am Bildschirm ändert sich
 * sichtbar nichts.
 *
 * Priorität 20 auf 'woocommerce_before_shop_loop' (nach der Filter-Bar
 * selbst bei Priorität 15, direkt vor dem WooCommerce-eigenen Loop-Start).
 * Priorität 20 auf 'woocommerce_after_shop_loop' (nach der Custom-
 * Pagination des Themes bei Priorität 10 in inc/woocommerce.php).
 */
add_action( 'woocommerce_before_shop_loop', function () {
	echo '<div class="wc-products-container">';
}, 20 );

add_action( 'woocommerce_after_shop_loop', function () {
	echo '</div><!-- .wc-products-container -->';
}, 20 );