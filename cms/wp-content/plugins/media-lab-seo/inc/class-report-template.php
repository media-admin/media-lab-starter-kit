<?php
/**
 * MLT_Report_Template
 *
 * Erstellt das vollständige HTML-E-Mail-Template für den wöchentlichen SEO-Report.
 * Inline-CSS für maximale E-Mail-Client-Kompatibilität.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Report_Template {

    public static function build( array $data ) : string {
        $site      = get_bloginfo( 'name' );
        $url       = home_url( '/' );
        $week      = wp_date( 'W' );
        $year      = wp_date( 'Y' );

        // Zeitraum kommt jetzt aus den echten Report-Daten statt eigener Berechnung
        $range       = $data['range'] ?? [];
        $range_start = $range['start'] ?? gmdate( 'Y-m-d', strtotime( '-28 days' ) );
        $range_end   = $range['end']   ?? gmdate( 'Y-m-d', strtotime( '-2 days' ) );
        $date_from   = wp_date( 'd.m.Y', strtotime( $range_start ) );
        $date_to     = wp_date( 'd.m.Y', strtotime( $range_end ) );
        $range_days  = (int) round( ( strtotime( $range_end ) - strtotime( $range_start ) ) / DAY_IN_SECONDS ) + 1; // Start- und Endtag inklusive

        $gsc      = $data['gsc_overview']   ?? [];
        $queries  = $data['gsc_queries']    ?? [];
        $pages    = $data['gsc_pages']      ?? [];
        $a_over   = $data['analytics']      ?? [];
        $a_src    = $data['analytics_sources'] ?? [];

        $compare   = $data['compare'] ?? [];
        $cmp_range = $compare['range'] ?? null;
        $g_d       = $compare['gsc_deltas']       ?? [];
        $a_d       = $compare['analytics_deltas'] ?? [];
        $q_d       = $compare['query_deltas'] ?? [];
        $pg_d      = $compare['page_deltas']  ?? [];
        $cmp_name  = $cmp_range['label'] ?? '';

        // Säulendiagramme (Tageswerte) – nur, wenn vorhanden
        $charts = [];
        foreach ( (array) ( $data['charts'] ?? [] ) as $c ) {
            if ( isset( $c['key'] ) ) $charts[ $c['key'] ] = $c;
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SEO Report KW <?php echo (int) $week; ?>/<?php echo (int) $year; ?></title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;padding:32px 16px">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Header -->
    <tr>
        <td style="background:#1a1a2e;border-radius:8px 8px 0 0;padding:28px 32px">
            <h1 style="margin:0 0 4px;font-size:22px;font-weight:700;color:#fff"><?php echo esc_html( $site ); ?></h1>
            <p style="margin:0;font-size:13px;color:#9ca3af">
                SEO Report &nbsp;·&nbsp; KW <?php echo (int) $week; ?>/<?php echo (int) $year; ?>
                &nbsp;·&nbsp; Zeitraum: <?php echo $range_days; ?> Tage
                <span style="color:#6b7280">(<?php echo esc_html( $date_from ); ?> – <?php echo esc_html( $date_to ); ?>)</span>
                <?php if ( $cmp_range ) : ?>
                <br><span style="color:#6b7280">Vergleich: <?php echo esc_html( MLT_Compare::describe( $cmp_range ) ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $compare['notice'] ) ) : ?>
                <br><span style="color:#fbbf24"><?php echo esc_html( $compare['notice'] ); ?></span>
                <?php endif; ?>
            </p>
        </td>
    </tr>

    <?php if ( ! empty( $gsc ) ) : ?>
    <!-- GSC KPIs -->
    <tr>
        <td style="background:#fff;padding:24px 32px 8px">
            <p style="margin:0 0 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">Google Search Console</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <?php self::kpi_cell( 'Klicks',       number_format( $gsc['clicks'] ?? 0,       0, ',', '.' ), '#2563eb', $g_d['clicks'] ?? null ); ?>
                    <?php self::kpi_cell( 'Impressionen', number_format( $gsc['impressions'] ?? 0,  0, ',', '.' ), '#7c3aed', $g_d['impressions'] ?? null ); ?>
                    <?php self::kpi_cell( 'Ø CTR',        number_format( (float) ( $gsc['ctr'] ?? 0 ), 1, ',', '' ) . ' %', '#16a34a', $g_d['ctr'] ?? null ); ?>
                    <?php self::kpi_cell( 'Ø Position',   isset( $gsc['position'] ) ? number_format( (float) $gsc['position'], 1, ',', '' ) : '–', '#d97706', $g_d['position'] ?? null ); ?>
                </tr>
            </table>
            <?php if ( isset( $charts['clicks'] ) ) echo MLT_Chart::mail_columns( $charts['clicks'], $cmp_range ); // HTML wird in MLT_Chart escaped ?>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $a_over ) ) : ?>
    <!-- Analytics KPIs -->
    <tr>
        <td style="background:#fff;padding:8px 32px 24px">
            <p style="margin:0 0 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">Analytics</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <?php self::kpi_cell( 'Seitenaufrufe', number_format( $a_over['pageviews'] ?? 0, 0, ',', '.' ), '#0891b2', $a_d['pageviews'] ?? null ); ?>
                    <?php self::kpi_cell( 'Sessions',      number_format( $a_over['sessions']  ?? 0, 0, ',', '.' ), '#0284c7', $a_d['sessions'] ?? null ); ?>
                    <?php self::kpi_cell( 'Nutzer',        number_format( $a_over['users']     ?? 0, 0, ',', '.' ), '#7c3aed', $a_d['users'] ?? null ); ?>
                    <td width="25%"></td>
                </tr>
            </table>
            <?php if ( isset( $charts['pageviews'] ) ) echo MLT_Chart::mail_columns( $charts['pageviews'], $cmp_range ); // HTML wird in MLT_Chart escaped ?>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $compare['note'] ) ) : ?>
    <!-- Hinweis Messtoleranz -->
    <tr>
        <td style="background:#fff;padding:0 32px 20px">
            <p style="margin:0;font-size:11px;line-height:1.55;color:#9ca3af"><?php echo esc_html( $compare['note'] ); ?></p>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $queries ) ) : ?>
    <!-- Top Keywords -->
    <tr>
        <td style="background:#fff;padding:0 32px 24px">
            <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 20px">
            <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">🔑 Top Keywords<?php if ( $q_d && $cmp_name ) : ?> &nbsp;·&nbsp; Δ vs. <?php echo esc_html( $cmp_name ); ?><?php endif; ?></p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr style="background:#f9fafb">
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">#</th>
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Keyword</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Klicks</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Impr.</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Pos.</th>
                </tr>
                <?php
                $q_rows = array_slice( $queries, 0, 8 );
                $q_max  = max( 1, ...array_map( 'intval', array_column( $q_rows, 'clicks' ) ?: [ 1 ] ) );
                foreach ( $q_rows as $i => $row ) : ?>
                <tr style="background:<?php echo $i % 2 === 0 ? '#fff' : '#f9fafb'; ?>">
                    <td style="padding:8px 10px;font-size:13px;color:#9ca3af" valign="top"><?php echo $i + 1; ?></td>
                    <td style="padding:8px 10px;font-size:13px;color:#374151"><?php echo esc_html( $row['query'] ); ?><?php echo MLT_Chart::mail_bar( (int) $row['clicks'] / $q_max, '#2563eb' ); ?></td>
                    <td style="padding:8px 10px;font-size:13px;color:#374151;text-align:right"><?php echo number_format( $row['clicks'], 0, ',', '.' ); ?><?php echo MLT_Delta::html_mail_inline( $q_d[ $row['query'] ]['clicks'] ?? null ); ?></td>
                    <td style="padding:8px 10px;font-size:13px;color:#6b7280;text-align:right"><?php echo number_format( $row['impressions'], 0, ',', '.' ); ?></td>
                    <td style="padding:8px 10px;text-align:right">
                        <?php
                        $pos = $row['position'];
                        $bg  = $pos <= 3 ? '#d1fae5' : ( $pos <= 10 ? '#fef3c7' : '#fee2e2' );
                        $fg  = $pos <= 3 ? '#065f46' : ( $pos <= 10 ? '#92400e' : '#991b1b' );
                        ?>
                        <span style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;border-radius:4px;padding:2px 7px;font-size:12px;font-weight:600">
                            <?php echo number_format( $pos, 1, ',', '' ); ?>
                        </span><?php echo MLT_Delta::html_mail_inline( $q_d[ $row['query'] ]['position'] ?? null ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $pages ) ) : ?>
    <!-- Top Seiten -->
    <tr>
        <td style="background:#fff;padding:0 32px 24px">
            <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 20px">
            <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">📄 Top Seiten<?php if ( $pg_d && $cmp_name ) : ?> &nbsp;·&nbsp; Δ vs. <?php echo esc_html( $cmp_name ); ?><?php endif; ?></p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr style="background:#f9fafb">
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Seite</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Klicks</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Pos.</th>
                </tr>
                <?php
                $p_rows   = array_slice( $pages, 0, 8 );
                $p_labels = MLT_Compare::page_labels( array_column( $p_rows, 'url' ), 45 );
                $p_max  = max( 1, ...array_map( 'intval', array_column( $p_rows, 'clicks' ) ?: [ 1 ] ) );
                foreach ( $p_rows as $i => $row ) :
                    $short = $p_labels[ $row['url'] ] ?? '/';
                    $pos   = $row['position'];
                    $bg    = $pos <= 3 ? '#d1fae5' : ( $pos <= 10 ? '#fef3c7' : '#fee2e2' );
                    $fg    = $pos <= 3 ? '#065f46' : ( $pos <= 10 ? '#92400e' : '#991b1b' );
                ?>
                <tr style="background:<?php echo $i % 2 === 0 ? '#fff' : '#f9fafb'; ?>">
                    <td style="padding:8px 10px;font-size:12px;color:#2563eb">
                        <a href="<?php echo esc_url( $row['url'] ); ?>" style="color:#2563eb;text-decoration:none"><?php echo esc_html( $short ); ?></a>
                        <?php echo MLT_Chart::mail_bar( (int) $row['clicks'] / $p_max, '#2563eb' ); ?>
                    </td>
                    <td style="padding:8px 10px;font-size:13px;color:#374151;text-align:right"><?php echo number_format( $row['clicks'], 0, ',', '.' ); ?><?php echo MLT_Delta::html_mail_inline( $pg_d[ $row['url'] ]['clicks'] ?? null ); ?></td>
                    <td style="padding:8px 10px;text-align:right">
                        <span style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;border-radius:4px;padding:2px 7px;font-size:12px;font-weight:600">
                            <?php echo number_format( $pos, 1, ',', '' ); ?>
                        </span><?php echo MLT_Delta::html_mail_inline( $pg_d[ $row['url'] ]['position'] ?? null ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $a_src ) ) : ?>
    <!-- Traffic-Quellen -->
    <tr>
        <td style="background:#fff;padding:0 32px 24px">
            <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 20px">
            <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">📡 Traffic-Quellen</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <?php
                $s_max   = max( 1, ...array_map( 'intval', array_column( $a_src, 'sessions' ) ?: [ 1 ] ) );
                $s_total = (int) ( $a_over['sessions'] ?? 0 );
                foreach ( $a_src as $i => $row ) :
                    $share = ( $s_total > 0 && (int) $row['sessions'] <= $s_total ) ? round( (int) $row['sessions'] / $s_total * 100 ) : null;
                ?>
                <tr style="background:<?php echo $i % 2 === 0 ? '#fff' : '#f9fafb'; ?>">
                    <td style="padding:8px 10px;font-size:13px;color:#374151"><?php echo esc_html( $row['source'] ); ?><?php echo MLT_Chart::mail_bar( (int) $row['sessions'] / $s_max, '#0284c7' ); ?></td>
                    <td style="padding:8px 10px;font-size:13px;color:#6b7280;text-align:right" valign="top"><?php echo number_format( $row['sessions'], 0, ',', '.' ); ?> Sessions<?php if ( $share !== null ) : ?><br><span style="font-size:11px;color:#9ca3af"><?php echo (int) $share; ?> %</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Footer -->
    <tr>
        <td style="background:#f9fafb;border-radius:0 0 8px 8px;padding:20px 32px;border-top:1px solid #e5e7eb">
            <p style="margin:0;font-size:12px;color:#9ca3af">
                Dieser Report wird automatisch von
                <a href="<?php echo esc_url( $url ); ?>" style="color:#6b7280"><?php echo esc_html( $site ); ?></a>
                via <strong>Media Lab SEO Toolkit</strong> gesendet. &nbsp;·&nbsp;
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=media-lab-seo' ) ); ?>" style="color:#6b7280">Report deaktivieren</a>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>

</body>
</html>
        <?php
        return ob_get_clean();
    }

    private static function kpi_cell( string $label, $value, string $color, ?array $delta = null ) {
        echo '<td width="25%" style="text-align:center;padding:0 6px 16px">';
        echo '<div style="background:#f9fafb;border-radius:6px;padding:14px 8px">';
        echo '<div style="font-size:22px;font-weight:700;color:' . esc_attr( $color ) . '">' . esc_html( $value ) . '</div>';
        echo '<div style="font-size:11px;color:#9ca3af;margin-top:3px">' . esc_html( $label ) . '</div>';
        echo MLT_Delta::html_mail( $delta ); // escaped in MLT_Delta
        echo '</div></td>';
    }
}
