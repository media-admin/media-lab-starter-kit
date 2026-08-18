<?php
defined( 'ABSPATH' ) || exit;

$logs = MLBKP_Logger::get_logs( 100 );
?>

<div class="mlb-card">
    <div class="mlb-logs-header">
        <h2 class="mlb-card-title" style="margin-bottom:0 !important; border-bottom:none; padding-bottom:0;">📋 Backup-Protokoll</h2>
        <button type="button" id="mlb-cleanup-stuck" class="button button-secondary">
            🧹 Hängende Jobs bereinigen
        </button>
    </div>
    <p id="mlb-cleanup-result" class="mlb-inline-status" style="margin:8px 0 16px; display:none;"></p>

    <?php if ( empty( $logs ) ): ?>
        <p class="mlb-empty">Noch keine Backups ausgeführt.</p>
    <?php else: ?>

    <table class="widefat mlb-logs-table">
        <thead>
            <tr>
                <th>Datum / Zeit</th>
                <th>Typ</th>
                <th>Status</th>
                <th>Größe</th>
                <th>Dauer</th>
                <th>Datei</th>
                <th>Auslöser</th>
                <th>Fehler</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $logs as $log ): ?>
            <tr class="mlb-log-row mlb-status-<?php echo esc_attr( $log['status'] ); ?>">
                <td>
                    <strong><?php echo esc_html( wp_date( 'd.m.Y', strtotime( $log['started_at'] ) ) ); ?></strong><br>
                    <small><?php echo esc_html( wp_date( 'H:i:s', strtotime( $log['started_at'] ) ) ); ?></small>
                </td>
                <td>
                    <?php
                    $type_labels = [
                        'database'  => '🗄 Datenbank',
                        'wpcontent' => '📁 wp-content',
                        'wpcore'    => '⚙️ WP-Core',
                        'full'      => '📦 Vollständig',
                    ];
                    echo esc_html( $type_labels[ $log['backup_type'] ] ?? $log['backup_type'] );
                    ?>
                </td>
                <td>
                    <?php
                    $status_labels = [
                        'success'   => '<span class="mlb-badge mlb-badge-success">✅ Erfolgreich</span>',
                        'error'     => '<span class="mlb-badge mlb-badge-error">❌ Fehler</span>',
                        'running'   => '<span class="mlb-badge mlb-badge-running">⏳ Läuft</span>',
                        'cancelled' => '<span class="mlb-badge mlb-badge-cancelled">🛑 Abgebrochen</span>',
                    ];
                    echo $status_labels[ $log['status'] ] ?? esc_html( $log['status'] );
                    ?>
                </td>
                <td><?php echo $log['file_size'] ? esc_html( MLBKP_Logger::format_bytes( (int) $log['file_size'] ) ) : '—'; ?></td>
                <td><?php echo esc_html( MLBKP_Logger::format_duration( isset( $log['duration_sec'] ) ? (int) $log['duration_sec'] : null ) ); ?></td>
                <td>
                    <?php if ( $log['file_name'] ): ?>
                        <code class="mlb-filename"><?php echo esc_html( $log['file_name'] ); ?></code>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $trigger_labels = [ 'manual' => '👤 Manuell', 'cron' => '🕐 Zeitplan' ];
                    echo esc_html( $trigger_labels[ $log['triggered_by'] ] ?? $log['triggered_by'] );
                    ?>
                </td>
                <td>
                    <?php if ( $log['error_message'] ): ?>
                        <span class="mlb-error-msg" title="<?php echo esc_attr( $log['error_message'] ); ?>">
                            <?php echo esc_html( mb_strimwidth( $log['error_message'], 0, 60, '…' ) ); ?>
                        </span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php endif; ?>
</div>
