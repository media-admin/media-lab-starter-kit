<?php
/**
 * MLT_LLMS_Txt
 *
 * Gibt unter /llms.txt eine dynamisch generierte, kuratierte Markdown-Übersicht
 * der Website aus – nach dem Community-Format von llmstxt.org (Jeremy Howard,
 * Answer.AI, 2024). Kein offizieller Web-Standard, keine der großen KI-Firmen
 * garantiert Unterstützung; siehe 13_SEO.md für Einordnung.
 *
 * Läuft über den normalen WordPress-Frontcontroller (wie robots.txt/sitemap.xml),
 * keine eigene Rewrite-Rule nötig – der `.htaccess`-Block "BEGIN WordPress"
 * reicht jede unbekannte URL ohnehin an index.php durch.
 *
 * Standardmäßig aktiv, außer die Seite ist auf "Sichtbarkeit für Suchmaschinen
 * blockieren" gestellt (`blog_public` = 0) – dann bleibt /llms.txt wie robots.txt
 * ebenfalls zurückhaltend.
 *
 * Erweiterung ohne Plugin-Code anzufassen:
 *   mlt_llms_txt_enabled   (bool)   Ausgabe komplett an/aus
 *   mlt_llms_txt_content   (string) fertigen Text vor der Ausgabe nachbearbeiten
 *   mlt_llms_txt_sections  (array)  Abschnitte (Titel => Zeilen-Array) ergänzen/ändern
 *   mlt_llms_txt_post_limit (int)   Anzahl Blogbeiträge im "Blog"-Abschnitt (Standard 20)
 *   mlt_llms_txt_description (string) Ein-Satz-Beschreibung unter dem H1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_LLMS_Txt {

    public function __construct() {
        add_action( 'init', [ $this, 'maybe_serve' ] );
    }

    // ── Ausgabe ───────────────────────────────────────────────────────────────

    public function maybe_serve(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) ) return;
        if ( ! $this->is_requested_path() ) return;
        if ( ! $this->is_enabled() ) return;

        nocache_headers();
        header( 'Content-Type: text/markdown; charset=utf-8' );
        echo $this->generate(); // phpcs:ignore WordPress.Security.EscapeOutput -- Markdown, kein HTML-Kontext
        exit;
    }

    /** True, wenn die aktuelle Anfrage exakt "<home>/llms.txt" ist (subdirectory-sicher). */
    private function is_requested_path(): bool {
        $request  = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        $home     = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
        $expected = ( '' !== $home ) ? $home . '/llms.txt' : 'llms.txt';

        return $request === $expected;
    }

    private function is_enabled(): bool {
        // "Sichtbarkeit für Suchmaschinen blockieren" (Einstellungen → Lesen) →
        // wie robots.txt zurückhaltend, keine kuratierte Seitenübersicht ausliefern.
        if ( ! get_option( 'blog_public', 1 ) ) return false;

        // Admin-Schalter: SEO Toolkit → Schema → "llms.txt aktivieren" (Standard: an).
        $enabled = (bool) get_option( 'mlt_llms_txt_enabled', 1 );

        return (bool) apply_filters( 'mlt_llms_txt_enabled', $enabled );
    }

    // ── Aufbau ────────────────────────────────────────────────────────────────

    private function generate(): string {
        $lines = [];

        $lines[] = '# ' . $this->text( get_bloginfo( 'name' ) );
        $lines[] = '';

        $summary = (string) apply_filters( 'mlt_llms_txt_description', get_bloginfo( 'description' ) );
        if ( '' !== trim( $summary ) ) {
            $lines[] = '> ' . $this->text( $summary );
            $lines[] = '';
        }

        $sections = $this->build_sections();
        $sections = (array) apply_filters( 'mlt_llms_txt_sections', $sections );

        foreach ( $sections as $title => $entries ) {
            if ( empty( $entries ) ) continue;

            $lines[] = '## ' . $title;
            $lines[] = '';
            foreach ( $entries as $entry ) {
                $lines[] = $entry;
            }
            $lines[] = '';
        }

        $content = implode( "\n", $lines );

        return (string) apply_filters( 'mlt_llms_txt_content', rtrim( $content ) . "\n" );
    }

    /** @return array<string,array<int,string>> Abschnittstitel => Zeilen (fertige "- [Titel](URL): Text") */
    private function build_sections(): array {
        $sections = [];

        $sections['Seiten'] = $this->section_from_query( [
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ] );

        if ( post_type_exists( 'service' ) ) {
            $sections['Leistungen'] = $this->section_from_query( [
                'post_type'      => 'service',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
            ] );
        }

        $post_limit = (int) apply_filters( 'mlt_llms_txt_post_limit', (int) get_option( 'mlt_llms_txt_post_limit', 20 ) );
        if ( $post_limit > 0 ) {
            $sections['Blog'] = $this->section_from_query( [
                'post_type'      => 'post',
                'posts_per_page' => $post_limit,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ] );
        }

        return $sections;
    }

    /** @return array<int,string> */
    private function section_from_query( array $args ): array {
        $args = array_merge( $args, [
            'post_status'         => 'publish',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ] );

        $query = new WP_Query( $args );
        $lines = [];

        foreach ( $query->posts as $post ) {
            if ( $this->is_excluded( $post ) ) continue;

            $title = $this->text( get_the_title( $post ) );
            $url   = get_permalink( $post );
            $desc  = $this->get_description( $post );

            $lines[] = $desc
                ? sprintf( '- [%s](%s): %s', $title, $url, $desc )
                : sprintf( '- [%s](%s)', $title, $url );
        }

        wp_reset_postdata();

        return $lines;
    }

    /** WooCommerce-Systemseiten (Warenkorb, Kasse, Mein Konto, …) ausblenden – kein kuratierbarer Inhalt. */
    private function is_excluded( WP_Post $post ): bool {
        if ( ! function_exists( 'wc_get_page_id' ) ) return false;

        foreach ( [ 'cart', 'checkout', 'myaccount', 'shop', 'terms' ] as $wc_page ) {
            if ( (int) wc_get_page_id( $wc_page ) === $post->ID ) return true;
        }

        return false;
    }

    private function get_description( WP_Post $post ): string {
        $source = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
        $text   = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $source ) ) );

        return $this->text( wp_trim_words( $text, 20, '…' ) );
    }

    /** Für Markdown-Linktext/-Fließtext: Zeilenumbrüche weg, kein rohes "]"/"[" das den Link sprengt. */
    private function text( $value ): string {
        $text = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
        $text = str_replace( [ '[', ']' ], [ '(', ')' ], $text ); // Markdown-Linksyntax nicht brechen
        $text = (string) preg_replace( '/\s+/u', ' ', $text );

        return trim( $text );
    }
}
