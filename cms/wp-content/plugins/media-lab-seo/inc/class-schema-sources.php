<?php
/**
 * MLT_Schema_Sources
 *
 * Schema-Quellen für Module, die im Starter Kit überall mitgeliefert werden. Werden
 * ausschließlich über die Filter des Kerns (MLT_Schema) angedockt:
 *
 *  Seiten-Ebene (Filter mlt_schema_graph, liest den Seiteninhalt):
 *    [pricing_table]      → OfferCatalog + Offer/Service   (Agency Core)
 *    [google_map id=""]   → Place (contentLocation)        (Agency Core, CPT gmap)
 *    [mlb_booking_form]   → LocalBusiness je Standort inkl. OpeningHoursSpecification,
 *                           Leistungen, ReserveAction      (media-lab-bookings)
 *    [team_member]         → Person je Teammitglied         (Agency Core; auch verschachtelt
 *                           in [team_cards]). Der CPT-Fall ([team_query], post_type "team")
 *                           läuft weiterhin über den Post-Type-Builder in MLT_Schema.
 *
 *  Post-Type-Builder (Filter mlt_schema_post_type_builders):
 *    event → Event (+ Offer)                               (media-lab-events)
 *
 * Ist das jeweilige Plugin nicht aktiv, tut die Quelle nichts.
 * Projektspezifische Typen gehören ins Projekt (Filter mlt_schema_post_type_builders
 * bzw. mlt_schema_graph), nicht ins Starter Kit.
 *
 * Datenschutz: E-Mail-Adresse eines Standorts (mlb_location_email ist die interne
 * Kopie-Adresse für Buchungen) wird NUR ausgegeben, wenn der Filter
 * mlt_schema_location_email true liefert. Buchungen (mlb_booking) kommen nie ins Schema.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Schema_Sources {

    private ?MLT_Schema $schema = null;

    public function __construct() {
        add_filter( 'mlt_schema_post_type_builders', [ $this, 'register_builders' ] );
        add_filter( 'mlt_schema_graph',              [ $this, 'extend_graph' ], 10, 2 );
    }

    public function register_builders( $map ) {
        $map = is_array( $map ) ? $map : [];

        // "+" behält bereits registrierte Builder (Projekt-Overrides gewinnen)
        return $map + [ 'event' => [ $this, 'build_event' ] ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Seiten-Ebene: liest Shortcodes aus dem Seiteninhalt
    // ══════════════════════════════════════════════════════════════════════════

    public function extend_graph( $graph, $schema ) {
        if ( ! is_array( $graph ) || ! $schema instanceof MLT_Schema || ! is_singular() ) return $graph;

        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) return $graph;

        $this->schema = $schema;
        $url          = $schema->get_url();
        $content      = (string) $post->post_content;
        $org_id       = home_url( '/#organization' );

        $graph = $this->add_pricing( $graph, $content, $post, $url, $org_id );
        $graph = $this->add_maps( $graph, $content, $url );
        $graph = $this->add_booking_locations( $graph, $content, $url, $org_id );
        $graph = $this->add_team_members( $graph, $content, $org_id );

        return $graph;
    }

    // ── [pricing_table] → OfferCatalog ────────────────────────────────────────

    private function add_pricing( array $graph, string $content, WP_Post $post, string $url, string $org_id ): array {
        if ( ! has_shortcode( $content, 'pricing_table' ) ) return $graph;

        preg_match_all( '/' . get_shortcode_regex( [ 'pricing_table' ] ) . '/', $content, $matches, PREG_SET_ORDER );

        $offers = [];
        foreach ( $matches as $sc ) {
            // Defaults spiegeln pricing_table_shortcode() (Agency Core)
            $atts = shortcode_atts( [
                'title' => '', 'price' => '', 'currency' => '€', 'period' => 'pro Monat',
                'description' => '', 'features' => '',
            ], (array) shortcode_parse_atts( $sc[3] ) );

            $name  = $this->text( $atts['title'] );
            $price = $this->parse_price( (string) $atts['price'] );
            if ( '' === $name || null === $price ) continue; // z. B. "Auf Anfrage" → kein Offer

            $code    = $this->currency_code( (string) $atts['currency'] );
            $period  = $this->text( $atts['period'] );
            $service = [
                '@type'    => 'Service',
                'name'     => $name,
                'provider' => [ '@id' => $org_id ],
            ];

            $desc = $this->text( $atts['description'] );
            if ( '' === $desc ) {
                $features = array_filter( array_map( 'trim', explode( ',', (string) $atts['features'] ) ) );
                $desc     = $this->text( implode( ', ', $features ) );
            }
            if ( '' !== $desc ) $service['description'] = $desc;

            $offer = [
                '@type'         => 'Offer',
                'name'          => $name,
                'price'         => $price,
                'priceCurrency' => $code,
                'itemOffered'   => $service,
                'seller'        => [ '@id' => $org_id ],
                'url'           => $url,
            ];
            if ( '' !== $period ) {
                $offer['priceSpecification'] = [
                    '@type'         => 'UnitPriceSpecification',
                    'price'         => $price,
                    'priceCurrency' => $code,
                    'unitText'      => $period,
                ];
            }
            $offers[] = $offer;
        }

        if ( ! $offers ) return $graph;

        $catalog_id           = $url . '#pricing';
        $graph[ $catalog_id ] = [
            '@type'           => 'OfferCatalog',
            '@id'             => $catalog_id,
            'name'            => sprintf( '%s – Preise', get_the_title( $post ) ),
            'itemListElement' => $offers,
        ];

        if ( isset( $graph[ $org_id ] ) ) {
            $this->append( $graph[ $org_id ], 'hasOfferCatalog', [ '@id' => $catalog_id ] );
        }

        return $graph;
    }

    // ── [team_member] → Person ────────────────────────────────────────────────

    /**
     * Erkennt sowohl freistehende als auch in [team_cards] verschachtelte
     * [team_member]-Shortcodes. Attribute: name, role, image, email, phone,
     * linkedin, twitter, facebook, instagram; Inhalt = Kurzbio.
     */
    private function add_team_members( array $graph, string $content, string $org_id ): array {
        if ( ! has_shortcode( $content, 'team_member' ) ) return $graph;

        preg_match_all( '/' . get_shortcode_regex( [ 'team_member' ] ) . '/', $content, $matches, PREG_SET_ORDER );

        $employees = [];
        foreach ( $matches as $i => $sc ) {
            $atts = shortcode_atts( [
                'name' => '', 'role' => '', 'image' => '', 'email' => '', 'phone' => '',
                'linkedin' => '', 'twitter' => '', 'facebook' => '', 'instagram' => '',
            ], (array) shortcode_parse_atts( $sc[3] ) );

            $name = $this->text( $atts['name'] );
            if ( '' === $name ) continue;

            $id   = $org_id . '-team-' . ( $i + 1 ) . '-' . sanitize_title( $name );
            $node = [
                '@type'    => 'Person',
                '@id'      => $id,
                'name'     => $name,
                'worksFor' => [ '@id' => $org_id ],
            ];

            $role = $this->text( $atts['role'] );
            if ( '' !== $role ) $node['jobTitle'] = $role;

            $img = esc_url_raw( (string) $atts['image'] );
            if ( '' !== $img ) $node['image'] = $img;

            $bio = $this->text( $sc[5] ?? '' );
            if ( '' !== $bio ) $node['description'] = $bio;

            // E-Mail/Telefon nur auf ausdrücklichen Wunsch (DSGVO, wie beim Team-CPT)
            if ( apply_filters( 'mlt_schema_person_contact', false ) ) {
                $email = trim( (string) $atts['email'] );
                $phone = trim( (string) $atts['phone'] );
                if ( $email ) $node['email']     = $email;
                if ( $phone ) $node['telephone'] = $phone;
            }

            $same = [];
            foreach ( [ 'linkedin', 'twitter', 'facebook', 'instagram' ] as $key ) {
                $url_val = esc_url_raw( (string) $atts[ $key ] );
                if ( '' !== $url_val ) $same[] = $url_val;
            }
            if ( $same ) $node['sameAs'] = $same;

            $graph[ $id ] = $node;
            $employees[]  = [ '@id' => $id ];
        }

        if ( $employees && isset( $graph[ $org_id ] ) ) {
            $graph[ $org_id ]['employee'] = $employees;
        }

        return $graph;
    }

    // ── [google_map id="…"] → Place ───────────────────────────────────────────

    private function add_maps( array $graph, string $content, string $url ): array {
        if ( ! has_shortcode( $content, 'google_map' ) ) return $graph;

        preg_match_all( '/' . get_shortcode_regex( [ 'google_map' ] ) . '/', $content, $matches, PREG_SET_ORDER );

        $page_id = $url . '#webpage';
        foreach ( $matches as $sc ) {
            $atts = (array) shortcode_parse_atts( $sc[3] );
            $id   = (int) ( $atts['id'] ?? 0 );
            $map  = $id ? get_post( $id ) : null;

            if ( ! $map || 'gmap' !== $map->post_type || 'publish' !== $map->post_status ) continue;

            $address = trim( (string) get_post_meta( $id, 'address', true ) );
            if ( '' === $address ) continue; // ohne Adresse kein sinnvoller Place

            $title = trim( (string) get_post_meta( $id, 'marker_title', true ) );
            $node  = [
                '@type'   => 'Place',
                '@id'     => $url . '#map-' . $id,
                'name'    => $title ?: get_the_title( $map ),
                'address' => $this->address_node( $address ),
                'hasMap'  => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $this->one_line( $address ) ),
            ];

            // Nur in der älteren Feldgruppe (Theme-Backup) vorhanden; aktuelle Gruppe hat nur embed_src
            $lat = get_post_meta( $id, 'latitude', true );
            $lng = get_post_meta( $id, 'longitude', true );
            if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
                $node['geo'] = [ '@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng ];
            }

            $desc = $this->text( get_post_meta( $id, 'marker_description', true ) );
            if ( '' !== $desc ) $node['description'] = $desc;

            // Feld laut älterer Doku; kann fehlen
            $phone = trim( (string) get_post_meta( $id, 'phone', true ) );
            if ( '' !== $phone ) $node['telephone'] = $phone;

            $graph[ $node['@id'] ] = $node;

            if ( isset( $graph[ $page_id ] ) ) {
                $this->append( $graph[ $page_id ], 'contentLocation', [ '@id' => $node['@id'] ] );
            }
        }

        return $graph;
    }

    // ── [mlb_booking_form] → LocalBusiness je Standort ────────────────────────

    private function add_booking_locations( array $graph, string $content, string $url, string $org_id ): array {
        if ( ! has_shortcode( $content, 'mlb_booking_form' ) || ! post_type_exists( 'mlb_location' ) ) return $graph;

        preg_match_all( '/' . get_shortcode_regex( [ 'mlb_booking_form' ] ) . '/', $content, $matches, PREG_SET_ORDER );

        $ids = [];
        foreach ( $matches as $sc ) {
            $ref = trim( (string) ( ( (array) shortcode_parse_atts( $sc[3] ) )['location'] ?? '' ) );

            if ( '' !== $ref ) {
                $loc = is_numeric( $ref )
                    ? get_post( (int) $ref )
                    : get_page_by_path( sanitize_title( $ref ), OBJECT, 'mlb_location' );
                if ( $loc && 'mlb_location' === $loc->post_type && 'publish' === $loc->post_status ) {
                    $ids[ $loc->ID ] = $loc->ID;
                }
            } else {
                // ohne Preset: alle Standorte (das Formular bietet sie zur Auswahl an)
                foreach ( get_posts( [
                    'post_type' => 'mlb_location', 'post_status' => 'publish',
                    'posts_per_page' => 20, 'fields' => 'ids', 'no_found_rows' => true,
                ] ) as $loc_id ) {
                    $ids[ $loc_id ] = $loc_id;
                }
            }
        }

        foreach ( $ids as $id ) {
            $node = $this->location_node( (int) $id, $url, $org_id, $graph );
            $graph[ $node['@id'] ] = $node;

            if ( isset( $graph[ $org_id ] ) ) {
                $this->append( $graph[ $org_id ], 'subOrganization', [ '@id' => $node['@id'] ] );
            }
        }

        return $graph;
    }

    private function location_node( int $id, string $url, string $org_id, array $graph ): array {
        $type = (string) get_option( 'mlt_schema_org_type', 'Organization' );
        $type = ( 'Organization' === $type || ! in_array( $type, MLT_Schema::ORG_TYPES, true ) ) ? 'LocalBusiness' : $type;

        $node = [
            '@type'              => $type,
            '@id'                => home_url( '/#location-' . $id ),
            'name'               => get_the_title( $id ),
            'url'                => $url,
            'parentOrganization' => [ '@id' => $org_id ],
        ];

        if ( ! empty( $graph[ $org_id ]['image'] ) ) $node['image'] = $graph[ $org_id ]['image'];

        $address = trim( (string) get_post_meta( $id, 'mlb_location_address', true ) );
        if ( '' !== $address ) $node['address'] = $this->address_node( $address );

        $phone = trim( (string) get_post_meta( $id, 'mlb_location_phone', true ) );
        if ( '' !== $phone ) $node['telephone'] = $phone;

        if ( apply_filters( 'mlt_schema_location_email', false, $id ) ) {
            $email = trim( (string) get_post_meta( $id, 'mlb_location_email', true ) );
            if ( '' !== $email ) $node['email'] = $email;
        }

        $hours = $this->opening_hours( $id );
        if ( $hours ) $node['openingHoursSpecification'] = $hours;

        // Leistungen aus dem ACF-Repeater mlb_services (service_name)
        if ( function_exists( 'get_field' ) ) {
            $services = [];
            foreach ( (array) get_field( 'mlb_services', $id ) as $row ) {
                $name = $this->text( $row['service_name'] ?? '' );
                if ( '' !== $name ) $services[] = [ '@type' => 'Service', 'name' => $name ];
            }
            if ( $services ) {
                $node['hasOfferCatalog'] = [
                    '@type'           => 'OfferCatalog',
                    'name'            => 'Leistungen',
                    'itemListElement' => $services,
                ];
            }
        }

        $node['potentialAction'] = [
            '@type'  => 'ReserveAction',
            'name'   => function_exists( 'mlb_term' ) ? (string) mlb_term( 'verb' ) : 'Termin buchen',
            'target' => [ '@type' => 'EntryPoint', 'urlTemplate' => $url ],
        ];

        return $node;
    }

    /** Wochentage mit gleichen Zeiten zu einer Specification zusammenfassen. */
    private function opening_hours( int $id ): array {
        $days = [
            'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
            'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
        ];

        $groups = [];
        foreach ( $days as $key => $name ) {
            // Plugin-Default: Mo–Fr aktiv, solange nichts gespeichert ist
            $active = metadata_exists( 'post', $id, "mlb_{$key}_active" )
                ? (bool) get_post_meta( $id, "mlb_{$key}_active", true )
                : in_array( $key, [ 'mon', 'tue', 'wed', 'thu', 'fri' ], true );
            if ( ! $active ) continue;

            $open  = $this->time( get_post_meta( $id, "mlb_{$key}_open", true ) ?: '09:00' );
            $close = $this->time( get_post_meta( $id, "mlb_{$key}_close", true ) ?: '18:00' );
            if ( '' === $open || '' === $close ) continue;

            $groups[ $open . '-' . $close ]['opens']    = $open;
            $groups[ $open . '-' . $close ]['closes']   = $close;
            $groups[ $open . '-' . $close ]['days'][]   = $name;
        }

        $specs = [];
        foreach ( $groups as $g ) {
            $specs[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $g['days'],
                'opens'     => $g['opens'],
                'closes'    => $g['closes'],
            ];
        }

        return $specs;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Post-Type-Builder
    // ══════════════════════════════════════════════════════════════════════════

    public function build_event( WP_Post $post, string $page_id, $schema ) {
        $this->schema = $schema;

        $start = $this->datetime( get_post_meta( $post->ID, 'event_date_start', true ) );
        if ( '' === $start ) return null; // Event ohne Startdatum ist ungültig

        $node = [
            '@type'               => 'Event',
            '@id'                 => $schema->get_url() . '#event',
            'name'                => get_the_title( $post ),
            'url'                 => get_permalink( $post ),
            'startDate'           => $start,
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'organizer'           => [ '@id' => home_url( '/#organization' ) ],
            'mainEntityOfPage'    => [ '@id' => $page_id ],
        ];

        $end = $this->datetime( get_post_meta( $post->ID, 'event_date_end', true ) );
        if ( '' !== $end ) $node['endDate'] = $end;

        $desc = $schema->get_description( $post );
        if ( '' !== $desc ) $node['description'] = $desc;

        $image = $schema->image_node( (int) get_post_thumbnail_id( $post ) );
        if ( $image ) $node['image'] = $image;

        // event_location ist Freitext → als Name und Adresse (Text) ausgeben
        $loc = $this->text( get_post_meta( $post->ID, 'event_location', true ) );
        if ( '' !== $loc ) {
            $node['location'] = [ '@type' => 'Place', 'name' => $loc, 'address' => $loc ];
        }

        // event_price ist Freitext → nur bei eindeutiger Zahl bzw. "frei"
        $raw = (string) get_post_meta( $post->ID, 'event_price', true );
        if ( preg_match( '/^\s*(eintritt\s+)?(frei|kostenlos|gratis)\s*$/i', $raw ) ) {
            $price = '0';
        } else {
            $price = $this->parse_price( $raw );
        }
        if ( null !== $price ) {
            $node['offers'] = [
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => $this->currency_code( $raw ),
                'url'           => get_permalink( $post ),
            ];
        }

        return $node;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helfer
    // ══════════════════════════════════════════════════════════════════════════

    /** Wert an eine Listen-Property anhängen (Einzelwert wird zur Liste). */
    private function append( array &$node, string $prop, array $value ): void {
        if ( ! isset( $node[ $prop ] ) ) {
            $node[ $prop ] = [ $value ];
            return;
        }
        if ( isset( $node[ $prop ]['@id'] ) || isset( $node[ $prop ]['@type'] ) ) {
            $node[ $prop ] = [ $node[ $prop ] ];
        }
        $node[ $prop ][] = $value;
    }

    private function text( $html ): string {
        $text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );

        return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
    }

    private function one_line( string $value ): string {
        return trim( (string) preg_replace( '/\s*[\r\n]+\s*/', ', ', $value ) );
    }

    /**
     * Streng: nur eindeutige Zahlen ("29", "29,90", "1.299,00", "29,-", "29 €").
     * Alles andere ("ab 29", "Auf Anfrage") → null, damit kein falscher Preis entsteht.
     */
    public function parse_price( string $raw ): ?string {
        $s = trim( str_ireplace( [ '€', 'EUR', 'CHF', '$', '£', ',-', '.-' ], '', $raw ) );
        if ( '' === $s ) return null;

        if ( preg_match( '/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $s ) ) {        // 1.299,90
            $s = str_replace( [ '.', ',' ], [ '', '.' ], $s );
        } elseif ( preg_match( '/^\d+,\d{1,2}$/', $s ) ) {                    // 29,90
            $s = str_replace( ',', '.', $s );
        } elseif ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $s ) ) {              // 29 / 29.90
            return null;
        }

        return $s;
    }

    public function currency_code( string $raw ): string {
        if ( false !== strpos( $raw, '$' ) || false !== stripos( $raw, 'USD' ) ) return 'USD';
        if ( false !== stripos( $raw, 'CHF' ) )                                  return 'CHF';
        if ( false !== strpos( $raw, '£' ) || false !== stripos( $raw, 'GBP' ) ) return 'GBP';

        return 'EUR';
    }

    /** "Straße 1, 2620 Neunkirchen" → PostalAddress; sonst Zeile als streetAddress. */
    public function address_node( string $address ): array {
        $line = $this->one_line( $address );

        if ( preg_match( '/^(.*?),\s*(\d{4,5})\s+([^,]+?)(?:,\s*.+)?$/u', $line, $m ) ) {
            return [
                '@type'           => 'PostalAddress',
                'streetAddress'   => trim( $m[1] ),
                'postalCode'      => $m[2],
                'addressLocality' => trim( $m[3] ),
                'addressCountry'  => $this->country(),
            ];
        }

        return [ '@type' => 'PostalAddress', 'streetAddress' => $line, 'addressCountry' => $this->country() ];
    }

    private function country(): string {
        $country = (string) get_option( 'mlt_schema_country', '' );

        return $country ?: (string) apply_filters( 'mlt_schema_default_country', 'AT' );
    }

    public function time( $value ): string {
        return preg_match( '/^(\d{1,2}):(\d{2})/', trim( (string) $value ), $m )
            ? sprintf( '%02d:%s', (int) $m[1], $m[2] )
            : '';
    }

    /** ACF-Datum(-Zeit) → ISO 8601 in der WordPress-Zeitzone. */
    private function datetime( $raw ): string {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) return '';

        try {
            return ( new DateTime( $raw, wp_timezone() ) )->format( 'c' );
        } catch ( Exception $e ) {
            return '';
        }
    }
}
