<?php
/**
 * Mail Log Report
 *
 * Periodischer Report (täglich / wöchentlich / monatlich, konfigurierbar)
 * über den Versandstatus aus dem Mail-Log (siehe inc/mail-log.php):
 * Anzahl gesendet/fehlgeschlagen, Erfolgsquote, häufigste Fehlerursachen.
 *
 * Eigene, in sich geschlossene Settings-Seite (analog zu inc/smtp-oauth.php)
 * – kein Eingriff in bestehende Dateien nötig.
 *
 * ── Cron-Strategie ───────────────────────────────────────────────
 * Bewusst KEIN wiederkehrendes wp_schedule_event() mit fixem Intervall:
 * WP-Cron kennt kein natives "monatlich"-Intervall, ein selbst
 * registriertes würde bei unterschiedlichen Monatslängen driften.
 * Stattdessen: nach jedem Versand wird der nächste Termin anhand der
 * aktuellen Einstellungen neu berechnet und einzeln per
 * wp_schedule_single_event() eingeplant (Selbstverkettung) – ähnliche
 * Idee wie die Chunk-Jobs in media-lab-backup, nur für einen
 * einzelnen wiederkehrenden Termin statt einer Job-Kette.
 *
 * @package MediaLab_Core
 * @since   1.26.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Mail_Log_Report {

	const OPT_ENABLED      = 'medialab_mail_report_enabled';
	const OPT_FREQUENCY    = 'medialab_mail_report_frequency';   // daily | weekly | monthly
	const OPT_WEEKDAY      = 'medialab_mail_report_weekday';     // 0-6, für weekly
	const OPT_DAY_OF_MONTH = 'medialab_mail_report_day';         // 1-28, für monthly
	const OPT_TIME         = 'medialab_mail_report_time';        // "HH:MM"
	const OPT_TIMEZONE     = 'medialab_mail_report_timezone';
	const OPT_RECIPIENTS   = 'medialab_mail_report_recipients';
	const OPT_LAST_SENT    = 'medialab_mail_report_last_sent';
	const OPT_LAST_STATUS  = 'medialab_mail_report_last_status';

	const SETTINGS_GROUP = 'medialab_mail_report_group';
	const CRON_HOOK       = 'medialab_mail_report_send';
	const MENU_SLUG        = 'agency-core-mail-report';

	private static $instance = null;

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ), 21 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( self::CRON_HOOK, array( $this, 'send' ) );
		add_action( 'init', array( $this, 'maybe_init_cron' ) );
		add_action( 'wp_ajax_medialab_mail_report_test', array( $this, 'ajax_send_test' ) );

		foreach ( array( self::OPT_FREQUENCY, self::OPT_WEEKDAY, self::OPT_DAY_OF_MONTH, self::OPT_TIME, self::OPT_TIMEZONE ) as $key ) {
			add_action( "update_option_{$key}", array( $this, 'reschedule' ) );
		}
		add_action( 'update_option_' . self::OPT_ENABLED, array( $this, 'sync_cron' ), 10, 2 );
	}

	// ─────────────────────────────────────────────────────────────
	// Settings API
	// ─────────────────────────────────────────────────────────────

	public function register_settings(): void {
		register_setting( self::SETTINGS_GROUP, self::OPT_ENABLED );

		register_setting( self::SETTINGS_GROUP, self::OPT_FREQUENCY, array(
			'sanitize_callback' => function ( $v ) {
				return in_array( $v, array( 'daily', 'weekly', 'monthly' ), true ) ? $v : 'weekly';
			},
		) );

		register_setting( self::SETTINGS_GROUP, self::OPT_WEEKDAY, array( 'sanitize_callback' => 'absint' ) );

		register_setting( self::SETTINGS_GROUP, self::OPT_DAY_OF_MONTH, array(
			'sanitize_callback' => function ( $v ) { return max( 1, min( 28, (int) $v ) ); },
		) );

		register_setting( self::SETTINGS_GROUP, self::OPT_TIME, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( self::SETTINGS_GROUP, self::OPT_TIMEZONE, array( 'sanitize_callback' => 'sanitize_text_field' ) );

		register_setting( self::SETTINGS_GROUP, self::OPT_RECIPIENTS, array(
			'sanitize_callback' => array( $this, 'sanitize_recipients' ),
		) );
	}

	public function sanitize_recipients( $input ): array {
		if ( ! is_array( $input ) ) return array();
		return array_values( array_filter( array_map( 'sanitize_email', $input ), 'is_email' ) );
	}

	public function get_recipients(): array {
		$list = get_option( self::OPT_RECIPIENTS, array() );
		return is_array( $list ) ? $list : array();
	}

	// ─────────────────────────────────────────────────────────────
	// Schedule-Berechnung
	// ─────────────────────────────────────────────────────────────

	public function get_schedule(): array {
		return array(
			'frequency'    => get_option( self::OPT_FREQUENCY, 'weekly' ),
			'weekday'      => (int) get_option( self::OPT_WEEKDAY, 1 ),
			'day_of_month' => (int) get_option( self::OPT_DAY_OF_MONTH, 1 ),
			'time'         => (string) get_option( self::OPT_TIME, '08:00' ),
			'timezone'     => (string) get_option( self::OPT_TIMEZONE, wp_timezone_string() ),
		);
	}

	public function calculate_next_run( array $schedule ): int {
		$tz  = new \DateTimeZone( $schedule['timezone'] ?: 'UTC' );
		$now = new \DateTime( 'now', $tz );

		$parts  = explode( ':', $schedule['time'] );
		$hour   = isset( $parts[0] ) ? (int) $parts[0] : 8;
		$minute = isset( $parts[1] ) ? (int) $parts[1] : 0;

		$next = clone $now;
		$next->setTime( $hour, $minute, 0 );

		switch ( $schedule['frequency'] ) {

			case 'daily':
				if ( $next <= $now ) $next->modify( '+1 day' );
				break;

			case 'monthly':
				$day = max( 1, min( 28, (int) $schedule['day_of_month'] ) );
				$next->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), $day );
				$next->setTime( $hour, $minute, 0 );
				if ( $next <= $now ) $next->modify( '+1 month' );
				break;

			case 'weekly':
			default:
				$target_day  = (int) $schedule['weekday'];
				$current_day = (int) $now->format( 'w' );
				$diff        = ( $target_day - $current_day + 7 ) % 7;
				if ( $diff === 0 && $next <= $now ) $diff = 7;
				if ( $diff > 0 ) $next->modify( "+{$diff} days" );
				break;
		}

		return $next->getTimestamp();
	}

	public function maybe_init_cron(): void {
		if ( get_option( self::OPT_ENABLED ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$this->reschedule();
		}
	}

	public function reschedule(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) wp_unschedule_event( $ts, self::CRON_HOOK );

		if ( ! get_option( self::OPT_ENABLED ) ) return;

		$next = $this->calculate_next_run( $this->get_schedule() );
		wp_schedule_single_event( $next, self::CRON_HOOK );
	}

	public function sync_cron( $old, $new ): void {
		if ( $new && ! $old ) {
			$this->reschedule();
		} elseif ( ! $new && $old ) {
			$ts = wp_next_scheduled( self::CRON_HOOK );
			if ( $ts ) wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	// ─────────────────────────────────────────────────────────────
	// Versand
	// ─────────────────────────────────────────────────────────────

	public function send(): void {
		if ( ! get_option( self::OPT_ENABLED ) ) return;

		// Selbstverkettung: nächsten Termin sofort einplanen – bleibt auch
		// bei einem Fehler im Versand selbst intakt.
		$this->reschedule();

		$to = $this->get_recipients();
		if ( empty( $to ) ) {
			$admin = get_option( 'admin_email' );
			if ( ! is_email( $admin ) ) return;
			$to = array( $admin );
		}

		list( $start, $end, $label ) = $this->get_report_range();
		$data = $this->collect_data( $start, $end );
		$html = $this->build_html( $data, $start, $end, $label );

		$subject = sprintf( '[%s] E-Mail-Report – %s', get_bloginfo( 'name' ), $label );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $html, $headers );

		update_option( self::OPT_LAST_SENT, current_time( 'mysql' ) );
		update_option( self::OPT_LAST_STATUS, $sent ? 'success' : 'failed' );
	}

	/**
	 * Zeitraum passend zur Frequenz – die jeweils zuletzt abgeschlossene
	 * Periode (z.B. bei wöchentlichem Report: die letzten 7 Tage).
	 *
	 * @return array{0:string,1:string,2:string} [start, end, label]
	 */
	private function get_report_range(): array {
		$frequency = get_option( self::OPT_FREQUENCY, 'weekly' );
		$end       = gmdate( 'Y-m-d' );

		switch ( $frequency ) {
			case 'daily':
				$start = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
				$label = 'Täglicher Report';
				break;
			case 'monthly':
				$start = gmdate( 'Y-m-d', strtotime( '-1 month' ) );
				$label = 'Monatlicher Report';
				break;
			case 'weekly':
			default:
				$start = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
				$label = 'Wöchentlicher Report';
				break;
		}

		return array( $start, $end, $label );
	}

	private function collect_data( string $start, string $end ): array {
		global $wpdb;
		$table = class_exists( 'MediaLab_Mail_Log' ) ? MediaLab_Mail_Log::get_table() : $wpdb->prefix . 'medialab_mail_log';

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) as cnt FROM {$table} WHERE created_at BETWEEN %s AND %s GROUP BY status",
			$start . ' 00:00:00', $end . ' 23:59:59'
		) );

		$stats = array( 'sent' => 0, 'failed' => 0, 'pending' => 0 );
		foreach ( $rows as $row ) {
			if ( isset( $stats[ $row->status ] ) ) $stats[ $row->status ] = (int) $row->cnt;
		}
		$stats['total'] = array_sum( $stats );
		$stats['rate']  = $stats['total'] > 0 ? round( ( $stats['sent'] / $stats['total'] ) * 100, 1 ) : 100.0;

		$top_errors = $wpdb->get_results( $wpdb->prepare(
			"SELECT error_message, COUNT(*) as cnt FROM {$table}
			 WHERE status = 'failed' AND created_at BETWEEN %s AND %s AND error_message IS NOT NULL AND error_message != ''
			 GROUP BY error_message ORDER BY cnt DESC LIMIT 5",
			$start . ' 00:00:00', $end . ' 23:59:59'
		) );

		return compact( 'stats', 'top_errors' );
	}

	private function build_html( array $data, string $start, string $end, string $label ): string {
		$site   = get_bloginfo( 'name' );
		$stats  = $data['stats'];
		$from   = wp_date( 'd.m.Y', strtotime( $start ) );
		$to_d   = wp_date( 'd.m.Y', strtotime( $end ) );
		$logurl = admin_url( 'admin.php?page=agency-core-mail-log&mla_start=' . $start . '&mla_end=' . $end );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html( $label ); ?></title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;padding:32px 16px;"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

	<tr><td style="background:#1a1a2e;border-radius:8px 8px 0 0;padding:28px 32px;">
		<h1 style="margin:0 0 4px;font-size:22px;font-weight:700;color:#fff;"><?php echo esc_html( $site ); ?></h1>
		<p style="margin:0;font-size:13px;color:#9ca3af;"><?php echo esc_html( $label ); ?> &nbsp;·&nbsp; <?php echo esc_html( "{$from} – {$to_d}" ); ?></p>
	</td></tr>

	<tr><td style="background:#fff;padding:24px 32px 8px;">
		<table width="100%" cellpadding="0" cellspacing="0" border="0">
			<tr>
				<td width="33%" style="text-align:center;padding:12px;">
					<div style="font-size:26px;font-weight:700;color:#1a1a2e;"><?php echo (int) $stats['total']; ?></div>
					<div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Gesamt</div>
				</td>
				<td width="33%" style="text-align:center;padding:12px;">
					<div style="font-size:26px;font-weight:700;color:#00a32a;"><?php echo (int) $stats['sent']; ?></div>
					<div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Gesendet</div>
				</td>
				<td width="33%" style="text-align:center;padding:12px;">
					<div style="font-size:26px;font-weight:700;color:#d63638;"><?php echo (int) $stats['failed']; ?></div>
					<div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Fehlgeschlagen</div>
				</td>
			</tr>
		</table>
		<p style="text-align:center;color:#646970;font-size:13px;margin:8px 0 0;">Erfolgsquote: <strong><?php echo esc_html( $stats['rate'] ); ?>%</strong></p>
	</td></tr>

	<?php if ( ! empty( $data['top_errors'] ) ) : ?>
	<tr><td style="background:#fff;padding:16px 32px;">
		<p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px;">Häufigste Fehlerursachen</p>
		<table width="100%" cellpadding="0" cellspacing="0" border="0">
			<?php foreach ( $data['top_errors'] as $err ) : ?>
			<tr>
				<td style="padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:13px;color:#1a1a2e;"><?php echo esc_html( wp_trim_words( $err->error_message, 12 ) ); ?></td>
				<td style="padding:6px 0;border-bottom:1px solid #f0f0f1;font-size:13px;color:#d63638;text-align:right;white-space:nowrap;"><?php echo (int) $err->cnt; ?>×</td>
			</tr>
			<?php endforeach; ?>
		</table>
	</td></tr>
	<?php endif; ?>

	<tr><td style="background:#fff;padding:20px 32px 28px;border-radius:0 0 8px 8px;text-align:center;">
		<a href="<?php echo esc_url( $logurl ); ?>" style="display:inline-block;background:#1a1a2e;color:#fff;text-decoration:none;padding:10px 24px;border-radius:4px;font-size:13px;font-weight:600;">Vollständiges Log ansehen</a>
	</td></tr>
</table>
</td></tr></table>
</body></html>
		<?php
		return ob_get_clean();
	}

	// ─────────────────────────────────────────────────────────────
	// Test-Mail
	// ─────────────────────────────────────────────────────────────

	public function ajax_send_test(): void {
		check_ajax_referer( 'medialab_mail_report_test', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Keine Berechtigung.' );

		$to = $this->get_recipients();
		$to = ! empty( $to ) ? $to[0] : get_option( 'admin_email' );

		list( $start, $end, $label ) = $this->get_report_range();
		$data = $this->collect_data( $start, $end );
		$html = $this->build_html( $data, $start, $end, $label . ' (Test)' );

		$sent = wp_mail( $to, '[Test] ' . get_bloginfo( 'name' ) . ' – E-Mail-Report', $html, array( 'Content-Type: text/html; charset=UTF-8' ) );

		if ( $sent ) {
			wp_send_json_success( 'Test-Report gesendet an ' . esc_html( $to ) );
		} else {
			wp_send_json_error( 'Versand fehlgeschlagen. SMTP-Konfiguration prüfen.' );
		}
	}

	// ─────────────────────────────────────────────────────────────
	// Settings-Seite
	// ─────────────────────────────────────────────────────────────

	public function add_admin_page(): void {
		add_submenu_page(
			'agency-core',
			'E-Mail-Report',
			'E-Mail-Report',
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$enabled     = get_option( self::OPT_ENABLED, 0 );
		$schedule    = $this->get_schedule();
		$recipients  = $this->get_recipients();
		$last_sent   = get_option( self::OPT_LAST_SENT, '' );
		$last_status = get_option( self::OPT_LAST_STATUS, '' );
		$next_ts     = wp_next_scheduled( self::CRON_HOOK );

		$weekdays  = array( 1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 0 => 'Sonntag' );
		$timezones = array(
			'Europe/Vienna' => 'Wien (CET/CEST)',
			'Europe/Berlin' => 'Berlin (CET/CEST)',
			'Europe/Zurich' => 'Zürich (CET/CEST)',
			'UTC'           => 'UTC',
		);
		?>
		<div class="wrap">
			<h1>E-Mail-Report</h1>
			<p class="description">Periodischer Report über den Status aller versendeten E-Mails (siehe <a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-core-mail-log' ) ); ?>">E-Mail-Log</a>).</p>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>
			<?php endif; ?>

			<?php if ( $last_sent ) : ?>
				<p class="description">
					Letzter Versand: <strong><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $last_sent ) ) ); ?></strong>
					– <?php echo $last_status === 'success' ? '✓ erfolgreich' : '✗ fehlgeschlagen'; ?>
				</p>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Report aktivieren</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPT_ENABLED ); ?>" value="1" <?php checked( $enabled, 1 ); ?>>
								Automatischen E-Mail-Report versenden
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Empfänger</th>
						<td>
							<div id="mla-report-recipients">
								<?php $display = ! empty( $recipients ) ? $recipients : array( '' ); ?>
								<?php foreach ( $display as $email ) : ?>
								<div class="mla-recipient-row" style="display:flex;gap:8px;margin-bottom:6px;">
									<input type="email" name="<?php echo esc_attr( self::OPT_RECIPIENTS ); ?>[]" class="regular-text" placeholder="empfaenger@example.com" value="<?php echo esc_attr( $email ); ?>">
									<button type="button" class="button mla-recipient-remove">✕</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" id="mla-recipient-add" class="button button-secondary">+ Empfänger hinzufügen</button>
							<p class="description">Fallback: Admin-E-Mail, falls keine Empfänger eingetragen sind.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Frequenz</th>
						<td>
							<select name="<?php echo esc_attr( self::OPT_FREQUENCY ); ?>" id="mla-report-frequency">
								<option value="daily" <?php selected( $schedule['frequency'], 'daily' ); ?>>Täglich</option>
								<option value="weekly" <?php selected( $schedule['frequency'], 'weekly' ); ?>>Wöchentlich</option>
								<option value="monthly" <?php selected( $schedule['frequency'], 'monthly' ); ?>>Monatlich</option>
							</select>
						</td>
					</tr>
					<tr id="mla-row-weekday" <?php echo $schedule['frequency'] !== 'weekly' ? 'style="display:none;"' : ''; ?>>
						<th scope="row">Wochentag</th>
						<td>
							<select name="<?php echo esc_attr( self::OPT_WEEKDAY ); ?>">
								<?php foreach ( $weekdays as $val => $wlabel ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $schedule['weekday'], $val ); ?>><?php echo esc_html( $wlabel ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="mla-row-dayofmonth" <?php echo $schedule['frequency'] !== 'monthly' ? 'style="display:none;"' : ''; ?>>
						<th scope="row">Tag im Monat</th>
						<td>
							<input type="number" min="1" max="28" name="<?php echo esc_attr( self::OPT_DAY_OF_MONTH ); ?>" value="<?php echo esc_attr( $schedule['day_of_month'] ); ?>" style="width:80px;">
							<p class="description">1–28 (zur Sicherheit bei kurzen Monaten wie Februar).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Uhrzeit</th>
						<td><input type="time" name="<?php echo esc_attr( self::OPT_TIME ); ?>" value="<?php echo esc_attr( $schedule['time'] ); ?>" step="300"></td>
					</tr>
					<tr>
						<th scope="row">Zeitzone</th>
						<td>
							<select name="<?php echo esc_attr( self::OPT_TIMEZONE ); ?>">
								<?php foreach ( $timezones as $tzk => $tzl ) : ?>
								<option value="<?php echo esc_attr( $tzk ); ?>" <?php selected( $schedule['timezone'], $tzk ); ?>><?php echo esc_html( $tzl ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<?php if ( $next_ts ) : ?>
					<p class="description" style="padding:8px 12px;background:#f6f7f7;border-left:3px solid #2271b1;">
						Nächster geplanter Versand: <strong><?php echo esc_html( wp_date( 'd.m.Y H:i', $next_ts ) ); ?></strong>
					</p>
				<?php endif; ?>

				<?php submit_button( 'Einstellungen speichern' ); ?>
			</form>

			<p>
				<button type="button" class="button" id="mla-report-test">Test-Report senden</button>
				<span id="mla-report-test-result" style="margin-left:8px;font-weight:600;"></span>
			</p>
		</div>

		<script>
		(function($){
			$('#mla-report-frequency').on('change', function(){
				var v = $(this).val();
				$('#mla-row-weekday').toggle( v === 'weekly' );
				$('#mla-row-dayofmonth').toggle( v === 'monthly' );
			});

			var $list = $('#mla-report-recipients');
			$('#mla-recipient-add').on('click', function(){
				var row = $('.mla-recipient-row').first().clone();
				row.find('input').val('');
				$list.append(row);
			});
			$list.on('click', '.mla-recipient-remove', function(){
				if ( $('.mla-recipient-row').length > 1 ) {
					$(this).closest('.mla-recipient-row').remove();
				} else {
					$(this).closest('.mla-recipient-row').find('input').val('');
				}
			});

			$('#mla-report-test').on('click', function(){
				var $btn = $(this), $result = $('#mla-report-test-result');
				$btn.prop('disabled', true);
				$result.text('Wird gesendet…').css('color', '#646970');
				$.post( ajaxurl, {
					action: 'medialab_mail_report_test',
					nonce: '<?php echo esc_js( wp_create_nonce( 'medialab_mail_report_test' ) ); ?>'
				}).done(function(res){
					$result.text(res.data).css('color', res.success ? '#00a32a' : '#d63638');
				}).fail(function(){
					$result.text('Fehler bei der Anfrage.').css('color', '#d63638');
				}).always(function(){
					$btn.prop('disabled', false);
				});
			});
		})(jQuery);
		</script>
		<?php
	}
}

MediaLab_Mail_Log_Report::get_instance();
