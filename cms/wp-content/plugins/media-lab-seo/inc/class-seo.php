<?php
/**
 * MLT_SEO
 *
 * Gibt SEO-relevante Meta-Tags im <head> aus:
 *  - Google Search Console Verification
 *  - Bing Webmaster Tools Verification
 *  - Canonical URL
 *  - Open Graph (og:title, og:description, og:image, og:url, og:type)
 *  - Twitter Cards
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_SEO {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 1 );

        remove_action( 'wp_head', 'rel_canonical' );
        add_action( 'wp_head', [ $this, 'output_canonical' ], 1 );
    }

    /**
     * Meta-Tags für die Search-Console- und Bing-Verifizierung. Gespeicherte Werte werden
     * erneut geprüft (auch ältere Einträge, die vor der Prüfung in den Einstellungen gespeichert
     * wurden, z. B. eine Property-URL statt des Codes).
     */
    public function verification_tags( string $gsc, string $bing ) : string {
        $out = '';

        $g = MLT_Settings::parse_verification( $gsc, 'google-site-verification' );
        if ( $g['status'] === 'ok' ) {
            $out .= '<meta name="google-site-verification" content="' . esc_attr( $g['value'] ) . '">' . "\n";
        }

        $b = MLT_Settings::parse_verification( $bing, 'msvalidate.01' );
        if ( $b['status'] === 'ok' ) {
            $out .= '<meta name="msvalidate.01" content="' . esc_attr( $b['value'] ) . '">' . "\n";
        }

        return $out;
    }

    public function output_meta_tags() {
        $gsc  = get_option( 'mlt_gsc_verification', '' );
        $bing = get_option( 'mlt_bing_verification', '' );
        $data = $this->collect_meta_data();

        echo "\n<!-- Media Lab SEO Toolkit: SEO -->\n";

        // Verifizierungs-Tags (nur gültige Codes – eine URL o. Ä. im Feld wird nie ausgegeben)
        echo $this->verification_tags( (string) $gsc, (string) $bing ); // phpcs:ignore WordPress.Security.EscapeOutput -- Werte werden in verification_tags() escaped

        // Open Graph
        echo '<meta property="og:type"        content="' . esc_attr( $data['og_type'] ) . '">' . "\n";
        echo '<meta property="og:url"         content="' . esc_url( $data['url'] ) . '">' . "\n";
        echo '<meta property="og:title"       content="' . esc_attr( $data['title'] ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $data['description'] ) . '">' . "\n";
        echo '<meta property="og:locale"      content="' . esc_attr( get_locale() ) . '">' . "\n";
        echo '<meta property="og:site_name"   content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";

        if ( $data['image'] ) {
            echo '<meta property="og:image"        content="' . esc_url( $data['image'] ) . '">' . "\n";
            echo '<meta property="og:image:width"  content="' . esc_attr( $data['image_w'] ) . '">' . "\n";
            echo '<meta property="og:image:height" content="' . esc_attr( $data['image_h'] ) . '">' . "\n";
            echo '<meta property="og:image:alt"    content="' . esc_attr( $data['image_alt'] ) . '">' . "\n";
        }

        // Twitter Cards
        echo '<meta name="twitter:card"        content="' . esc_attr( $data['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
        echo '<meta name="twitter:title"       content="' . esc_attr( $data['title'] ) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $data['description'] ) . '">' . "\n";

        if ( $data['image'] ) {
            echo '<meta name="twitter:image" content="' . esc_url( $data['image'] ) . '">' . "\n";
        }

        echo "<!-- /Media Lab SEO Toolkit: SEO -->\n\n";
    }

    public function output_canonical() {
        $url = $this->get_canonical_url();
        if ( $url ) {
            echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
        }
    }

    private function collect_meta_data() {
        $data = [
            'title'       => '',
            'description' => '',
            'url'         => '',
            'og_type'     => 'website',
            'image'       => '',
            'image_w'     => 1200,
            'image_h'     => 630,
            'image_alt'   => '',
        ];

        $data['url']         = $this->get_canonical_url();
        $data['title']       = $this->get_title();
        $data['description'] = $this->get_description();

        if ( is_single() ) {
            $data['og_type'] = 'article';
        }

        [ $data['image'], $data['image_w'], $data['image_h'], $data['image_alt'] ] = $this->get_image();

        return $data;
    }

    private function get_canonical_url() {
        if ( is_singular() )                        return get_permalink();
        if ( is_home() || is_front_page() )         return home_url( '/' );
        if ( is_category() || is_tag() || is_tax() ) return get_term_link( get_queried_object() );
        if ( is_author() )                          return get_author_posts_url( get_queried_object_id() );
        if ( is_archive() )                         return get_post_type_archive_link( get_post_type() );
        return '';
    }

    private function get_title() {
        if ( is_singular() ) {
            return get_the_title() . ' – ' . get_bloginfo( 'name' );
        }
        return wp_get_document_title();
    }

    private function get_description() {
        if ( is_singular() ) {
            $post = get_queried_object();
            if ( $post && has_excerpt( $post->ID ) ) {
                return wp_trim_words( strip_shortcodes( get_the_excerpt( $post->ID ) ), 30, '…' );
            }
            if ( $post ) {
                return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );
            }
        }
        if ( is_home() || is_front_page() ) return get_bloginfo( 'description' );
        if ( is_category() || is_tag() || is_tax() ) {
            $desc = term_description();
            if ( $desc ) return wp_trim_words( wp_strip_all_tags( $desc ), 30, '…' );
        }
        return get_bloginfo( 'description' );
    }

    private function get_image() {
        $image = ''; $width = 1200; $height = 630; $alt = '';

        if ( is_singular() && has_post_thumbnail() ) {
            $id  = get_post_thumbnail_id();
            $src = wp_get_attachment_image_src( $id, 'full' );
            if ( $src ) {
                [ $image, $width, $height ] = $src;
                $alt = get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title();
            }
        }

        if ( ! $image ) {
            $fallback_id = get_option( 'mlt_og_default_image', 0 );
            if ( $fallback_id ) {
                $src = wp_get_attachment_image_src( $fallback_id, 'full' );
                if ( $src ) {
                    [ $image, $width, $height ] = $src;
                    $alt = get_post_meta( $fallback_id, '_wp_attachment_image_alt', true ) ?: get_bloginfo( 'name' );
                }
            }
        }

        return [ $image, $width, $height, $alt ];
    }
}
