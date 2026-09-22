<?php
/**
 * MLT_Schema (ab 1.10.0)
 *
 * Gibt EIN verknüpftes JSON-LD-Objekt (@graph) im <head> aus:
 *
 *   Organization (bzw. LocalBusiness-Untertyp) ─┐
 *   WebSite ────────────────────────────────────┤
 *   WebPage / CollectionPage / FAQPage … ───────┤  alle per @id verknüpft
 *   BreadcrumbList ─────────────────────────────┤
 *   Hauptentität je Post Type ──────────────────┘
 *     post    → BlogPosting (+ Person als Autor)
 *     service → Service
 *     team    → Person
 *     job     → JobPosting
 *     project → CreativeWork
 *
 * FAQPage wird automatisch erkannt (Shortcodes [faq] / [accordion_item],
 * <details>/<summary> inkl. Core-Details-Block) – siehe get_faq_items().
 *
 * Manuelle Steuerung pro Seite: siehe class-schema-admin.php (Metabox).
 *
 * Erweiterung ohne Plugin-Code anzufassen:
 *   mlt_schema_enabled              (bool)  Schema komplett an/aus
 *   mlt_schema_graph                (array) fertigen Graph nachbearbeiten
 *   mlt_schema_organization         (array) Organization-Node anpassen
 *   mlt_schema_post_type_builders   (array) Post Type => Callback
 *   mlt_schema_faq_items            (array) FAQ-Items ergänzen (z. B. eigener Block)
 *   mlt_schema_article_type         (string)
 *   mlt_schema_description          (string)
 *   mlt_schema_person_contact       (bool)  E-Mail/Telefon bei Team-Personen ausgeben
 *   mlt_schema_default_country      (string) ISO-Ländercode, Default "AT"
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Schema {

    /** Zusätzliche Profilfelder am WP-Benutzer (werden in class-schema-admin.php angelegt). */
    const AUTHOR_PROFILES = [
        'mlt_linkedin'  => 'LinkedIn-Profil',
        'mlt_xing'      => 'Xing-Profil',
        'mlt_instagram' => 'Instagram-Profil',
        'mlt_x'         => 'X-Profil',
        'mlt_facebook'  => 'Facebook-Profil',
        'mlt_youtube'   => 'YouTube-Kanal',
    ];

    /** Erlaubte Seitentypen für den manuellen Override (Metabox). */
    const WEBPAGE_TYPES = [
        'WebPage', 'AboutPage', 'ContactPage', 'CollectionPage',
        'ProfilePage', 'FAQPage', 'ItemPage',
    ];

    /** Erlaubte Organisationstypen (Settings + Validierung). */
    const ORG_TYPES = [
        'Organization', 'LocalBusiness', 'ProfessionalService', 'ArtGallery', 'Store',
        'Restaurant', 'Hotel', 'MedicalBusiness', 'HealthAndBeautyBusiness',
        'AutomotiveBusiness', 'FoodEstablishment', 'LodgingBusiness',
    ];

    /** @var array<int|string,array> Knoten, nach @id indiziert (dedupliziert). */
    private array $graph = [];

    /** Kanonische URL der aktuellen Ansicht. */
    private string $url = '';

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_schema' ], 5 );
    }

    // ── Ausgabe ───────────────────────────────────────────────────────────────

    public function output_schema() {
        if ( ! $this->is_enabled() ) return;

        $graph = $this->build_graph();
        if ( empty( $graph ) ) return;

        $data = [
            '@context' => 'https://schema.org',
            '@graph'   => array_values( $graph ),
        ];

        // JSON_HEX_TAG/AMP: verhindert, dass Inhalte (z. B. "</script>" im Titel) das Script-Tag verlassen.
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT;

        echo "\n<!-- Media Lab SEO Toolkit: Schema.org -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $data, $flags );
        echo "\n</script>\n";
        echo "<!-- /Media Lab SEO Toolkit: Schema.org -->\n\n";
    }

    private function is_enabled(): bool {
        // Kein Doppel-Markup, wenn ein anderes SEO-Plugin aktiv ist.
        $enabled = ! ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) );

        if ( is_singular() && get_post_meta( get_queried_object_id(), '_mlt_schema_disable', true ) ) {
            $enabled = false;
        }

        return (bool) apply_filters( 'mlt_schema_enabled', $enabled );
    }

    // ── Graph aufbauen ────────────────────────────────────────────────────────

    private function build_graph(): array {
        $this->graph = [];
        $this->url   = $this->get_current_url();

        $this->add( $this->get_organization_node() );
        $this->add( $this->get_website_node() );

        if ( ! is_404() ) {
            $crumbs = is_search() ? null : $this->get_breadcrumb_node();
            $this->add( $crumbs );
            $this->add( $this->get_webpage_node( (bool) $crumbs ) );

            if ( is_singular() ) {
                $this->add_singular_nodes();
            }
        }

        $graph = apply_filters( 'mlt_schema_graph', $this->graph, $this );

        return is_array( $graph ) ? $graph : [];
    }

    /** Kanonische URL der aktuellen Ansicht (für Builder/Erweiterungen). */
    public function get_url(): string {
        return $this->url;
    }

    /** Knoten hinzufügen; gleiche @id überschreibt (keine Duplikate). */
    public function add( ?array $node ): void {
        if ( empty( $node ) ) return;

        if ( isset( $node['@id'] ) ) {
            $this->graph[ $node['@id'] ] = $node;
        } else {
            $this->graph[] = $node;
        }
    }

    // ── Organization / LocalBusiness ──────────────────────────────────────────

    private function get_organization_node(): array {
        $home = home_url( '/' );

        $allowed = apply_filters( 'mlt_schema_org_types', self::ORG_TYPES );
        $type = (string) get_option( 'mlt_schema_org_type', 'Organization' );
        if ( ! in_array( $type, $allowed, true ) ) {
            $type = 'Organization';
        }

        $node = [
            // Rückwärtskompatibel: bisher immer Organization + Brand.
            '@type' => ( 'Organization' === $type ) ? [ 'Organization', 'Brand' ] : $type,
            '@id'   => $home . '#organization',
            'url'   => $home,
            'name'  => get_bloginfo( 'name' ),
        ];

        $logo = $this->get_logo_url();
        if ( $logo ) {
            $node['logo']  = [ '@type' => 'ImageObject', 'url' => $logo ];
            $node['image'] = $logo; // Google verlangt bei LocalBusiness ein image
        }

        $contact = $this->get_contact();
        if ( $contact['phone'] ) $node['telephone'] = $contact['phone'];
        if ( $contact['email'] ) $node['email']     = $contact['email'];

        if ( $contact['street'] || $contact['postal'] || $contact['locality'] ) {
            $address = [ '@type' => 'PostalAddress' ];
            if ( $contact['street'] )   $address['streetAddress']   = $contact['street'];
            if ( $contact['postal'] )   $address['postalCode']      = $contact['postal'];
            if ( $contact['locality'] ) $address['addressLocality'] = $contact['locality'];
            $address['addressCountry'] = $contact['country'];
            $node['address'] = $address;
        }

        $same_as = array_values( array_unique( array_merge(
            array_filter( array_map( 'esc_url_raw', $this->lines( get_option( 'mlt_schema_same_as', '' ) ) ) ),
            $this->get_top_header_social()
        ) ) );
        if ( $same_as ) $node['sameAs'] = $same_as;

        $hours = $this->lines( get_option( 'mlt_schema_opening_hours', '' ) );
        if ( $hours && 'Organization' !== $type ) $node['openingHours'] = $hours;

        $area = $this->lines( get_option( 'mlt_schema_area_served', '' ), ',' );
        if ( $area ) $node['areaServed'] = $area;

        return apply_filters( 'mlt_schema_organization', $node );
    }

    /**
     * Kontaktdaten: Schema-Einstellungen haben Vorrang, sonst Agency Core → Top Header
     * (nur, was dort auch sichtbar veröffentlicht wird: Top Header aktiv + Eintrag aktiv).
     */
    private function get_contact(): array {
        $c = [
            'phone'    => trim( (string) get_option( 'mlt_schema_phone', '' ) ),
            'email'    => trim( (string) get_option( 'mlt_schema_email', '' ) ),
            'street'   => trim( (string) get_option( 'mlt_schema_street', '' ) ),
            'postal'   => trim( (string) get_option( 'mlt_schema_postal_code', '' ) ),
            'locality' => trim( (string) get_option( 'mlt_schema_locality', '' ) ),
            'country'  => trim( (string) get_option( 'mlt_schema_country', '' ) ),
        ];

        if ( function_exists( 'get_field' ) && get_field( 'top_header_enable', 'option' ) ) {
            $phone = get_field( 'top_header_phone', 'option' );
            if ( ! $c['phone'] && $this->group_on( $phone ) && ! empty( $phone['number'] ) ) {
                $c['phone'] = trim( (string) $phone['number'] );
            }

            $email = get_field( 'top_header_email', 'option' );
            if ( ! $c['email'] && $this->group_on( $email ) && ! empty( $email['address'] ) ) {
                $c['email'] = trim( (string) $email['address'] );
            }

            $addr = get_field( 'top_header_address', 'option' );
            if ( $this->group_on( $addr ) ) {
                if ( ! $c['street'] && ! empty( $addr['street'] ) ) $c['street'] = trim( (string) $addr['street'] );

                // Feld "PLZ & Stadt" ist Freitext, z. B. "2620 Neunkirchen"
                if ( ! $c['postal'] && ! $c['locality'] && ! empty( $addr['city'] ) ) {
                    $city = trim( (string) $addr['city'] );
                    if ( preg_match( '/^(\d{4,5})\s+(.+)$/u', $city, $m ) ) {
                        $c['postal']   = $m[1];
                        $c['locality'] = trim( $m[2] );
                    } else {
                        $c['locality'] = $city;
                    }
                }
                if ( ! $c['country'] && ! empty( $addr['country'] ) ) $c['country'] = trim( (string) $addr['country'] );
            }
        }

        if ( '' === $c['country'] ) $c['country'] = $this->get_country();

        return $c;
    }

    /** ACF-Gruppe mit "enable"-Schalter: aktiv, wenn Schalter fehlt oder an ist. */
    private function group_on( $group ): bool {
        return is_array( $group ) && ( ! array_key_exists( 'enable', $group ) || ! empty( $group['enable'] ) );
    }

    /** Social-Profile aus Agency Core → Top Header (Gruppe top_header_social). */
    private function get_top_header_social(): array {
        $urls = [];

        if ( function_exists( 'get_field' ) && get_field( 'top_header_enable', 'option' ) ) {
            $social = get_field( 'top_header_social', 'option' );
            if ( $this->group_on( $social ) ) {
                foreach ( $social as $key => $value ) {
                    if ( 'enable' !== $key && is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
                        $urls[] = esc_url_raw( $value );
                    }
                }
            }
        }

        return $urls;
    }

    private function get_logo_url(): string {
        // Reihenfolge: echtes Logo (ACF) vor dem Social-Default-Bild.
        if ( function_exists( 'get_field' ) ) {
            $acf = get_field( 'logo_desktop', 'option' ); // Agency Core → Logo / Globale Einstellungen
            if ( $acf ) {
                $url = is_array( $acf ) ? ( $acf['url'] ?? '' ) : (string) $acf;
                if ( $url ) return $url;
            }
        }

        $og = (int) get_option( 'mlt_og_default_image', 0 );

        return $og ? (string) wp_get_attachment_image_url( $og, 'full' ) : '';
    }

    // ── WebSite ───────────────────────────────────────────────────────────────

    private function get_website_node(): array {
        return [
            '@type'           => 'WebSite',
            '@id'             => home_url( '/#website' ),
            'url'             => home_url( '/' ),
            'name'            => get_bloginfo( 'name' ),
            'description'     => get_bloginfo( 'description' ),
            'publisher'       => [ '@id' => home_url( '/#organization' ) ],
            'inLanguage'      => $this->get_language(),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url( '/?s={search_term_string}' ),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    // ── WebPage (+ FAQPage) ───────────────────────────────────────────────────

    private function get_webpage_node( bool $has_breadcrumb ): array {
        $post = is_singular() ? get_queried_object() : null;
        $post = ( $post instanceof WP_Post ) ? $post : null;

        $type = 'WebPage';
        if ( is_search() ) {
            $type = 'SearchResultsPage';
        } elseif ( is_author() ) {
            $type = 'ProfilePage';
        } elseif ( ! is_front_page() && ( is_archive() || is_home() ) ) {
            $type = 'CollectionPage';
        } elseif ( $post ) {
            $override = (string) get_post_meta( $post->ID, '_mlt_schema_webpage_type', true );
            if ( in_array( $override, self::WEBPAGE_TYPES, true ) ) {
                $type = $override;
            }
        }

        $node = [
            '@type'      => $type,
            '@id'        => $this->url . '#webpage',
            'url'        => $this->url,
            'name'       => $this->get_page_title( $post ),
            'isPartOf'   => [ '@id' => home_url( '/#website' ) ],
            'inLanguage' => $this->get_language(),
        ];

        if ( is_front_page() ) {
            $node['about'] = [ '@id' => home_url( '/#organization' ) ];
        }
        if ( is_author() ) {
            $author = get_queried_object();
            if ( $author instanceof WP_User && $this->author_is_person( $author ) ) {
                $person = $this->author_person_node( $author );
                $this->add( $person );
                $node['mainEntity'] = [ '@id' => $person['@id'] ];
            }
        }
        if ( $has_breadcrumb ) {
            $node['breadcrumb'] = [ '@id' => $this->url . '#breadcrumb' ];
        }

        if ( $post ) {
            $desc = $this->get_description( $post );
            if ( $desc ) $node['description'] = $desc;

            $node['datePublished'] = get_the_date( 'c', $post );
            $node['dateModified']  = get_the_modified_date( 'c', $post );

            $image = $this->image_node( (int) get_post_thumbnail_id( $post ) );
            if ( $image ) $node['primaryImageOfPage'] = $image;

            // FAQ automatisch erkennen → Seite wird (zusätzlich) FAQPage
            $faq = $this->get_faq_items( $post );
            if ( $faq ) {
                $questions = [];
                foreach ( $faq as $item ) {
                    $questions[] = [
                        '@type'          => 'Question',
                        'name'           => $item['question'],
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $item['answer'] ],
                    ];
                }
                if ( 'FAQPage' !== $type ) {
                    $node['@type'] = ( 'WebPage' === $type ) ? 'FAQPage' : [ $type, 'FAQPage' ];
                }
                $node['mainEntity'] = $questions;
            }
        }

        return $node;
    }

    // ── BreadcrumbList ────────────────────────────────────────────────────────

    private function get_breadcrumb_node(): ?array {
        $items = MLT_Breadcrumbs::get_items();
        if ( count( $items ) < 2 ) return null;

        $list = [];
        foreach ( $items as $i => $item ) {
            $entry = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
            ];
            if ( ! empty( $item['url'] ) ) {
                $entry['item'] = $item['url'];
            }
            $list[] = $entry;
        }

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $this->url . '#breadcrumb',
            'itemListElement' => $list,
        ];
    }

    /** Rückwärtskompatibel (war public in ≤ 1.9). */
    public function get_breadcrumb_list() {
        $this->url = $this->get_current_url();
        $node      = $this->get_breadcrumb_node();

        return $node ? [ '@context' => 'https://schema.org' ] + $node : null;
    }

    // ── Hauptentität je Post Type ─────────────────────────────────────────────

    private function add_singular_nodes(): void {
        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) return;

        $builders = apply_filters( 'mlt_schema_post_type_builders', [
            'post'    => 'build_article',
            'service' => 'build_service',
            'team'    => 'build_person',
            'job'     => 'build_job',
            'project' => 'build_project',
            // 'product' bewusst nicht: WooCommerce gibt Product-Schema selbst aus.
        ] );

        $builder = $builders[ $post->post_type ] ?? null;
        if ( is_string( $builder ) && method_exists( $this, $builder ) ) {
            $builder = [ $this, $builder ];
        }

        if ( $builder && is_callable( $builder ) ) {
            $result = call_user_func( $builder, $post, $this->url . '#webpage', $this );
            $this->add_result( $result );
        }

        // Manuelles JSON-LD aus der Metabox (nur von Admins pflegbar)
        $custom = json_decode( (string) get_post_meta( $post->ID, '_mlt_schema_custom', true ), true );
        if ( is_array( $custom ) ) {
            $this->add_result( $custom );
        }
    }

    /** Akzeptiert einen Node oder eine Liste von Nodes. */
    private function add_result( $result ): void {
        if ( ! is_array( $result ) || ! $result ) return;

        $nodes = isset( $result['@type'] ) ? [ $result ] : $result;
        foreach ( $nodes as $node ) {
            if ( is_array( $node ) && isset( $node['@type'] ) ) {
                unset( $node['@context'] );
                $this->add( $node );
            }
        }
    }

    private function build_article( WP_Post $post, string $page_id ): array {
        $node = [
            '@type'            => apply_filters( 'mlt_schema_article_type', 'BlogPosting', $post ),
            '@id'              => $this->url . '#article',
            'headline'         => get_the_title( $post ),
            'url'              => get_permalink( $post ),
            'datePublished'    => get_the_date( 'c', $post ),
            'dateModified'     => get_the_modified_date( 'c', $post ),
            'mainEntityOfPage' => [ '@id' => $page_id ],
            'publisher'        => [ '@id' => home_url( '/#organization' ) ],
            'inLanguage'       => $this->get_language(),
        ];

        $desc = $this->get_description( $post );
        if ( $desc ) $node['description'] = $desc;

        $words = preg_split( '/\s+/u', trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ), -1, PREG_SPLIT_NO_EMPTY );
        if ( $words ) $node['wordCount'] = count( $words );

        $image = $this->image_node( (int) get_post_thumbnail_id( $post ) )
            ?: $this->image_node( (int) get_option( 'mlt_og_default_image', 0 ) );
        if ( $image ) $node['image'] = $image;

        $cats = get_the_category( $post->ID );
        if ( $cats ) $node['articleSection'] = $cats[0]->name;

        $tags = get_the_tags( $post->ID );
        if ( $tags ) $node['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );

        $author = get_userdata( $post->post_author );
        if ( $author && $this->author_is_person( $author ) ) {
            $person = $this->author_person_node( $author );
            $this->add( $person );
            $node['author'] = [ '@id' => $person['@id'] ];
        } else {
            // Kein brauchbarer Personenname (z. B. Benutzername "admin") → Organisation als Autor
            $node['author'] = [ '@id' => home_url( '/#organization' ) ];
        }

        return $node;
    }

    /** Nur echte Namen als Person ausgeben – nicht den Login-Namen ("admin"). */
    private function author_is_person( WP_User $author ): bool {
        $name    = trim( (string) $author->display_name );
        $is_real = '' !== $name
            && 0 !== strcasecmp( $name, (string) $author->user_login )
            && ! in_array( strtolower( $name ), [ 'admin', 'administrator', 'webmaster' ], true );

        return (bool) apply_filters( 'mlt_schema_author_is_person', $is_real, $author );
    }

    private function author_person_node( WP_User $author ): array {
        $archive = get_author_posts_url( $author->ID );

        $node = [
            '@type' => 'Person',
            '@id'   => $archive . '#person',
            'name'  => $author->display_name,
            // Bei deaktivierten Autoren-Archiven hier z. B. die Team-/Über-uns-Seite liefern
            'url'   => (string) apply_filters( 'mlt_schema_author_url', $archive, $author ),
        ];

        if ( $author->description ) $node['description'] = wp_strip_all_tags( $author->description );

        $same = [];
        if ( $author->user_url ) $same[] = esc_url_raw( $author->user_url );
        foreach ( array_keys( self::AUTHOR_PROFILES ) as $key ) {
            $url = trim( (string) get_user_meta( $author->ID, $key, true ) );
            if ( $url && preg_match( '#^https?://#i', $url ) ) $same[] = esc_url_raw( $url );
        }
        if ( $same ) $node['sameAs'] = array_values( array_unique( $same ) );

        return $node;
    }

    private function build_service( WP_Post $post, string $page_id ): array {
        $node = [
            '@type'            => 'Service',
            '@id'              => $this->url . '#service',
            'name'             => get_the_title( $post ),
            'url'              => get_permalink( $post ),
            'provider'         => [ '@id' => home_url( '/#organization' ) ],
            'mainEntityOfPage' => [ '@id' => $page_id ],
        ];

        $desc = $this->get_description( $post );
        if ( $desc ) $node['description'] = $desc;

        $image = $this->image_node( (int) get_post_thumbnail_id( $post ) );
        if ( $image ) $node['image'] = $image;

        $terms = get_the_terms( $post->ID, 'service_category' );
        if ( $terms && ! is_wp_error( $terms ) ) $node['serviceType'] = $terms[0]->name;

        $area = $this->lines( get_option( 'mlt_schema_area_served', '' ), ',' );
        if ( $area ) $node['areaServed'] = $area;

        // Bewusst kein "offers": das ACF-Feld "price" ist Freitext.
        // Bei Bedarf über den Filter "mlt_schema_graph" ergänzen.
        return $node;
    }

    private function build_person( WP_Post $post, string $page_id ): array {
        $node = [
            '@type'            => 'Person',
            '@id'              => $this->url . '#person',
            'name'             => get_the_title( $post ),
            'url'              => get_permalink( $post ),
            'worksFor'         => [ '@id' => home_url( '/#organization' ) ],
            'mainEntityOfPage' => [ '@id' => $page_id ],
        ];

        // Feldnamen laut Doku: position / social_links; laut Theme-JSON-Backup: role / social_media
        $position = (string) get_post_meta( $post->ID, 'position', true ) ?: (string) get_post_meta( $post->ID, 'role', true );
        if ( $position ) $node['jobTitle'] = $position;

        $bio = (string) get_post_meta( $post->ID, 'bio_short', true );
        if ( $bio ) $node['description'] = wp_strip_all_tags( $bio );

        $image = $this->image_node( (int) get_post_thumbnail_id( $post ) );
        if ( $image ) $node['image'] = $image;

        // DSGVO: private Kontaktdaten nur auf ausdrücklichen Wunsch (Filter → true)
        if ( apply_filters( 'mlt_schema_person_contact', false, $post ) ) {
            $email = (string) get_post_meta( $post->ID, 'email', true );
            $phone = (string) get_post_meta( $post->ID, 'phone', true );
            if ( $email ) $node['email']     = $email;
            if ( $phone ) $node['telephone'] = $phone;
        }

        // Profile: Repeater "social_links" (url) ODER Gruppe "social_media" (linkedin, twitter, …)
        if ( function_exists( 'get_field' ) ) {
            $same  = [];
            $links = get_field( 'social_links', $post->ID );
            if ( is_array( $links ) ) {
                foreach ( $links as $row ) {
                    if ( ! empty( $row['url'] ) ) $same[] = esc_url_raw( $row['url'] );
                }
            }
            $group = get_field( 'social_media', $post->ID );
            if ( is_array( $group ) ) {
                foreach ( $group as $url ) {
                    if ( is_string( $url ) && preg_match( '#^https?://#i', $url ) ) $same[] = esc_url_raw( $url );
                }
            }
            if ( $same ) $node['sameAs'] = array_values( array_unique( $same ) );
        }

        return $node;
    }

    private function build_job( WP_Post $post, string $page_id ): array {
        $node = [
            '@type'              => 'JobPosting',
            '@id'                => $this->url . '#job',
            'title'              => get_the_title( $post ),
            'description'        => wpautop( wp_kses_post( $post->post_content ) ),
            'datePosted'         => get_the_date( 'Y-m-d', $post ),
            'url'                => get_permalink( $post ),
            'mainEntityOfPage'   => [ '@id' => $page_id ],
            // Google erwartet hiringOrganization inline (mit Name)
            'hiringOrganization' => array_filter( [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'sameAs'=> home_url( '/' ),
                'logo'  => $this->get_logo_url(),
            ] ),
        ];

        $valid_through = $this->to_date( get_post_meta( $post->ID, 'application_deadline', true ) );
        if ( $valid_through ) $node['validThrough'] = $valid_through;

        $types = [
            'full-time' => 'FULL_TIME', 'part-time' => 'PART_TIME', 'contract' => 'CONTRACTOR',
            'temporary' => 'TEMPORARY', 'internship' => 'INTERN', 'freelance' => 'CONTRACTOR',
        ];
        $emp = (string) get_post_meta( $post->ID, 'employment_type', true );
        if ( isset( $types[ $emp ] ) ) $node['employmentType'] = $types[ $emp ];

        $location = (string) get_post_meta( $post->ID, 'location', true );
        if ( $location ) {
            $node['jobLocation'] = [
                '@type'   => 'Place',
                'address' => [
                    '@type'           => 'PostalAddress',
                    'addressLocality' => $location,
                    'addressCountry'  => $this->get_country(),
                ],
            ];
        }
        if ( get_post_meta( $post->ID, 'remote', true ) ) {
            $node['jobLocationType'] = 'TELECOMMUTE';
        }

        return $node;
    }

    private function build_project( WP_Post $post, string $page_id ): array {
        $node = [
            '@type'            => 'CreativeWork',
            '@id'              => $this->url . '#project',
            'name'             => get_the_title( $post ),
            'url'              => get_permalink( $post ),
            'creator'          => [ '@id' => home_url( '/#organization' ) ],
            'mainEntityOfPage' => [ '@id' => $page_id ],
        ];

        $desc = $this->get_description( $post );
        if ( $desc ) $node['description'] = $desc;

        $image = $this->image_node( (int) get_post_thumbnail_id( $post ) );
        if ( $image ) $node['image'] = $image;

        $date = $this->to_date( get_post_meta( $post->ID, 'project_date', true ) );
        if ( $date ) $node['dateCreated'] = $date;

        return $node;
    }

    // ── FAQ-Erkennung ─────────────────────────────────────────────────────────

    /**
     * @return array<int,array{question:string,answer:string}>
     */
    private function get_faq_items( WP_Post $post ): array {
        $content = (string) $post->post_content;
        $items   = [];

        // 1) [faq_accordion category="…" limit="…"] → CPT "faq" (Frage = Titel, Antwort = Inhalt)
        if ( has_shortcode( $content, 'faq_accordion' ) ) {
            preg_match_all( '/' . get_shortcode_regex( [ 'faq_accordion' ] ) . '/', $content, $matches, PREG_SET_ORDER );
            foreach ( $matches as $sc ) {
                $atts = wp_parse_args( (array) shortcode_parse_atts( $sc[3] ), [ 'category' => '', 'limit' => -1 ] );
                $args = [
                    'post_type'      => 'faq',
                    'post_status'    => 'publish',
                    'posts_per_page' => (int) $atts['limit'],
                    'orderby'        => 'date',
                    'order'          => 'ASC',
                    'no_found_rows'  => true,
                ];
                if ( ! empty( $atts['category'] ) ) {
                    $args['tax_query'] = [ [
                        'taxonomy' => 'faq_category',
                        'field'    => 'slug',
                        'terms'    => $atts['category'],
                    ] ];
                }
                foreach ( get_posts( $args ) as $faq ) {
                    $items[] = [ 'question' => get_the_title( $faq ), 'answer' => $faq->post_content ];
                }
            }
        }

        // 2) <details><summary>Frage</summary>Antwort</details> (Core-Details-Block, statische Blöcke)
        if ( false !== stripos( $content, '<details' ) && class_exists( 'DOMDocument' ) ) {
            $prev = libxml_use_internal_errors( true );
            $dom  = new DOMDocument();
            $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content );
            libxml_clear_errors();
            libxml_use_internal_errors( $prev );

            foreach ( $dom->getElementsByTagName( 'details' ) as $details ) {
                $summary = $details->getElementsByTagName( 'summary' )->item( 0 );
                if ( ! $summary ) continue;

                $answer = '';
                foreach ( $details->childNodes as $child ) {
                    if ( $child !== $summary ) $answer .= $dom->saveHTML( $child );
                }
                $items[] = [ 'question' => $summary->textContent, 'answer' => $answer ];
            }
        }

        // 3) Eigene Quellen (z. B. nativer medialab/accordion-Block, ACF-Repeater im Theme)
        $items = apply_filters( 'mlt_schema_faq_items', $items, $post );

        // Bereinigen + deduplizieren
        $clean = [];
        foreach ( (array) $items as $item ) {
            $q = $this->clean_text( $item['question'] ?? '' );
            $a = $this->clean_text( $item['answer'] ?? '' );
            if ( $q && $a && ! isset( $clean[ $q ] ) ) {
                $clean[ $q ] = [ 'question' => $q, 'answer' => $a ];
            }
        }

        return array_slice( array_values( $clean ), 0, 50 );
    }

    // ── Helfer ────────────────────────────────────────────────────────────────

    private function clean_text( $html ): string {
        $text = wp_strip_all_tags( strip_shortcodes( (string) $html ) );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

        return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
    }

    public function get_description( WP_Post $post ): string {
        $source = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
        $text   = wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $source ) ) );
        $text   = wp_trim_words( trim( $text ), 30, '…' );

        return (string) apply_filters( 'mlt_schema_description', $text, $post );
    }

    private function get_page_title( ?WP_Post $post ): string {
        if ( $post ) return get_the_title( $post );
        if ( is_front_page() || is_home() ) return get_bloginfo( 'name' );
        if ( is_search() ) return sprintf( 'Suchergebnisse für: %s', get_search_query() );
        if ( is_archive() ) return wp_strip_all_tags( get_the_archive_title() );

        return get_bloginfo( 'name' );
    }

    private function get_language(): string {
        if ( function_exists( 'pll_current_language' ) ) {
            $locale = pll_current_language( 'locale' );
            if ( $locale ) return str_replace( '_', '-', $locale );
        }

        return get_bloginfo( 'language' );
    }

    private function get_country(): string {
        $country = (string) get_option( 'mlt_schema_country', '' );

        return $country ?: (string) apply_filters( 'mlt_schema_default_country', 'AT' );
    }

    public function image_node( int $attachment_id ): ?array {
        if ( ! $attachment_id ) return null;

        $src = wp_get_attachment_image_src( $attachment_id, 'full' );
        if ( ! $src ) return null;

        $node = [ '@type' => 'ImageObject', 'url' => $src[0] ];
        if ( ! empty( $src[1] ) && ! empty( $src[2] ) ) {
            $node['width']  = (int) $src[1];
            $node['height'] = (int) $src[2];
        }

        return $node;
    }

    /** Zeilen- (oder Komma-)getrennte Option → bereinigtes Array. */
    private function lines( $value, string $sep = "\n" ): array {
        $parts = ( "\n" === $sep ) ? preg_split( '/\R/', (string) $value ) : explode( $sep, (string) $value );

        return array_values( array_filter( array_map( 'trim', (array) $parts ) ) );
    }

    /** ACF-Datum (Ymd) oder beliebiges Datum → Y-m-d. */
    private function to_date( $raw ): string {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) return '';

        if ( preg_match( '/^\d{8}$/', $raw ) ) {
            $d = DateTime::createFromFormat( 'Ymd', $raw );
            return $d ? $d->format( 'Y-m-d' ) : '';
        }

        $ts = strtotime( $raw );

        return $ts ? gmdate( 'Y-m-d', $ts ) : '';
    }

    private function get_current_url(): string {
        if ( is_singular() ) {
            $canonical = wp_get_canonical_url();
            return $canonical ?: (string) get_permalink( get_queried_object_id() );
        }
        if ( is_front_page() ) return home_url( '/' );

        if ( is_home() ) {
            $page = (int) get_option( 'page_for_posts' );
            return $page ? (string) get_permalink( $page ) : home_url( '/' );
        }

        $object = get_queried_object();

        if ( is_category() || is_tag() || is_tax() ) {
            $link = get_term_link( $object );
            if ( ! is_wp_error( $link ) ) return $link;
        }
        if ( is_post_type_archive() && $object instanceof WP_Post_Type ) {
            $link = get_post_type_archive_link( $object->name );
            if ( $link ) return $link;
        }
        if ( is_author() ) return get_author_posts_url( get_queried_object_id() );

        global $wp;

        return trailingslashit( home_url( $wp->request ?? '' ) );
    }
}
