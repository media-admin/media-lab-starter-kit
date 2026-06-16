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
						<?php esc_html_e( 'Preis', 'medialab-woo-filters' ); ?>
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
						<?php esc_html_e( 'Kategorie', 'medialab-woo-filters' ); ?>
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
				<?php esc_html_e( 'Zurücksetzen', 'medialab-woo-filters' ); ?>
			</button>

		</form>

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

// Alias für Theme-Kompatibilität
function janecka_render_filter_bar(): void {
	mlwf_render_filter_bar();
}
