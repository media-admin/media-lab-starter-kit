<?php
/**
 * MLT_SEO_Dashboard
 *
 * SEO-Dashboard mit dynamischem Datumsbereich:
 *  - Shortcuts: 7 / 28 / 90 / 365 Tage
 *  - Freier Datepicker (von/bis)
 *  - Default aus Einstellungen (mlt_default_range)
 *  - Zeitraum wird als URL-Parameter übergeben (?mlt_range=90 oder ?mlt_start=...&mlt_end=...)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_SEO_Dashboard {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'wp_dashboard_setup',    [ $this, 'register_widget' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mlt_refresh_gsc', [ $this, 'ajax_refresh' ] );
    }

    // ── Menü ──────────────────────────────────────────────────────────────────

    public function register_menu() {
        add_submenu_page(
            'media-lab-seo',
            'SEO Dashboard',
            'Dashboard',
            'manage_options',
            'mlt-dashboard',
            [ $this, 'render_page' ]
        );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public function enqueue_assets( $hook ) {
        $is_dashboard_page = $hook === 'seo-toolkit_page_mlt-dashboard';
        $is_wp_dashboard   = $hook === 'index.php';

        if ( ! $is_dashboard_page && ! $is_wp_dashboard ) return;

        wp_enqueue_style(
            'mlt-dashboard',
            MLT_URL . 'assets/dashboard.css',
            [],
            MLT_VERSION
        );

        if ( $is_dashboard_page ) {
            // Flatpickr für Datepicker
            wp_enqueue_style(
                'flatpickr',
                'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
                [],
                '4.6.13'
            );
            wp_enqueue_script(
                'flatpickr',
                'https://cdn.jsdelivr.net/npm/flatpickr',
                [],
                '4.6.13',
                true
            );

            wp_enqueue_script(
                'mlt-dashboard',
                MLT_URL . 'assets/dashboard.js',
                [ 'jquery', 'flatpickr' ],
                MLT_VERSION,
                true
            );
            wp_localize_script( 'mlt-dashboard', 'mltDashboard', [
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'mlt_dashboard' ),
                'settingsUrl' => admin_url( 'admin.php?page=media-lab-seo' ),
                'baseUrl'     => admin_url( 'admin.php?page=mlt-dashboard' ),
            ] );
        }
    }

    // ── Dashboard-Seite ───────────────────────────────────────────────────────

    public function render_page() {
        $gsc     = MLT_GSC_API::instance();
        $adapter = MLT_Analytics_Adapter_Factory::get();

        $connected     = $gsc->is_connected();
        $configured    = $gsc->is_configured();
        $has_gsc       = $connected && $configured;
        $has_analytics = $adapter !== null;

        // Aktiven Zeitraum bestimmen
        [ 'start' => $start, 'end' => $end ] = MLT_GSC_API::get_active_range();

        // Daten holen
        $overview = $has_gsc ? $gsc->get_overview( $start, $end )      : [];
        $queries  = $has_gsc ? $gsc->get_top_queries( 10, $start, $end ) : [];
        $pages    = $has_gsc ? $gsc->get_top_pages( 10, $start, $end )   : [];

        $analytics_overview = $has_analytics ? $adapter->get_overview( $start, $end )   : [];
        $analytics_sources  = $has_analytics ? $adapter->get_sources( $start, $end, 5 ) : [];

        // Aktiver Shortcut (für Button-Highlighting)
        $active_range  = isset( $_GET['mlt_range'] ) ? (int) $_GET['mlt_range'] : null;
        $is_custom     = isset( $_GET['mlt_start'] );
        $default_days  = (int) get_option( 'mlt_default_range', 28 );
        if ( ! $active_range && ! $is_custom ) $active_range = $default_days;

        // Anzeige-Datum für Subtitle
        $display_start = wp_date( 'd.m.Y', strtotime( $start ) );
        $display_end   = wp_date( 'd.m.Y', strtotime( $end ) );

        // Aktuelle URL ohne Range-Parameter (für Links)
        $base_url = admin_url( 'admin.php?page=mlt-dashboard' );
        ?>
        <div class="wrap mlt-wrap">

            <div class="mlt-header">
                <h1>SEO Dashboard</h1>
                <p class="mlt-subtitle"><?php echo esc_html( "$display_start – $display_end" ); ?></p>
            </div>

            <!-- ── Zeitraum-Auswahl ──────────────────────────────────────── -->
            <div class="mlt-daterange-bar">

                <!-- Shortcuts -->
                <div class="mlt-daterange-shortcuts">
                    <?php
                    $shortcuts = [ 7 => '7 Tage', 28 => '28 Tage', 90 => '90 Tage', 365 => '365 Tage' ];
                    foreach ( $shortcuts as $days => $label ) :
                        $url     = add_query_arg( 'mlt_range', $days, $base_url );
                        $is_active = ( $active_range === $days && ! $is_custom );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="button <?php echo $is_active ? 'button-primary' : 'button-secondary'; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Freier Datepicker -->
                <div class="mlt-daterange-custom">
                    <span class="mlt-daterange-custom__label">Eigener Zeitraum:</span>
                    <input type="text" id="mlt-date-start"
                           value="<?php echo esc_attr( $start ); ?>"
                           placeholder="Von"
                           class="<?php echo $is_custom ? 'mlt-date-active' : ''; ?>">
                    <span>–</span>
                    <input type="text" id="mlt-date-end"
                           value="<?php echo esc_attr( $end ); ?>"
                           placeholder="Bis"
                           class="<?php echo $is_custom ? 'mlt-date-active' : ''; ?>">
                    <button type="button" id="mlt-date-apply" class="button <?php echo $is_custom ? 'button-primary' : 'button-secondary'; ?>">
                        Anwenden
                    </button>
                </div>

            </div><!-- .mlt-daterange-bar -->

            <?php if ( isset( $_GET['mlt_gsc_connected'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>✓ Google Search Console erfolgreich verbunden.</p></div>
            <?php endif; ?>

            <?php if ( ! $configured ) : ?>
                <div class="mlt-notice mlt-notice--warning" style="max-width:680px">
                    <strong>⚠ Google Search Console nicht konfiguriert.</strong>
                    Bitte Client ID, Client Secret und Property URL unter
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=media-lab-seo#gsc' ) ); ?>">SEO Toolkit → Einstellungen</a>
                    eintragen.
                </div>
            <?php elseif ( ! $connected ) : ?>
                <div class="mlt-notice mlt-notice--warning" style="max-width:680px">
                    <strong>⚠ Noch nicht mit Google verbunden.</strong>
                    <a href="<?php echo esc_url( MLT_GSC_API::instance()->get_auth_url() ); ?>"
                       class="button button-primary" style="margin-left:12px">Mit Google verbinden</a>
                </div>
            <?php endif; ?>

            <!-- KPI-Kacheln -->
            <div class="mlt-kpi-grid">
                <?php $this->kpi( 'Klicks',        $overview['clicks']      ?? '–', 'mlt-kpi--blue' ); ?>
                <?php $this->kpi( 'Impressionen',  $overview['impressions'] ?? '–', 'mlt-kpi--purple' ); ?>
                <?php $this->kpi( 'Ø CTR',         isset( $overview['ctr'] ) ? $overview['ctr'] . '%' : '–', 'mlt-kpi--green' ); ?>
                <?php $this->kpi( 'Ø Position',    $overview['position']    ?? '–', 'mlt-kpi--orange' ); ?>
                <?php if ( $has_analytics ) : ?>
                    <?php $this->kpi( 'Seitenaufrufe', $analytics_overview['pageviews'] ?? '–', 'mlt-kpi--teal' ); ?>
                    <?php $this->kpi( 'Nutzer',        $analytics_overview['users']     ?? '–', 'mlt-kpi--pink' ); ?>
                <?php endif; ?>
            </div>

            <div class="mlt-grid">

                <!-- Top Keywords -->
                <?php if ( ! empty( $queries ) ) : ?>
                <div class="mlt-card">
                    <div class="mlt-card__header">
                        <span class="mlt-card__icon">🔑</span>
                        <h2>Top Keywords</h2>
                    </div>
                    <div class="mlt-card__body" style="padding:0">
                        <table class="wp-list-table widefat fixed striped mlt-table">
                            <thead><tr>
                                <th>Keyword</th>
                                <th style="width:70px;text-align:right">Klicks</th>
                                <th style="width:90px;text-align:right">Impressionen</th>
                                <th style="width:60px;text-align:right">Position</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ( $queries as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $row['query'] ); ?></td>
                                    <td style="text-align:right"><?php echo number_format( $row['clicks'], 0, ',', '.' ); ?></td>
                                    <td style="text-align:right"><?php echo number_format( $row['impressions'], 0, ',', '.' ); ?></td>
                                    <td style="text-align:right">
                                        <span class="mlt-pos mlt-pos--<?php echo $row['position'] <= 3 ? 'top' : ( $row['position'] <= 10 ? 'mid' : 'low' ); ?>">
                                            <?php echo number_format( $row['position'], 1, ',', '' ); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Top Seiten -->
                <?php if ( ! empty( $pages ) ) : ?>
                <div class="mlt-card">
                    <div class="mlt-card__header">
                        <span class="mlt-card__icon">📄</span>
                        <h2>Top Seiten (GSC)</h2>
                    </div>
                    <div class="mlt-card__body" style="padding:0">
                        <table class="wp-list-table widefat fixed striped mlt-table">
                            <thead><tr>
                                <th>URL</th>
                                <th style="width:70px;text-align:right">Klicks</th>
                                <th style="width:60px;text-align:right">Position</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ( $pages as $row ) : ?>
                                <?php $short = preg_replace( '#^https?://[^/]+#', '', $row['url'] ); ?>
                                <tr>
                                    <td><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( strlen( $short ) > 50 ? substr( $short, 0, 47 ) . '…' : $short ); ?></a></td>
                                    <td style="text-align:right"><?php echo number_format( $row['clicks'], 0, ',', '.' ); ?></td>
                                    <td style="text-align:right">
                                        <span class="mlt-pos mlt-pos--<?php echo $row['position'] <= 3 ? 'top' : ( $row['position'] <= 10 ? 'mid' : 'low' ); ?>">
                                            <?php echo number_format( $row['position'], 1, ',', '' ); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Traffic-Quellen -->
                <?php if ( ! empty( $analytics_sources ) ) : ?>
                <div class="mlt-card">
                    <div class="mlt-card__header">
                        <span class="mlt-card__icon">📡</span>
                        <h2>Traffic-Quellen</h2>
                    </div>
                    <div class="mlt-card__body" style="padding:0">
                        <table class="wp-list-table widefat fixed striped mlt-table">
                            <thead><tr><th>Quelle</th><th style="width:90px;text-align:right">Sessions</th></tr></thead>
                            <tbody>
                                <?php foreach ( $analytics_sources as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $row['source'] ); ?></td>
                                    <td style="text-align:right"><?php echo number_format( $row['sessions'], 0, ',', '.' ); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <?php if ( $has_gsc ) : ?>
            <p style="margin-top:16px">
                <button type="button" class="button button-secondary" id="mlt-refresh-gsc">
                    🔄 Daten aktualisieren
                </button>
                <span id="mlt-refresh-result" style="margin-left:12px;font-size:13px;color:#6b7280"></span>
            </p>
            <?php endif; ?>

        </div><!-- .wrap -->

        <style>
        .mlt-daterange-bar {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .mlt-daterange-shortcuts {
            display: flex;
            gap: 6px;
        }
        .mlt-daterange-custom {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }
        .mlt-daterange-custom__label {
            font-size: 13px;
            color: #6b7280;
            white-space: nowrap;
        }
        .mlt-daterange-custom input[type="text"] {
            width: 110px;
            font-size: 13px;
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            cursor: pointer;
        }
        .mlt-daterange-custom input.mlt-date-active {
            border-color: #2271b1;
            background: #f0f6fc;
        }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var baseUrl = <?php echo wp_json_encode( $base_url ); ?>;

            // Flatpickr initialisieren
            if ( typeof flatpickr === 'undefined' ) return;

            var fpStart = flatpickr('#mlt-date-start', {
                dateFormat: 'Y-m-d',
                maxDate:    'today',
                locale:     { firstDayOfWeek: 1 },
                onReady: function(_, __, fp) {
                    fp.calendarContainer.classList.add('mlt-fp');
                },
            });

            var fpEnd = flatpickr('#mlt-date-end', {
                dateFormat: 'Y-m-d',
                maxDate:    'today',
                locale:     { firstDayOfWeek: 1 },
                onReady: function(_, __, fp) {
                    fp.calendarContainer.classList.add('mlt-fp');
                },
            });

            // Anwenden-Button
            document.getElementById('mlt-date-apply').addEventListener('click', function () {
                var start = document.getElementById('mlt-date-start').value;
                var end   = document.getElementById('mlt-date-end').value;

                if ( ! start || ! end ) {
                    alert('Bitte beide Daten auswählen.');
                    return;
                }

                if ( start > end ) {
                    alert('Das Startdatum muss vor dem Enddatum liegen.');
                    return;
                }

                window.location.href = baseUrl + '&mlt_start=' + encodeURIComponent(start) + '&mlt_end=' + encodeURIComponent(end);
            });
        });
        </script>
        <?php
    }

    private function kpi( string $label, $value, string $class = '' ) {
        echo '<div class="mlt-kpi ' . esc_attr( $class ) . '">';
        echo '<div class="mlt-kpi__value">' . ( is_numeric( $value ) ? number_format( (float) $value, 0, ',', '.' ) : esc_html( $value ) ) . '</div>';
        echo '<div class="mlt-kpi__label">' . esc_html( $label ) . '</div>';
        echo '</div>';
    }

    // ── WP-Dashboard-Widget ───────────────────────────────────────────────────

    public function register_widget() {
        wp_add_dashboard_widget(
            'mlt_seo_widget',
            '📊 SEO Übersicht',
            [ $this, 'render_widget' ]
        );
    }

    public function render_widget() {
        $gsc       = MLT_GSC_API::instance();
        $connected = $gsc->is_connected() && $gsc->is_configured();
        $default   = (int) get_option( 'mlt_default_range', 28 );
        $start     = gmdate( 'Y-m-d', strtotime( "-{$default} days" ) );
        $end       = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
        $overview  = $connected ? $gsc->get_overview( $start, $end ) : [];
        ?>
        <?php if ( ! $connected ) : ?>
            <p style="color:#9ca3af;font-size:13px">
                GSC nicht verbunden.
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=media-lab-seo' ) ); ?>">Einstellungen</a>
            </p>
        <?php else : ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <?php $this->mini_kpi( 'Klicks',       $overview['clicks']      ?? 0, '#2563eb' ); ?>
                <?php $this->mini_kpi( 'Impressionen', $overview['impressions'] ?? 0, '#7c3aed' ); ?>
                <?php $this->mini_kpi( 'CTR',          ( $overview['ctr'] ?? 0 ) . '%', '#16a34a' ); ?>
                <?php $this->mini_kpi( 'Ø Position',   $overview['position']    ?? 0, '#d97706' ); ?>
            </div>
            <p style="font-size:11px;color:#9ca3af;margin:0">
                Letzte <?php echo esc_html( $default ); ?> Tage &nbsp;·&nbsp;
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mlt-dashboard' ) ); ?>">Dashboard öffnen →</a>
            </p>
        <?php endif; ?>
        <?php
    }

    private function mini_kpi( string $label, $value, string $color ) {
        echo '<div style="text-align:center;padding:8px;background:#f9fafb;border-radius:6px">';
        echo '<div style="font-size:18px;font-weight:700;color:' . esc_attr( $color ) . '">' . esc_html( is_numeric( $value ) ? number_format( (float) $value, 0, ',', '.' ) : $value ) . '</div>';
        echo '<div style="font-size:11px;color:#6b7280;margin-top:2px">' . esc_html( $label ) . '</div>';
        echo '</div>';
    }

    // ── AJAX: Cache leeren ────────────────────────────────────────────────────

    public function ajax_refresh() {
        check_ajax_referer( 'mlt_dashboard', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        // Alle GSC + GA4 Transients löschen
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_mlt_gsc_%'
                OR option_name LIKE '_transient_timeout_mlt_gsc_%'
                OR option_name LIKE '_transient_mlt_ga4_%'
                OR option_name LIKE '_transient_timeout_mlt_ga4_%'"
        );

        wp_send_json_success( 'Cache geleert. Seite wird neu geladen.' );
    }
}
