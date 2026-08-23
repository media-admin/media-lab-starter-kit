<?php
/**
 * Konfigurator → Attribut-Filter-Sync
 *
 * Synchronisiert Konfigurator-Step-Optionen mit einem kuratiert ausgewählten,
 * BESTEHENDEN WooCommerce-Attribut (siehe inc/configurator/class-acf-fields.php,
 * Felder 'use_as_filter' / 'filter_attribute' am config_steps-Repeater), damit
 * konfigurierbare Produkte über das bestehende Filter-System (inc/filters/)
 * filterbar werden.
 *
 * Bewusst KEINE Abhängigkeit in die andere Richtung: inc/filters/ weiß
 * nichts vom Konfigurator und muss es auch nicht - filter-config.php liest
 * ohnehin nur wc_get_attribute_taxonomies() plus die an Produkte gehängten
 * Terms. Dieser Sync sorgt nur dafür, dass die Terms überhaupt gesetzt sind.
 *
 * Kuratiert heißt hier: die TAXONOMIE wird manuell im Step ausgewählt (keine
 * automatische Taxonomie-Erzeugung aus dem Step-Label - Dubletten-Risiko).
 * Die einzelnen OPTIONS-WERTE werden aber automatisch als Terms INNERHALB
 * dieser gewählten Taxonomie angelegt/wiederverwendet (Matching per Label,
 * case-insensitive über get_term_by('name', ...)) - die Options-Pflege im
 * Konfigurator bleibt dadurch unverändert (weiterhin freies Repeater).
 *
 * Brücke (Ansatz B, siehe Absprache): Options-'value' (Konfigurator-intern,
 * z.B. für Preislogik/Bedingungen) und der Taxonomie-Term-Slug sind komplett
 * getrennte Bezeichner - 'value' wird hier NIE angefasst. Stattdessen wird
 * die ermittelte term_id zusätzlich in ein eigenes, neues Feld pro Option
 * zurückgeschrieben ('filter_term_id', siehe class-acf-fields.php) - eine
 * stabile, explizite Referenz für künftige Features (z.B. Farbe im Wizard
 * vorauswählen, attributbasierte Preisregeln), ohne bestehende
 * Preis-/Bedingungs-Logik zu gefährden, die weiterhin auf 'value' aufbaut.
 *
 * ACHTUNG: wp_set_object_terms(..., false) setzt die Terms der Ziel-Taxonomie
 * am Produkt bei JEDEM Speichern vollständig neu. Wird dieselbe Taxonomie
 * zusätzlich manuell gepflegt (z.B. bei variablen Produkten), überschreibt
 * der nächste Speichervorgang diese Zuordnung. Ein Attribut daher entweder
 * vom Konfigurator ODER manuell/für Variationen pflegen, nicht gemischt.
 *
 * @package MedialabWooFilters
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Priorität 20: ACF hat zu diesem Zeitpunkt die eigenen Feldwerte bereits
// gespeichert, get_field() liefert also zuverlässig die aktuellen Werte.
add_action( 'acf/save_post', 'mlwf_sync_configurator_filter_terms', 20 );

function mlwf_sync_configurator_filter_terms( $post_id ): void {
	// ACF feuert 'acf/save_post' auch für Options-Seiten (dort ist $post_id
	// ein String wie 'option', kein numerischer Post) - beides ausschließen.
	if ( ! is_numeric( $post_id ) || get_post_type( $post_id ) !== 'product' ) return;

	if ( ! get_field( 'is_configurable', $post_id ) ) return;

	$steps = get_field( 'config_steps', $post_id );
	if ( ! is_array( $steps ) || empty( $steps ) ) return;

	$filterable_types = [ 'select', 'radio', 'checkbox', 'color_picker' ];

	// Terms je Taxonomie sammeln statt sofort zu setzen: falls zwei Steps
	// dieselbe Taxonomie referenzieren, würde ein zweiter sofortiger
	// wp_set_object_terms()-Aufruf die Terms des ersten überschreiben (replace,
	// kein append). Daher erst sammeln, am Ende EIN Aufruf pro Taxonomie.
	$terms_by_taxonomy = [];

	// Ob sich an $steps etwas geändert hat (neue/andere filter_term_id-Werte) -
	// nur dann am Ende EIN update_field()-Aufruf, statt bei jedem Speichern
	// unnötig zu schreiben.
	$modified = false;

	foreach ( $steps as $step_index => $step ) {
		if ( empty( $step['use_as_filter'] ) ) continue;
		if ( ! in_array( $step['step_type'] ?? '', $filterable_types, true ) ) continue;

		$taxonomy = $step['filter_attribute'] ?? '';
		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) continue;

		$options = is_array( $step['options'] ?? null ) ? $step['options'] : [];
		if ( empty( $options ) ) continue;

		if ( ! isset( $terms_by_taxonomy[ $taxonomy ] ) ) {
			$terms_by_taxonomy[ $taxonomy ] = [];
		}

		foreach ( $options as $option_index => $option ) {
			$label = trim( (string) ( $option['label'] ?? '' ) );
			if ( $label === '' ) continue;

			$term = get_term_by( 'name', $label, $taxonomy );

			if ( ! $term ) {
				$inserted = wp_insert_term( $label, $taxonomy );
				if ( is_wp_error( $inserted ) ) continue;
				$term_id = (int) $inserted['term_id'];
			} else {
				$term_id = (int) $term->term_id;
			}

			$terms_by_taxonomy[ $taxonomy ][] = $term_id;

			// Brücke zurückschreiben: term_id als eigene Referenz an der
			// Option speichern - 'value' bleibt unangetastet. Nur bei
			// tatsächlicher Änderung markieren, um unnötige Writes zu vermeiden.
			$current = (string) ( $option['filter_term_id'] ?? '' );
			if ( $current !== (string) $term_id ) {
				$steps[ $step_index ]['options'][ $option_index ]['filter_term_id'] = (string) $term_id;
				$modified = true;
			}
		}
	}

	foreach ( $terms_by_taxonomy as $taxonomy => $term_ids ) {
		wp_set_object_terms( $post_id, array_unique( $term_ids ), $taxonomy, false );
	}

	// update_field() löst KEIN erneutes 'acf/save_post' aus (das feuert nur
	// beim eigentlichen ACF-Formular-Speichervorgang) - kein Risiko einer
	// Endlosschleife durch diesen Rückschreib-Schritt.
	if ( $modified ) {
		update_field( 'config_steps', $steps, $post_id );
	}
}
