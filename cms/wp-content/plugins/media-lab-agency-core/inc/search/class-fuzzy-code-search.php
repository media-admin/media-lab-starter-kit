<?php
/**
 * Fuzzy-Toleranz für Produktcode-Suche (Tippfehler-Toleranz).
 *
 * Läuft NUR als Fallback, wenn die reguläre Exact-/LIKE-Suche in
 * agency_core_ajax_search() null Treffer liefert - siehe Hook-Punkt
 * 'media_lab_ajax_search_extra_matches' in inc/ajax-search.php
 * (Patch-Hinweis separat).
 *
 * Zwei-Stufen-Ansatz gegen Performance-Probleme bei großen Katalogen
 * (Projektgrößen reichen von ~1.500 bis ~150.000 Produkten):
 *   1. SQL grenzt Kandidaten grob über Längen-Differenz ein (nutzt den
 *      meta_key-Index von wp_postmeta, kein WP_Query-Overhead, keine
 *      Notwendigkeit für einen eigenen Cache - die Query ist schon
 *      durch LIMIT + Index günstig genug, und läuft ohnehin nur im
 *      Leerfall, nicht bei jedem Tastendruck).
 *   2. Erst auf dieser kleinen Teilmenge läuft levenshtein() in PHP.
 *
 * Datei ablegen unter: inc/search/class-fuzzy-code-search.php
 * Einbindung: require_once in media-lab-agency-core.php, im
 * inc/search/-Block (analog zu inc/filters/), plus
 * add_action('init', ['MediaLab_Fuzzy_Code_Search', 'init']).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Fuzzy_Code_Search {

	/** @var int Max. Levenshtein-Distanz, die noch als Treffer zählt. */
	const DEFAULT_MAX_DISTANCE = 2;

	/** @var int Obergrenze für SQL-vorgefilterte Kandidaten (Schutz vor Vollscan bei sehr großen Katalogen). */
	const CANDIDATE_LIMIT = 500;

	public static function init(): void {
		add_filter( 'media_lab_ajax_search_extra_matches', [ __CLASS__, 'maybe_add_fuzzy_matches' ], 10, 3 );
	}

	/**
	 * Hängt sich in die Suche ein. Wird laut Hook-Vertrag in ajax-search.php
	 * NUR aufgerufen, wenn bisherige Matches (Content + Attribute) leer sind -
	 * die Leer-Prüfung passiert im Aufrufer, nicht hier.
	 *
	 * @param array  $matches       post_id => Anzeige-Text (bisher leer).
	 * @param string $search_query  Roh-Suchbegriff.
	 * @param int    $limit         Wie viele Treffer maximal gebraucht werden.
	 * @return array<int,string>
	 */
	public static function maybe_add_fuzzy_matches( array $matches, string $search_query, int $limit ): array {
		if ( ! class_exists( 'WooCommerce' ) ) return $matches;
		if ( mb_strlen( $search_query ) < 3 ) return $matches; // zu kurz -> zu viele False Positives

		$field_key    = apply_filters( 'media_lab_search_code_field', '_sku' );
		$max_distance = (int) apply_filters( 'media_lab_search_code_fuzzy_distance', self::DEFAULT_MAX_DISTANCE );

		$candidates = self::get_length_filtered_candidates( $field_key, $search_query );
		if ( empty( $candidates ) ) return $matches;

		$needle = mb_strtolower( $search_query );
		$found  = array();

		foreach ( $candidates as $post_id => $code ) {
			$distance = levenshtein( $needle, mb_strtolower( (string) $code ) );
			if ( $distance <= $max_distance ) {
				$found[ (int) $post_id ] = sprintf(
					/* translators: %s: gefundener Produktcode */
					__( 'Code: %s', 'media-lab-core' ),
					$code
				);
			}
		}

		if ( empty( $found ) ) return $matches;

		// Sortierung nach Ähnlichkeit (kleinste Distanz zuerst) statt Post-ID-
		// Reihenfolge. levenshtein() wird hier bewusst ein zweites Mal berechnet,
		// statt die Distanz zwischenzuspeichern - das würde die Rückgabestruktur
		// (post_id => Anzeige-Text) verändern und mit dem Merge-Pattern in
		// ajax-search.php kollidieren. Bei max. CANDIDATE_LIMIT Kandidaten ist
		// der Mehraufwand vernachlässigbar.
		uksort( $found, function( $a, $b ) use ( $candidates, $needle ) {
			$dist_a = levenshtein( $needle, mb_strtolower( (string) ( $candidates[ $a ] ?? '' ) ) );
			$dist_b = levenshtein( $needle, mb_strtolower( (string) ( $candidates[ $b ] ?? '' ) ) );
			return $dist_a <=> $dist_b;
		} );

		return array_slice( $found, 0, $limit, true );
	}

	/**
	 * Grenzt Kandidaten VOR dem teuren levenshtein()-Vergleich per SQL ein:
	 * nur Codes, deren Länge um max. 2 Zeichen von der Suchbegriff-Länge
	 * abweicht, kommen überhaupt in die PHP-Schleife.
	 *
	 * Direktes $wpdb statt WP_Query/get_posts(): wir brauchen hier nur
	 * post_id + meta_value, kein vollständiges Post-Objekt-Hydrieren für
	 * bis zu CANDIDATE_LIMIT Kandidaten.
	 *
	 * @return array<int,string> post_id => Code
	 */
	private static function get_length_filtered_candidates( string $field_key, string $search_query ): array {
		global $wpdb;

		$target_length = mb_strlen( $search_query );
		$min_length    = max( 1, $target_length - 2 );
		$max_length    = $target_length + 2;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $field_key kommt nur
		// aus apply_filters() im eigenen Code, nicht aus User-Input; alle
		// tatsächlichen Werte sind über prepare() als Platzhalter gesetzt.
		$sql = $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = %s
			   AND LENGTH(meta_value) BETWEEN %d AND %d
			   AND meta_value != ''
			 LIMIT %d",
			$field_key,
			$min_length,
			$max_length,
			self::CANDIDATE_LIMIT
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! $rows ) return array();

		$candidates = array();
		foreach ( $rows as $row ) {
			$candidates[ (int) $row['post_id'] ] = (string) $row['meta_value'];
		}
		return $candidates;
	}
}
