<?php
/**
 * Synonym-Wörterbuch für die Freitext-Suche (Begriffs-Äquivalenz ohne
 * Zeichenähnlichkeit, z.B. "sample bomb" <-> "bottle sampler",
 * "analyzer" <-> "analyser").
 *
 * Abgrenzung zu class-fuzzy-code-search.php: das eine fängt Tippfehler
 * auf Produktcodes (Zeichenähnlichkeit), das andere fängt Terminologie-
 * Unterschiede im Fließtext (keine Zeichenähnlichkeit, reines
 * Vokabular) - beide Probleme brauchen unterschiedliche Lösungen und
 * werden deshalb bewusst nicht in einer Klasse zusammengefasst.
 *
 * Konfiguration: ACF-Repeater unter WooCommerce Products > [Tab]
 * Synonyme (analoges Options-Page-Pattern wie Wording/Attribut-Labels/
 * Sprachen in class-settings.php). Struktur pro Sprachzeile: Liste von
 * Synonym-Gruppen, jede Gruppe ist ein Kommagetrennter Satz äquivalenter
 * Begriffe (nicht nur 1:1-Paare, damit z.B. "sample bomb",
 * "bottle sampler", "Probenflasche" gemeinsam eine Gruppe bilden
 * können statt nur paarweise verknüpft zu sein).
 *
 * Datei ablegen unter: inc/search/class-synonym-dictionary.php
 * Einbindung: require_once in media-lab-agency-core.php, im
 * inc/search/-Block, NACH inc/search/class-fuzzy-code-search.php, plus
 * add_action('init', ['MediaLab_Synonym_Dictionary', 'init']).
 *
 * Voraussetzung: ACF-Feldgruppe mit Feldname 'search_synonym_groups'
 * (Options-Page-Repeater, Sub-Felder 'language' + Repeater 'groups' mit
 * Textfeld 'terms') muss noch angelegt werden - siehe Hinweis am Ende
 * der Klasse.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Synonym_Dictionary {

	const ACF_OPTION_KEY = 'search_synonym_groups';

	public static function init(): void {
    add_filter( 'media_lab_ajax_search_query_expansion', [ __CLASS__, 'expand_query' ], 10, 2 );
    add_action( 'acf/init', [ __CLASS__, 'register_settings' ] );
}

/**
 * Eigene Sub-Page + Feldgruppe, analog zum Share-Buttons-Pattern
 * (inc/social-share.php) - vollständig selbst-enthalten statt zentral
 * in acf-settings.php verteilt, damit die Komponente atomar bleibt.
 */
public static function register_settings(): void {
    if ( ! function_exists( 'acf_add_options_sub_page' ) ) return;

    acf_add_options_sub_page( array(
        'page_title'  => 'Suche / Synonyme',
        'menu_title'  => 'Suche / Synonyme',
        'parent_slug' => 'agency-core',
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-search',
        'position'    => false,
        'redirect'    => false,
    ) );

    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( array(
        'key'    => 'group_search_synonyms',
        'title'  => 'Suche – Synonyme',
        'fields' => array(
            array(
                'key'          => 'field_search_synonym_groups',
                'label'        => 'Sprachen',
                'name'         => self::ACF_OPTION_KEY,
                'type'         => 'repeater',
                'button_label' => 'Sprache hinzufügen',
                'layout'       => 'block',
                'sub_fields'   => array(
					array(
                        'key'          => 'field_synonym_language',
                        'label'        => 'Sprache',
                        'name'         => 'language',
                        'type'         => 'text',
                        'placeholder'  => 'de',
                        'instructions' => 'Sprachcode, z.B. de, en, fr.',
                    ),
                    array(
                        'key'          => 'field_synonym_groups',
                        'label'        => 'Synonym-Gruppen',
                        'name'         => 'groups',
                        'type'         => 'repeater',
                        'button_label' => 'Gruppe hinzufügen',
                        'layout'       => 'table',
                        'sub_fields'   => array(
                            array(
                                'key'         => 'field_synonym_terms',
                                'label'       => 'Begriffe (kommagetrennt)',
                                'name'        => 'terms',
                                'type'        => 'text',
                                'placeholder' => 'sample bomb, bottle sampler',
                            ),
                        ),
                    ),
                ),
            ),
        ),
        'location' => array( array( array(
            'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-search',
        ) ) ),
        'position' => 'normal', 'style' => 'default',
        'label_placement' => 'top', 'instruction_placement' => 'label',
    ) );
}

	/**
	 * Liefert den Original-Suchbegriff plus alle Synonyme, die in
	 * derselben Gruppe stehen wie der eingegebene Suchbegriff. Die
	 * Rückgabe wird in ajax-search.php genutzt, um pro Begriff einen
	 * eigenen WP_Query-Durchlauf zu fahren und die Ergebnis-IDs zu
	 * mergen - siehe Patch-Hinweis für ajax-search.php.
	 *
	 * Matched aktuell nur den GESAMTEN Suchbegriff gegen eine Gruppe
	 * (nicht Wort-für-Wort) - "bottle sampler" als komplette Phrase
	 * matcht die Gruppe, ein Teilwort wie nur "bottle" nicht. Das
	 * entspricht dem beschriebenen Anwendungsfall (feste Fachbegriffe);
	 * Wort-für-Wort-Expansion wäre eine spätere Erweiterung, keine
	 * Voraussetzung dafür.
	 *
	 * @param string[] $terms         Bisher nur [$original_query].
	 * @param string   $search_query  Roh-Suchbegriff.
	 * @return string[] Eindeutige Liste von Suchbegriffen.
	 */
	public static function expand_query( array $terms, string $search_query ): array {
		$groups = self::get_groups_for_current_language();
		if ( empty( $groups ) ) return $terms;

		$needle = mb_strtolower( trim( $search_query ) );

		foreach ( $groups as $group ) {
			$lowercase_group = array_map( 'mb_strtolower', $group );
			if ( in_array( $needle, $lowercase_group, true ) ) {
				// Original-Schreibweise aus der ACF-Konfiguration übernehmen
				// (nicht die User-Eingabe) - konsistente Groß-/Kleinschreibung
				// für den WP_Query 's'-Parameter.
				$terms = array_merge( $terms, $group );
				break; // ein Begriff kann nur in einer Gruppe vorkommen
			}
		}

		return array_values( array_unique( $terms ) );
	}

	/**
	 * Sprachauflösung nutzt euer bestehendes resolve_row()-Fallback-Pattern
	 * aus dem Inquiry Engine 1:1 wieder: eine leere Sprachzeile fällt auf
	 * die erste KONFIGURIERTE Sprachzeile zurück, nicht direkt auf einen
	 * Hardcoded-Default (bestätigter Bug dort, siehe learnings.md - hier
	 * bewusst wiederverwendet statt neu erfunden).
	 *
	 * @return array<int,string[]> Liste von Synonym-Gruppen (jede Gruppe = string[]).
	 */
	private static function get_groups_for_current_language(): array {
		if ( ! function_exists( 'get_field' ) ) return array();

		$rows = get_field( self::ACF_OPTION_KEY, 'option' );
		if ( ! is_array( $rows ) || empty( $rows ) ) return array();

		// Nutzt Agency Cores eigene Spracherkennung (inc/multi-language.php).
		// Statisch aufrufbar, unabhängig davon, ob das Multilang-Feature-
		// Toggle aktiv ist - prüft intern nur, ob Polylang selbst aktiv ist.
		// Kein WPML-Fallback (anders als im Inquiry Engine) - Agency Core
		// unterstützt aktuell nur Polylang, siehe MediaLab_Multi_Language.
		$current_lang = class_exists( 'MediaLab_Multi_Language' )
			? MediaLab_Multi_Language::get_current_language()
			: 'de';

		$row = self::resolve_row( $rows, $current_lang );
		if ( ! $row || empty( $row['groups'] ) || ! is_array( $row['groups'] ) ) return array();

		$groups = array();
		foreach ( $row['groups'] as $group_row ) {
			$terms = array_filter( array_map( 'trim', explode( ',', (string) ( $group_row['terms'] ?? '' ) ) ) );
			if ( count( $terms ) >= 2 ) {
				$groups[] = array_values( $terms );
			}
		}
		return $groups;
	}

	private static function resolve_row( array $rows, string $lang ): ?array {
		foreach ( $rows as $row ) {
			if ( ( $row['language'] ?? '' ) === $lang && ! empty( $row['groups'] ) ) {
				return $row;
			}
		}
		// Fallback: erste konfigurierte Zeile mit Inhalt (siehe Klassenkommentar).
		foreach ( $rows as $row ) {
			if ( ! empty( $row['groups'] ) ) {
				return $row;
			}
		}
		return null;
	}
}

/**
 * ACF-Feldgruppe (per PHP oder Local JSON anlegen, analog zu den
 * bestehenden Tabs in class-settings.php):
 *
 * Options Page Tab "Synonyme"
 *   Repeater: search_synonym_groups
 *     - language (Select: eure 3 Hauptsprachen, Wert = Sprachcode wie 'de','en','fr')
 *     - groups (Repeater)
 *         - terms (Text, kommagetrennt: "sample bomb, bottle sampler, Probenflasche")
 */
