<?php
/**
 * HPOS Fix: product_type Term-Cache-Priming
 *
 * BUG: WC_Product_Data_Store_CPT::get_product_type() ermittelt den
 * Produkttyp (simple/variable/grouped/...) über get_the_terms( $id, 'product_type' )
 * und cached das Ergebnis dauerhaft für den Rest des Requests in der
 * WooCommerce-eigenen 'products'-Cache-Gruppe (wp_cache_set, überschreibt
 * also nichts mehr danach).
 *
 * Ist der WordPress-Term-Relationship-Cache für dieses Produkt/Taxonomie-Paar
 * zum Zeitpunkt dieses Aufrufs noch nicht korrekt vorbefüllt, liefert
 * get_the_terms() fälschlich leer/false zurück — WooCommerce fällt dann
 * PERMANENT auf den Fallback-Typ "simple" zurück, selbst wenn das Produkt
 * korrekt als "variable" (o.ä.) getaggt ist und echte Variations-Kind-Produkte
 * existieren.
 *
 * Sichtbare Symptome:
 * - Variable Produkte zeigen keine Attribut-Auswahl (Dropdowns) auf der
 *   Single-Product-Seite, da WC_Product_Simple statt WC_Product_Variable
 *   instanziiert wird.
 * - $product->get_available_variations() / get_children() liefern leer.
 * - $product->get_attributes()[...]->get_options() liefert leere Arrays,
 *   obwohl Taxonomie-Terms korrekt zugeordnet sind.
 * - In Produkt-Loops (Shop/Kategorie/AJAX-Filter): variable Produkte zeigen
 *   fälschlich "In den Warenkorb" statt einen "Details ansehen"-Link, und
 *   die Sale-Badge-Prozentrechnung (get_variation_prices()) läuft leer,
 *   Preisspannen ("ab X,00 €") fehlen.
 *
 * URSACHE UND ZEITPUNKT-ABHÄNGIGKEIT (verifiziert per Debug-Log an echten
 * Requests, nicht nur WP-CLI-Simulation — Projekt at.janecka-2026, Juli 2026):
 *
 * Weder WP_Query's eingebautes Post-Term-Cache-Priming noch ein manueller,
 * gebündelter Aufruf von update_object_term_cache() mit voller Taxonomie-Liste
 * primen den Cache zuverlässig für 'product_type'. Nur ein TAXONOMIE-
 * SPEZIFISCHER, EINZELNER get_the_terms( $id, 'product_type' )-Aufruf je
 * Objekt-ID VOR dem ersten wc_get_product()-Aufruf ist zuverlässig.
 *
 * Der korrekte HOOK-ZEITPUNKT unterscheidet sich je nach Query-Typ:
 *
 * 1. SINGLE-PRODUCT-REQUESTS (is_product()):
 *    Der 'wp'-Hook (selbst mit Priorität 1) ist zu spät — irgendein Code
 *    (Redirects, SEO, Analytics o.ä.) lädt das Produkt bereits vorher und
 *    schreibt den falschen Typ dauerhaft in WooCommerce's eigenen Cache.
 *    Lösung: 'the_posts'-Filter (läuft direkt nach der Haupt-Query, bevor
 *    irgendjemand sonst Zugriff auf die Post-Objekte hat).
 *
 * 2. PRODUKT-ARCHIVE MIT tax_query (Shop, Kategorie, Marken-Archiv, Suche):
 *    'the_posts' reicht HIER NICHT — WooCommerce lädt bereits WÄHREND der
 *    Query-Ausführung selbst volle Produktobjekte (vermutlich für
 *    Lagerbestand-/Sichtbarkeitsfilter bei variablen Produkten über einen
 *    eigenen 'pre_get_posts'-Hook), was den Cache poisoned, bevor
 *    'the_posts' überhaupt feuert.
 *    Lösung: 'pre_get_posts' mit Priorität >10 (NICHT niedriger — sonst ist
 *    WooCommerce's eigene posts_per_page-Modifikation, z.B. via
 *    'loop_shop_per_page', noch nicht angewendet und die parallele
 *    ID-Query liest die falsche Seitengröße). Eine separate, schlanke
 *    ID-only-Query (fields=ids, gleiche query_vars) primt den Cache, BEVOR
 *    die eigentliche Haupt-Query ausgeführt wird.
 *
 * 3. CUSTOM QUERIES AUSSERHALB VON WP_Query-HOOKS (z.B. AJAX-Handler, die
 *    ihre eigene WP_Query bauen und danach direkt rendern):
 *    Priming NACH der fertigen WP_Query kommt aus demselben Grund wie (2)
 *    zu spät. Lösung: Zwei-Schritt-Ansatz — zuerst eine ID-only-Query mit
 *    denselben Args ausführen und primen, DANACH erst die eigentliche
 *    Produkt-Query. Siehe medialab_prime_and_query_products() unten für
 *    einen wiederverwendbaren Helper.
 *
 * Verwandter, separater HPOS-Bug (nicht Teil dieses Fixes): WC-GZD
 * get_delivery_time() liefert aus demselben Cache-Grund leer zurück — dafür
 * gibt es keinen zentralen Fix, sondern eine direkte $wpdb-Query als
 * Workaround an der jeweiligen Ausgabestelle.
 *
 * @package MediaLabWooCommerce
 */

defined( 'ABSPATH' ) || exit;

// ===========================================================================
// 1. SINGLE-PRODUCT + allgemeine Custom-Queries: 'the_posts'-Filter
// ===========================================================================

if ( ! function_exists( 'medialab_prime_product_type_term_cache' ) ) {

	/**
	 * Primt den product_type-Term-Cache für alle Produkt-IDs einer Query.
	 *
	 * Deckt Single-Product-Requests und alle WP_Query-Instanzen ab, bei
	 * denen WooCommerce NICHT bereits während der Query-Ausführung selbst
	 * Produktobjekte lädt (siehe Punkt 2 oben für die Ausnahme: tax_query-
	 * basierte Produkt-Archive brauchen zusätzlich den pre_get_posts-Fix
	 * weiter unten).
	 *
	 * @param  WP_Post[] $posts Posts der aktuellen Query.
	 * @param  WP_Query  $query Die Query-Instanz.
	 * @return WP_Post[] Unveränderte Posts (reiner Seiteneffekt-Fix).
	 */
	function medialab_prime_product_type_term_cache( array $posts, WP_Query $query ): array {
		if ( empty( $posts ) ) {
			return $posts;
		}

		$post_type = $query->get( 'post_type' );

		// post_type kann String ODER Array sein (z.B. bei kombinierten Queries).
		$relevant = is_array( $post_type )
			? in_array( 'product', $post_type, true )
			: 'product' === $post_type;

		if ( ! $relevant ) {
			return $posts;
		}

		foreach ( $posts as $post ) {
			$post_id = is_object( $post ) ? ( $post->ID ?? 0 ) : (int) $post;

			if ( $post_id ) {
				get_the_terms( $post_id, 'product_type' );
			}
		}

		return $posts;
	}

	add_filter( 'the_posts', 'medialab_prime_product_type_term_cache', 10, 2 );
}

// ===========================================================================
// 2. PRODUKT-ARCHIVE (Shop, Kategorie, Marke, Suche): 'pre_get_posts'
// ===========================================================================

if ( ! function_exists( 'medialab_prime_archive_product_type_cache' ) ) {

	/**
	 * Primt den product_type-Term-Cache VOR der Ausführung von
	 * tax_query-basierten Produkt-Haupt-Queries (Shop/Kategorie/Marken-
	 * Archiv/Suche), da 'the_posts' hierfür nachweislich zu spät kommt.
	 *
	 * Priorität 20 ist bewusst gewählt: WooCommerce's eigene
	 * Query-Modifikationen (u.a. posts_per_page via 'loop_shop_per_page')
	 * laufen typischerweise auf Standard-Priorität 10 und müssen VOR
	 * diesem Fix abgeschlossen sein, sonst liest die interne
	 * Priming-Hilfsquery eine falsche Seitengröße.
	 *
	 * @param WP_Query $query Die Haupt-Query.
	 */
	function medialab_prime_archive_product_type_cache( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$is_relevant = $query->is_post_type_archive( 'product' )
			|| $query->is_tax( get_object_taxonomies( 'product' ) )
			|| $query->is_search();

		if ( ! $is_relevant ) {
			return;
		}

		$id_query_vars = $query->query_vars;
		$id_query_vars['fields']         = 'ids';
		$id_query_vars['posts_per_page'] = $query->get( 'posts_per_page' ) ?: get_option( 'posts_per_page' );

		// Rekursionsvermeidung: die Hilfs-Query darf diesen Hook nicht erneut auslösen.
		remove_action( 'pre_get_posts', 'medialab_prime_archive_product_type_cache', 20 );
		$id_query = new WP_Query( $id_query_vars );
		add_action( 'pre_get_posts', 'medialab_prime_archive_product_type_cache', 20 );

		foreach ( $id_query->posts as $post_id ) {
			get_the_terms( $post_id, 'product_type' );
		}
	}

	add_action( 'pre_get_posts', 'medialab_prime_archive_product_type_cache', 20 );
}

// ===========================================================================
// 3. HELPER für Custom-Queries außerhalb des normalen Query-Lifecycles
//    (z.B. AJAX-Handler, die ihre eigene WP_Query bauen und direkt rendern)
// ===========================================================================

if ( ! function_exists( 'medialab_prime_and_query_products' ) ) {

	/**
	 * Führt eine Produkt-WP_Query sicher aus, inklusive korrektem
	 * product_type-Term-Cache-Priming VOR der eigentlichen Query.
	 *
	 * Für Code außerhalb des normalen WordPress-Query-Lifecycles (z.B.
	 * AJAX-Handler mit eigener WP_Query, die anschließend sofort rendern).
	 * Ein Priming NACH einer fertigen WP_Query mit tax_query kommt zu
	 * spät (siehe Erklärung oben, Punkt 3) — dieser Helper übernimmt den
	 * nötigen Zwei-Schritt-Ansatz (erst ID-only-Query primen, dann die
	 * eigentliche Query ausführen) automatisch.
	 *
	 * Beispiel:
	 *   $query = medialab_prime_and_query_products( [
	 *       'post_type'      => 'product',
	 *       'posts_per_page' => 12,
	 *       'tax_query'      => [ ... ],
	 *   ] );
	 *   while ( $query->have_posts() ) { $query->the_post(); ... }
	 *
	 * @param  array $args Standard-WP_Query-Argumente.
	 * @return WP_Query Die fertige, sicher geprimte Produkt-Query.
	 */
	function medialab_prime_and_query_products( array $args ): WP_Query {
		$id_query = new WP_Query( array_merge( $args, [ 'fields' => 'ids' ] ) );

		foreach ( $id_query->posts as $post_id ) {
			get_the_terms( $post_id, 'product_type' );
		}

		return new WP_Query( $args );
	}
}
