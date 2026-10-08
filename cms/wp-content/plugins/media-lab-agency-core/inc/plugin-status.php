<?php
/**
 * Plugin Status – eigene Admin-Seite (Agency Core → Plugin Status).
 *
 * Ersetzt die frühere ACF-Options-Seite gleichen Slugs (agency-core-plugin-status),
 * die nur einen statischen Text mit einem "Aktualisieren"-Button zeigte. Die neue
 * Seite zeigt:
 *
 *  - Übersicht: Version, WordPress/PHP/Umgebung, Warnungen (Wartungsmodus,
 *    fehlendes ACF, Debug-Modus), System & Abhängigkeiten (ACF, Theme,
 *    WooCommerce, Mehrsprachigkeit, Speicher, Object Cache), Inhaltstypen mit
 *    Anzahl (dynamisch, nicht mehr hartcodiert) und Schnellzugriff auf alle
 *    Agency-Core-Einstellungsseiten (aus dem Admin-Menü).
 *  - Dokumentation: README.md des Plugins, im Admin gerendert.
 *  - Changelog: CHANGELOG.md, je Version einklappbar (neueste offen).
 *
 * Die Doku-Tabs sind ausgeblendet, sobald White Label aktiv ist (die Dateien
 * enthalten Agentur-interne Hinweise). Überschreibbar per Filter:
 *   add_filter( 'medialab_plugin_status_show_docs', '__return_true' );
 *
 * Markdown: kleiner eigener Renderer (Überschriften, Listen, Tabellen, Code,
 * Links, fett/kursiv) - bewusst ohne externe Bibliothek (das Plugin hat keine
 * Composer-Abhängigkeiten). Alles wird escaped und zusätzlich per wp_kses gefiltert.
 *
 * Datei ablegen unter: inc/plugin-status.php
 * Einbindung: require_once in media-lab-agency-core.php + MediaLab_Plugin_Status::init()
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Plugin_Status {

    const SLUG   = 'agency-core-plugin-status';
    const PARENT = 'agency-core';

    public static function init(): void {
        if ( ! is_admin() ) return;
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ], 100 );
    }

    /**
     * Nach ACFs Menü-Registrierung (Priorität 99): die ACF-Seite gleichen Slugs
     * entfernen und die eigene Seite an derselben Position eintragen.
     */
    public static function register_page(): void {
        remove_submenu_page( self::PARENT, self::SLUG );

        add_submenu_page(
            self::PARENT,
            __( 'Plugin Status', 'media-lab-core' ),
            __( 'Plugin Status', 'media-lab-core' ),
            'manage_options',
            self::SLUG,
            [ __CLASS__, 'render' ],
            1
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Seite
    // ═════════════════════════════════════════════════════════════════════════

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'media-lab-core' ) );
        }

        $plugin = self::plugin_data();
        $docs   = self::docs_enabled();

        $tabs = [ 'overview' => __( 'Übersicht', 'media-lab-core' ) ];
        if ( $docs ) {
            $tabs['docs']      = __( 'Dokumentation', 'media-lab-core' );
            $tabs['changelog'] = __( 'Changelog', 'media-lab-core' );
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'overview';
        }

        self::print_styles();
        ?>
        <div class="wrap mlps">
            <h1 class="mlps-h1"><?php esc_html_e( 'Plugin Status', 'media-lab-core' ); ?></h1>

            <header class="mlps-hero">
                <span class="mlps-hero__icon dashicons dashicons-admin-generic" aria-hidden="true"></span>
                <div class="mlps-hero__text">
                    <h2><?php echo esc_html( $plugin['Name'] ); ?></h2>
                    <?php if ( $plugin['Description'] ) : ?>
                    <p><?php echo esc_html( $plugin['Description'] ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="mlps-hero__badges">
                    <span class="mlps-pill mlps-pill--version">v<?php echo esc_html( $plugin['Version'] ); ?></span>
                    <span class="mlps-pill mlps-pill--ok"><?php esc_html_e( 'Aktiv', 'media-lab-core' ); ?></span>
                </div>
            </header>

            <?php if ( count( $tabs ) > 1 ) : ?>
            <nav class="mlps-tabs" aria-label="<?php esc_attr_e( 'Bereiche', 'media-lab-core' ); ?>">
                <?php foreach ( $tabs as $key => $label ) : ?>
                <a class="mlps-tabs__item<?php echo $key === $tab ? ' is-active' : ''; ?>"
                   href="<?php echo esc_url( add_query_arg( [ 'page' => self::SLUG, 'tab' => $key ], admin_url( 'admin.php' ) ) ); ?>"
                   <?php echo $key === $tab ? 'aria-current="page"' : ''; ?>>
                    <?php echo esc_html( $label ); ?>
                </a>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <?php
            if ( $tab === 'docs' ) {
                self::render_markdown_file( 'README.md', 'docs' );
            } elseif ( $tab === 'changelog' ) {
                self::render_markdown_file( 'CHANGELOG.md', 'changelog' );
            } else {
                self::render_overview( $plugin );
            }
            ?>
        </div>
        <?php
    }

    // ── Übersicht ────────────────────────────────────────────────────────────

    private static function render_overview( array $plugin ): void {
        global $wp_version;

        $wp_min  = $plugin['RequiresWP'] ?: '6.0';
        $php_min = $plugin['RequiresPHP'] ?: '8.0';
        $env     = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

        $stats = [
            [ __( 'Plugin-Version', 'media-lab-core' ), 'v' . $plugin['Version'], __( 'Media Lab Agency Core', 'media-lab-core' ), 'ok' ],
            [ 'WordPress', $wp_version, sprintf( __( 'Mindestens %s', 'media-lab-core' ), $wp_min ), version_compare( $wp_version, $wp_min, '>=' ) ? 'ok' : 'error' ],
            [ 'PHP', PHP_VERSION, sprintf( __( 'Mindestens %s', 'media-lab-core' ), $php_min ), version_compare( PHP_VERSION, $php_min, '>=' ) ? 'ok' : 'error' ],
            [ __( 'Umgebung', 'media-lab-core' ), ucfirst( $env ), WP_DEBUG ? __( 'Debug-Modus aktiv', 'media-lab-core' ) : __( 'Debug-Modus aus', 'media-lab-core' ), ( WP_DEBUG && $env === 'production' ) ? 'warn' : 'ok' ],
        ];

        // ── Hinweise ─────────────────────────────────────────────────────────
        $alerts = [];

        if ( function_exists( 'get_field' ) && get_field( 'maintenance_enabled', 'option' ) ) {
            $alerts[] = [
                'warn',
                __( 'Der Wartungsmodus ist aktiv – Besucher sehen die Wartungsseite.', 'media-lab-core' ),
                admin_url( 'admin.php?page=agency-core-maintenance' ),
                __( 'Wartungsmodus öffnen', 'media-lab-core' ),
            ];
        }
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            $alerts[] = [ 'error', __( 'Advanced Custom Fields (Pro) ist nicht aktiv – die Einstellungsseiten funktionieren nicht.', 'media-lab-core' ), '', '' ];
        }
        if ( WP_DEBUG && $env === 'production' ) {
            $alerts[] = [ 'warn', __( 'WP_DEBUG ist auf einer Production-Umgebung aktiv.', 'media-lab-core' ), '', '' ];
        }

        foreach ( $alerts as $alert ) :
            ?>
            <div class="mlps-alert mlps-alert--<?php echo esc_attr( $alert[0] ); ?>">
                <span class="dashicons <?php echo $alert[0] === 'error' ? 'dashicons-dismiss' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
                <span><?php echo esc_html( $alert[1] ); ?></span>
                <?php if ( $alert[2] ) : ?>
                <a href="<?php echo esc_url( $alert[2] ); ?>"><?php echo esc_html( $alert[3] ); ?></a>
                <?php endif; ?>
            </div>
            <?php
        endforeach;
        ?>

        <div class="mlps-stats">
            <?php foreach ( $stats as $stat ) : ?>
            <div class="mlps-stat mlps-stat--<?php echo esc_attr( $stat[3] ); ?>">
                <span class="mlps-stat__label"><?php echo esc_html( $stat[0] ); ?></span>
                <span class="mlps-stat__value"><?php echo esc_html( $stat[1] ); ?></span>
                <span class="mlps-stat__note"><?php echo esc_html( $stat[2] ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mlps-grid">

            <section class="mlps-card">
                <h3><?php esc_html_e( 'System & Abhängigkeiten', 'media-lab-core' ); ?></h3>
                <table class="mlps-table">
                    <tbody>
                    <?php foreach ( self::system_rows() as $row ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $row[0] ); ?></th>
                            <td>
                                <?php echo esc_html( $row[1] ); ?>
                                <?php if ( ! empty( $row[3] ) ) : ?>
                                <small><?php echo esc_html( $row[3] ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="mlps-table__state"><span class="mlps-dot mlps-dot--<?php echo esc_attr( $row[2] ); ?>" title="<?php echo esc_attr( $row[2] ); ?>"></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="mlps-card">
                <h3><?php esc_html_e( 'Inhaltstypen', 'media-lab-core' ); ?></h3>
                <?php $types = self::content_types(); ?>
                <?php if ( $types ) : ?>
                <table class="mlps-table">
                    <tbody>
                    <?php foreach ( $types as $type ) : ?>
                        <tr>
                            <th scope="row">
                                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $type['slug'] ) ); ?>"><?php echo esc_html( $type['label'] ); ?></a>
                            </th>
                            <td><code><?php echo esc_html( $type['slug'] ); ?></code></td>
                            <td class="mlps-table__num"><?php echo esc_html( number_format_i18n( $type['count'] ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p class="mlps-muted"><?php esc_html_e( 'Keine eigenen Inhaltstypen registriert.', 'media-lab-core' ); ?></p>
                <?php endif; ?>
            </section>

        </div>

        <section class="mlps-card">
            <h3><?php esc_html_e( 'Einstellungen', 'media-lab-core' ); ?></h3>
            <div class="mlps-links">
                <?php foreach ( self::settings_links() as $link ) : ?>
                <a class="mlps-link" href="<?php echo esc_url( $link['url'] ); ?>">
                    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                    <?php echo esc_html( $link['title'] ); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    // ── Daten ────────────────────────────────────────────────────────────────

    private static function plugin_data(): array {
        $data = get_file_data( MEDIALAB_CORE_FILE, [
            'Name'        => 'Plugin Name',
            'Version'     => 'Version',
            'Description' => 'Description',
            'RequiresWP'  => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
        ] );

        $data['Name']    = $data['Name'] ?: 'Media Lab Agency Core';
        $data['Version'] = $data['Version'] ?: MEDIALAB_CORE_VERSION;

        return $data;
    }

    /** @return array<int,array{0:string,1:string,2:string,3?:string}> label, Wert, Status (ok|warn|error|info), Hinweis */
    private static function system_rows(): array {
        $rows  = [];
        $theme = wp_get_theme();

        // ACF
        if ( function_exists( 'acf_add_local_field_group' ) ) {
            $acf = 'ACF ' . ( defined( 'ACF_VERSION' ) ? ACF_VERSION : '' ) . ( defined( 'ACF_PRO' ) ? ' Pro' : '' );
            $rows[] = [ 'Advanced Custom Fields', trim( $acf ), defined( 'ACF_PRO' ) ? 'ok' : 'warn', defined( 'ACF_PRO' ) ? '' : __( 'Pro empfohlen', 'media-lab-core' ) ];
        } else {
            $rows[] = [ 'Advanced Custom Fields', __( 'Nicht aktiv', 'media-lab-core' ), 'error', '' ];
        }

        // Theme
        $theme_note = $theme->parent() ? sprintf( __( 'Child von %s', 'media-lab-core' ), $theme->parent()->get( 'Name' ) ) : '';
        $rows[] = [ 'Theme', $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ), 'ok', $theme_note ];

        // WooCommerce
        $rows[] = defined( 'WC_VERSION' )
            ? [ 'WooCommerce', WC_VERSION, 'ok', '' ]
            : [ 'WooCommerce', __( 'Nicht aktiv', 'media-lab-core' ), 'info', '' ];

        // Mehrsprachigkeit
        if ( function_exists( 'pll_languages_list' ) ) {
            $langs  = (array) pll_languages_list( [ 'fields' => 'slug' ] );
            $rows[] = [ 'Polylang', ( defined( 'POLYLANG_VERSION' ) ? POLYLANG_VERSION : '' ), 'ok', implode( ', ', $langs ) ];
        } elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            $rows[] = [ 'WPML', ICL_SITEPRESS_VERSION, 'ok', '' ];
        } else {
            $rows[] = [ __( 'Mehrsprachigkeit', 'media-lab-core' ), __( 'Kein Plugin aktiv', 'media-lab-core' ), 'info', get_locale() ];
        }

        // Server
        $rows[] = [ __( 'PHP-Speicher', 'media-lab-core' ), (string) ini_get( 'memory_limit' ), 'info', 'WP: ' . WP_MEMORY_LIMIT ];
        $rows[] = [ __( 'Object Cache', 'media-lab-core' ), wp_using_ext_object_cache() ? __( 'Extern aktiv', 'media-lab-core' ) : __( 'Nicht aktiv', 'media-lab-core' ), 'info', '' ];

        return $rows;
    }

    /** @return array<int,array{slug:string,label:string,count:int}> */
    private static function content_types(): array {
        $hidden = (array) apply_filters( 'medialab_plugin_status_hidden_post_types', [
            'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_subscription',
            'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page',
            'wpcf7_contact_form', 'scheduled-action',
        ] );

        $out   = [];
        $types = get_post_types( [ 'show_ui' => true, '_builtin' => false ], 'objects' );

        foreach ( $types as $slug => $obj ) {
            if ( in_array( $slug, $hidden, true ) || empty( $obj->show_in_menu ) ) continue;

            $counts = wp_count_posts( $slug );
            $out[]  = [
                'slug'  => $slug,
                'label' => $obj->labels->name,
                'count' => (int) ( $counts->publish ?? 0 ),
            ];
        }

        usort( $out, static fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );

        return $out;
    }

    /** @return array<int,array{title:string,url:string}> Unterseiten von "Agency Core" aus dem Admin-Menü. */
    private static function settings_links(): array {
        global $submenu;

        $links = [];
        foreach ( (array) ( $submenu[ self::PARENT ] ?? [] ) as $item ) {
            $slug = (string) ( $item[2] ?? '' );
            if ( $slug === '' || $slug === self::SLUG || ! current_user_can( $item[1] ?? 'manage_options' ) ) continue;

            $url = strpos( $slug, '.php' ) !== false ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug );
            $links[] = [
                'title' => trim( wp_strip_all_tags( (string) $item[0] ) ),
                'url'   => $url,
            ];
        }

        return $links;
    }

    private static function docs_enabled(): bool {
        $enabled = true;

        if ( function_exists( 'get_field' ) ) {
            $white_label = get_field( 'white_label', 'option' );
            if ( is_array( $white_label ) && ! empty( $white_label['enabled'] ) ) {
                $enabled = false;
            }
        }

        return (bool) apply_filters( 'medialab_plugin_status_show_docs', $enabled );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Dokumentation (Markdown)
    // ═════════════════════════════════════════════════════════════════════════

    private static function render_markdown_file( string $file, string $mode ): void {
        $path = MEDIALAB_CORE_PATH . $file;

        if ( ! is_readable( $path ) ) {
            echo '<div class="notice notice-warning inline"><p>'
                . esc_html( sprintf( __( 'Datei %s nicht gefunden.', 'media-lab-core' ), $file ) )
                . '</p></div>';
            return;
        }

        $md   = (string) file_get_contents( $path );
        $html = $mode === 'changelog'
            ? self::render_changelog( $md )
            : '<div class="mlps-md">' . self::markdown( $md ) . '</div>';

        echo '<div class="mlps-card mlps-card--doc">' . wp_kses( $html, self::allowed_html() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
    }

    /** Changelog: je Version (## …) ein aufklappbarer Block, die neueste ist offen. */
    private static function render_changelog( string $md ): string {
        $md      = str_replace( [ "\r\n", "\r" ], "\n", $md );
        $parts   = preg_split( '/^## (?=\S)/m', $md ) ?: [ $md ];
        $preface = (string) array_shift( $parts );

        $html = '<div class="mlps-md">' . self::markdown( $preface ) . '</div>';

        foreach ( $parts as $i => $part ) {
            $nl    = strpos( $part, "\n" );
            $title = $nl === false ? $part : substr( $part, 0, $nl );
            $body  = $nl === false ? '' : substr( $part, $nl + 1 );

            $html .= '<details class="mlps-release"' . ( $i === 0 ? ' open' : '' ) . '>'
                   . '<summary>' . self::inline( $title ) . '</summary>'
                   . '<div class="mlps-md">' . self::markdown( $body ) . '</div>'
                   . '</details>';
        }

        return $html;
    }

    private static function allowed_html(): array {
        return [
            'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'p' => [], 'ul' => [], 'ol' => [], 'li' => [], 'strong' => [], 'em' => [],
            'code' => [], 'pre' => [], 'hr' => [], 'br' => [], 'blockquote' => [],
            'a'       => [ 'href' => true, 'target' => true, 'rel' => true ],
            'table'   => [ 'class' => true ], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
            'div'     => [ 'class' => true ],
            'details' => [ 'class' => true, 'open' => [ 'valueless' => 'y' ] ],
            'summary' => [],
        ];
    }

    /**
     * Minimaler Markdown-Renderer: Überschriften (# → h2), Listen (inkl. Verschachtelung
     * über Einrückung und Folgezeilen), Tabellen, Code-Blöcke, Zitate, Trennlinien.
     */
    private static function markdown( string $md ): string {
        $lines = explode( "\n", str_replace( [ "\r\n", "\r" ], "\n", $md ) );

        $out   = [];
        $para  = [];
        $stack = [];     // offene Listen: [ ['indent' => int, 'tag' => 'ul'|'ol'], … ]
        $code  = null;   // null = kein Code-Block, sonst Zeilen
        $table = [];

        $close_para  = static function () use ( &$para, &$out ) {
            if ( $para ) {
                $out[] = '<p>' . self::inline( implode( ' ', $para ) ) . '</p>';
                $para  = [];
            }
        };
        $close_lists = static function () use ( &$stack, &$out ) {
            while ( $stack ) {
                $top   = array_pop( $stack );
                $out[] = '</li></' . $top['tag'] . '>';
            }
        };
        $close_table = static function () use ( &$table, &$out ) {
            if ( $table ) {
                $out[]  = self::render_table( $table );
                $table  = [];
            }
        };

        foreach ( $lines as $line ) {
            // Code-Block
            if ( preg_match( '/^\s*```/', $line ) ) {
                if ( $code === null ) {
                    $close_para(); $close_lists(); $close_table();
                    $code = [];
                } else {
                    $out[] = '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
                    $code  = null;
                }
                continue;
            }
            if ( $code !== null ) {
                $code[] = $line;
                continue;
            }

            // Tabelle
            if ( preg_match( '/^\s*\|.*\|\s*$/', $line ) ) {
                $close_para(); $close_lists();
                $table[] = $line;
                continue;
            }
            $close_table();

            // Leerzeile
            if ( trim( $line ) === '' ) {
                $close_para(); $close_lists();
                continue;
            }

            // Überschrift
            if ( preg_match( '/^(#{1,6})\s+(.*)$/', $line, $m ) ) {
                $close_para(); $close_lists();
                $level = min( 6, strlen( $m[1] ) + 1 );
                $out[] = '<h' . $level . '>' . self::inline( $m[2] ) . '</h' . $level . '>';
                continue;
            }

            // Trennlinie
            if ( preg_match( '/^\s*(-{3,}|\*{3,})\s*$/', $line ) ) {
                $close_para(); $close_lists();
                $out[] = '<hr>';
                continue;
            }

            // Zitat
            if ( preg_match( '/^>\s?(.*)$/', $line, $m ) ) {
                $close_para(); $close_lists();
                $out[] = '<blockquote>' . self::inline( $m[1] ) . '</blockquote>';
                continue;
            }

            // Liste
            if ( preg_match( '/^(\s*)([-*]|\d+\.)\s+(.*)$/', $line, $m ) ) {
                $close_para();
                $indent = strlen( str_replace( "\t", '    ', $m[1] ) );
                $tag    = ctype_digit( rtrim( $m[2], '.' ) ) ? 'ol' : 'ul';

                if ( ! $stack ) {
                    $out[]   = '<' . $tag . '>';
                    $stack[] = [ 'indent' => $indent, 'tag' => $tag ];
                } else {
                    while ( $stack && $indent < end( $stack )['indent'] ) {
                        $top   = array_pop( $stack );
                        $out[] = '</li></' . $top['tag'] . '>';
                    }
                    if ( ! $stack ) {
                        $out[]   = '<' . $tag . '>';
                        $stack[] = [ 'indent' => $indent, 'tag' => $tag ];
                    } elseif ( $indent > end( $stack )['indent'] ) {
                        $out[]   = '<' . $tag . '>';
                        $stack[] = [ 'indent' => $indent, 'tag' => $tag ];
                    } else {
                        $out[] = '</li>';
                    }
                }

                $out[] = '<li>' . self::inline( $m[3] );
                continue;
            }

            // Folgezeile eines Listenpunkts (eingerückt)
            if ( $stack && preg_match( '/^\s+\S/', $line ) ) {
                $out[] = ' ' . self::inline( trim( $line ) );
                continue;
            }

            // Absatz
            $close_lists();
            $para[] = trim( $line );
        }

        if ( $code !== null ) {
            $out[] = '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
        }
        $close_para(); $close_lists(); $close_table();

        return implode( "\n", $out );
    }

    private static function render_table( array $rows ): string {
        $cells = static fn( string $row ): array => array_map( 'trim', explode( '|', trim( trim( $row ), '|' ) ) );

        $head = $cells( (string) array_shift( $rows ) );
        if ( $rows && preg_match( '/^\s*\|?[\s:\-|]+\|?\s*$/', $rows[0] ) ) {
            array_shift( $rows ); // Trennzeile |---|---|
        }

        $html = '<table class="mlps-md-table"><thead><tr>';
        foreach ( $head as $cell ) {
            $html .= '<th>' . self::inline( $cell ) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $html .= '<tr>';
            foreach ( $cells( $row ) as $cell ) {
                $html .= '<td>' . self::inline( $cell ) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /** Inline-Markdown: `code`, **fett**, *kursiv*, [Link](url). Alles escaped. */
    private static function inline( string $text ): string {
        // Code-Spans zuerst herauslösen (Inhalt bleibt unberührt)
        $codes = [];
        $text  = preg_replace_callback( '/`([^`]+)`/', static function ( $m ) use ( &$codes ) {
            $codes[] = '<code>' . esc_html( $m[1] ) . '</code>';
            return "\u{E000}" . ( count( $codes ) - 1 ) . "\u{E001}";
        }, $text );

        $text = esc_html( (string) $text );

        // Links
        $text = preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)\)/', static function ( $m ) {
            $label = $m[1];
            $url   = html_entity_decode( $m[2], ENT_QUOTES );

            if ( preg_match( '#^(\./)?CHANGELOG\.md$#i', $url ) ) {
                $href = admin_url( 'admin.php?page=' . self::SLUG . '&tab=changelog' );
                return '<a href="' . esc_url( $href ) . '">' . $label . '</a>';
            }
            if ( preg_match( '#^(https?://|mailto:|\#)#i', $url ) ) {
                $ext = stripos( $url, 'http' ) === 0 ? ' target="_blank" rel="noopener noreferrer"' : '';
                return '<a href="' . esc_url( $url ) . '"' . $ext . '>' . $label . '</a>';
            }

            return $label; // relative Repo-Pfade: nur Text
        }, $text );

        $text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', (string) $text );
        $text = preg_replace( '/(?<![\*\w])\*(?![\s\*])(.+?)(?<![\s\*])\*(?![\*\w])/s', '<em>$1</em>', (string) $text );

        return (string) preg_replace_callback( '/\x{E000}(\d+)\x{E001}/u', static function ( $m ) use ( $codes ) {
            return $codes[ (int) $m[1] ] ?? '';
        }, (string) $text );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Styles
    // ═════════════════════════════════════════════════════════════════════════

    private static function print_styles(): void {
        ?>
        <style>
            .mlps{--mlps-accent:var(--wp-admin-theme-color,#2271b1);--mlps-border:#dcdcde;--mlps-muted:#646970;--mlps-ok:#00a32a;--mlps-warn:#dba617;--mlps-err:#d63638;max-width:1180px}
            .mlps *{box-sizing:border-box}
            .mlps-h1{margin-bottom:16px!important}
            .mlps-hero{display:flex;align-items:center;gap:16px;padding:20px 24px;background:#fff;border:1px solid var(--mlps-border);border-left:4px solid var(--mlps-accent);border-radius:8px;margin-bottom:16px}
            .mlps-hero__icon{font-size:34px;width:34px;height:34px;color:var(--mlps-accent)}
            .mlps-hero__text{flex:1;min-width:0}
            .mlps-hero__text h2{margin:0 0 4px;font-size:18px;padding:0}
            .mlps-hero__text p{margin:0;color:var(--mlps-muted)}
            .mlps-hero__badges{display:flex;gap:8px;flex-shrink:0}
            .mlps-pill{display:inline-block;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:600;line-height:20px;background:#f0f0f1;color:#1d2327}
            .mlps-pill--ok{background:#e7f6ec;color:#007017}
            .mlps-pill--version{background:#eef2ff;color:var(--mlps-accent)}
            .mlps-tabs{display:flex;gap:4px;border-bottom:1px solid var(--mlps-border);margin-bottom:20px}
            .mlps-tabs__item{padding:10px 16px;margin-bottom:-1px;text-decoration:none;color:var(--mlps-muted);font-weight:600;border:1px solid transparent;border-radius:6px 6px 0 0}
            .mlps-tabs__item:hover{color:var(--mlps-accent)}
            .mlps-tabs__item.is-active{color:#1d2327;background:#fff;border-color:var(--mlps-border);border-bottom-color:#fff}
            .mlps-alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:6px;margin-bottom:12px;border:1px solid;font-size:14px}
            .mlps-alert a{margin-left:auto;font-weight:600}
            .mlps-alert--warn{background:#fcf9e8;border-color:#f0d78c;color:#6b5400}
            .mlps-alert--error{background:#fcf0f1;border-color:#f1a8a9;color:#8a1f20}
            .mlps-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:16px}
            .mlps-stat{display:flex;flex-direction:column;gap:2px;padding:16px 20px;background:#fff;border:1px solid var(--mlps-border);border-radius:8px;border-top:3px solid var(--mlps-ok)}
            .mlps-stat--warn{border-top-color:var(--mlps-warn)}
            .mlps-stat--error{border-top-color:var(--mlps-err)}
            .mlps-stat__label{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--mlps-muted)}
            .mlps-stat__value{font-size:22px;font-weight:700;color:#1d2327;line-height:1.3}
            .mlps-stat__note{font-size:12px;color:var(--mlps-muted)}
            .mlps-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
            .mlps-card{background:#fff;border:1px solid var(--mlps-border);border-radius:8px;padding:20px 24px;margin-bottom:16px}
            .mlps-grid .mlps-card{margin-bottom:0}
            .mlps-card h3{margin:0 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--mlps-muted)}
            .mlps-table{width:100%;border-collapse:collapse}
            .mlps-table th,.mlps-table td{padding:9px 8px;border-top:1px solid #f0f0f1;text-align:left;vertical-align:middle;font-size:13px}
            .mlps-table tr:first-child th,.mlps-table tr:first-child td{border-top:0}
            .mlps-table th{font-weight:600;width:42%}
            .mlps-table small{display:block;color:var(--mlps-muted);font-size:12px}
            .mlps-table__state,.mlps-table__num{text-align:right!important;white-space:nowrap}
            .mlps-table__num{font-variant-numeric:tabular-nums;color:var(--mlps-muted)}
            .mlps-dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#c3c4c7}
            .mlps-dot--ok{background:var(--mlps-ok)}
            .mlps-dot--warn{background:var(--mlps-warn)}
            .mlps-dot--error{background:var(--mlps-err)}
            .mlps-muted{color:var(--mlps-muted)}
            .mlps-links{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px}
            .mlps-link{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--mlps-border);border-radius:6px;text-decoration:none;font-weight:500;color:#1d2327;transition:border-color .15s,background .15s}
            .mlps-link:hover{border-color:var(--mlps-accent);background:#f6f7f7;color:var(--mlps-accent)}
            .mlps-link .dashicons{color:var(--mlps-muted)}
            .mlps-card--doc{padding:8px 28px 20px;max-width:1000px}
            .mlps-md{font-size:14px;line-height:1.65}
            .mlps-md h2{font-size:22px;margin:1.4em 0 .5em;padding-bottom:.3em;border-bottom:1px solid var(--mlps-border)}
            .mlps-md h3{font-size:17px;margin:1.4em 0 .5em}
            .mlps-md h4{font-size:15px;margin:1.3em 0 .4em}
            .mlps-md h5{font-size:13px;margin:1.1em 0 .4em;text-transform:uppercase;letter-spacing:.03em;color:var(--mlps-muted)}
            .mlps-md code{background:#f0f0f1;padding:1px 6px;border-radius:4px;font-size:12.5px}
            .mlps-md pre{background:#1d2327;color:#f0f0f1;padding:14px 16px;border-radius:6px;overflow:auto}
            .mlps-md pre code{background:none;padding:0;color:inherit}
            .mlps-md blockquote{margin:12px 0;padding:8px 16px;border-left:4px solid var(--mlps-border);color:var(--mlps-muted);background:#fafafa}
            .mlps-md ul,.mlps-md ol{margin:8px 0 8px 22px}
            .mlps-md ul{list-style:disc}
            .mlps-md ul ul{list-style:circle}
            .mlps-md hr{border:0;border-top:1px solid var(--mlps-border);margin:20px 0}
            .mlps-md-table{width:100%;border-collapse:collapse;margin:12px 0;font-size:13px;display:block;overflow-x:auto}
            .mlps-md-table th,.mlps-md-table td{border:1px solid var(--mlps-border);padding:7px 10px;text-align:left;vertical-align:top}
            .mlps-md-table th{background:#f6f7f7}
            .mlps-release{border-top:1px solid var(--mlps-border);padding:4px 0}
            .mlps-release summary{cursor:pointer;font-weight:600;font-size:15px;padding:12px 0}
            .mlps-release[open] summary{color:var(--mlps-accent)}
            @media (max-width:900px){.mlps-stats{grid-template-columns:repeat(2,1fr)}.mlps-grid{grid-template-columns:1fr}.mlps-hero{flex-wrap:wrap}}
        </style>
        <?php
    }
}
