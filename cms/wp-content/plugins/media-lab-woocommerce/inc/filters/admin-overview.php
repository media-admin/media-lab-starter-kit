<?php
/**
 * Admin-Übersichtsseite: Produktfilter-Konfiguration
 *
 * @package MedialabWooFilters
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'mlwf_register_admin_page' );

function mlwf_register_admin_page(): void {
	add_submenu_page(
		'edit.php?post_type=product',
		'Filter-Übersicht',
		'Filter-Übersicht',
		'manage_woocommerce',
		'mlwf-filter-overview',
		'mlwf_render_admin_page'
	);
}

add_action( 'admin_head', function() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'product_page_mlwf-filter-overview' ) return;
	?>
	<style>
		.mlwf-overview { max-width: 1200px; }
		.mlwf-overview h1 { margin-bottom: 0.5rem; }
		.mlwf-overview .description { color: #666; margin-bottom: 1.5rem; }
		.mlwf-overview h2 { margin-top: 2rem; border-bottom: 1px solid #ddd; padding-bottom: 0.5rem; }

		.mlwf-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e0e0e0; margin-bottom: 2rem; }
		.mlwf-table th { background: #f6f7f7; padding: 10px 14px; text-align: left; font-weight: 600; border-bottom: 1px solid #e0e0e0; }
		.mlwf-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
		.mlwf-table tr:last-child td { border-bottom: none; }
		.mlwf-table tr:hover td { background: #fafafa; }
		.mlwf-table .level-0 td:first-child { font-weight: 600; }
		.mlwf-table .level-1 td:first-child { padding-left: 2rem; }
		.mlwf-table .level-2 td:first-child { padding-left: 3.5rem; }

		.ft { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin: 2px; }
		.ft-attr  { background: #e7f3ff; color: #0066cc; border: 1px solid #b3d4f5; }
		.ft-price { background: #f0faf0; color: #2d7a2d; border: 1px solid #b3ddb3; }
		.ft-brand { background: #fff3e0; color: #c17a00; border: 1px solid #f5d08a; }
		.ft-sub   { background: #f3e7ff; color: #6600cc; border: 1px solid #d4b3f5; }
		.ft-inh   { background: #f5f5f5; color: #888; border: 1px solid #ddd; font-style: italic; }
		.ft-none  { background: #fff0f0; color: #cc0000; border: 1px solid #f5b3b3; }

		.src { font-size: 11px; color: #999; }
		.edit-link { font-size: 12px; color: #2271b1; text-decoration: none; }
		.edit-link:hover { text-decoration: underline; }

		.legend { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; align-items: center; font-size: 13px; color: #555; }
	</style>
	<?php
} );

function mlwf_render_admin_page(): void {
	$labels = mlwf_get_attribute_labels();
	?>
	<div class="wrap mlwf-overview">
		<h1>Produktfilter-Übersicht</h1>
		<p class="description">Read-only Übersicht der Filter-Konfiguration. Klicke auf „Bearbeiten" um Filter anzupassen.</p>

		<?php
		$price_label    = MediaLab_Filter_Settings::label( 'price' );
		$category_label = MediaLab_Filter_Settings::label( 'category' );
		?>
		<div class="legend">
			<strong>Legende:</strong>
			<span><span class="ft ft-price"><?php echo esc_html( $price_label ); ?></span> Preis-Slider</span>
			<span><span class="ft ft-attr">Attribut</span> Produkt-Attribut</span>
			<span><span class="ft ft-brand">Marke</span> Marken-Filter</span>
			<span><span class="ft ft-sub"><?php echo esc_html( $category_label ); ?></span> Unterkategorie-Filter</span>
			<span><span class="ft ft-inh">vererbt</span> Von Elternebene übernommen</span>
			<span><span class="ft ft-none">Keine</span> Nicht konfiguriert</span>
		</div>

		<h2>Produktkategorien</h2>
		<table class="mlwf-table">
			<thead>
				<tr><th>Kategorie</th><th>Aktive Filter</th><th>Quelle</th><th></th></tr>
			</thead>
			<tbody>
				<?php
				$root_cats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0, 'orderby' => 'name' ] );
				if ( ! is_wp_error( $root_cats ) ) {
					foreach ( $root_cats as $cat ) {
						mlwf_render_term_row( $cat, 'product_cat', $labels, 0 );
					}
				}
				?>
			</tbody>
		</table>

		<?php if ( taxonomy_exists( 'product_brand' ) ) : ?>
		<h2>Marken</h2>
		<table class="mlwf-table">
			<thead>
				<tr><th>Marke</th><th>Aktive Filter</th><th>Quelle</th><th></th></tr>
			</thead>
			<tbody>
				<?php
				$brands = get_terms( [ 'taxonomy' => 'product_brand', 'hide_empty' => false, 'orderby' => 'name' ] );
				if ( ! is_wp_error( $brands ) ) {
					foreach ( $brands as $brand ) {
						mlwf_render_term_row( $brand, 'product_brand', $labels, 0 );
					}
				}
				?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
	<?php
}

function mlwf_render_term_row( WP_Term $term, string $taxonomy, array $labels, int $level ): void {
	$config    = mlwf_resolve_filter_config( $term->term_id, $taxonomy, [] );
	$edit_url  = get_edit_term_link( $term->term_id, $taxonomy );
	$inherited = str_starts_with( $config['source'], 'parent:' );
	$is_default = $config['source'] === 'default';

	echo '<tr class="level-' . $level . '">';
	echo '<td>' . esc_html( $term->name ) . ' <span class="src">(' . $term->count . ')</span></td>';

	// Filter-Tags
	echo '<td>';
	if ( $is_default && empty( $config['attributes'] ) ) {
		echo '<span class="ft ft-none">Keine konfiguriert</span>';
	} else {
		$cls = $inherited ? 'ft-inh' : '';

		if ( $config['show_price'] )
			echo '<span class="ft ft-price ' . $cls . '">' . esc_html( MediaLab_Filter_Settings::label( 'price' ) ) . '</span> ';

		if ( $config['show_subcategories'] )
			echo '<span class="ft ft-sub ' . $cls . '">' . esc_html( MediaLab_Filter_Settings::label( 'category' ) ) . '</span> ';

		foreach ( $config['attributes'] as $slug ) {
			$label    = $labels[ $slug ] ?? $slug;
			$type_cls = in_array( $slug, [ 'product_brand', 'pa_brand', 'pa_marke' ], true ) ? 'ft-brand' : 'ft-attr';
			echo '<span class="ft ' . $type_cls . ' ' . $cls . '">' . esc_html( $label ) . '</span> ';
		}

		if ( empty( $config['attributes'] ) && ! $config['show_price'] ) {
			echo '<span class="ft ft-none">Keine Filter aktiv</span>';
		}
	}
	echo '</td>';

	// Quelle
	echo '<td><span class="src">';
	if ( $inherited ) {
		$parent = get_term( (int) str_replace( 'parent:', '', $config['source'] ), $taxonomy );
		echo '↑ ' . esc_html( $parent && ! is_wp_error( $parent ) ? $parent->name : 'Elternelement' );
	} elseif ( $is_default ) {
		echo 'Standard-Fallback';
	} else {
		echo 'Eigene Konfiguration';
	}
	echo '</span></td>';

	echo '<td><a href="' . esc_url( $edit_url ) . '" class="edit-link">Bearbeiten →</a></td>';
	echo '</tr>';

	// Nur für product_cat: Unterkategorien rekursiv
	if ( $taxonomy === 'product_cat' ) {
		$children = get_terms( [ 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => false, 'orderby' => 'name' ] );
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child ) {
				mlwf_render_term_row( $child, $taxonomy, $labels, $level + 1 );
			}
		}
	}
}
