<?php
/**
 * Mail Log
 *
 * Protokolliert alle über wp_mail() versendeten E-Mails – Empfänger,
 * Betreff, Volltext-Inhalt, Headers, Anhänge, Status, Fehlermeldung,
 * Versand-Modus (smtp/ms365/google). Vergleichbar mit dem Email-Log
 * von WP Mail SMTP, aber ohne externe Abhängigkeit, direkt ins Agency
 * Core Plugin integriert.
 *
 * Tabelle: {$wpdb->prefix}medialab_mail_log
 *
 * ── Erfassung ────────────────────────────────────────────────────
 * 'wp_mail'-Filter (spät, Prio 999) legt pro Aufruf einen Log-Eintrag
 * mit status='pending' an und merkt sich die Insert-ID auf einem
 * kleinen Stack ($pending_ids). 'wp_mail_succeeded' (WP 5.9+) bzw.
 * 'wp_mail_failed' schließen den jeweils obersten (also zuletzt
 * offenen) Eintrag ab. Der Stack statt einer einzelnen Property
 * verträgt auch seltene verschachtelte wp_mail()-Aufrufe (z.B. wenn
 * ein wp_mail_failed-Handler selbst eine Mail verschickt).
 *
 * ── Datenschutz ──────────────────────────────────────────────────
 * Es wird bewusst der VOLLE E-Mail-Inhalt gespeichert (Konfigurations-
 * entscheidung). Für Mails mit sensiblen Inhalten (z.B. Passwort-
 * Reset-Links) kann das Logging gezielt unterdrückt werden:
 *
 *   add_filter( 'medialab_mail_log_capture', function( $capture, $args ) {
 *       if ( stripos( $args['subject'], 'Passwort zurücksetzen' ) !== false ) {
 *           return false;
 *       }
 *       return $capture;
 *   }, 10, 2 );
 *
 * Automatische Bereinigung: täglicher WP-Cron-Job löscht Einträge, die
 * älter sind als die konfigurierte Aufbewahrungsdauer (Standard: 365
 * Tage, Option 'medialab_mail_log_retention_days').
 *
 * @package MediaLab_Core
 * @since   1.26.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Mail_Log {

	const TABLE          = 'medialab_mail_log';
	const DB_VERSION_OPT  = 'medialab_mail_log_db_version';
	const DB_VERSION      = '1.0';
	const RETENTION_OPT   = 'medialab_mail_log_retention_days';
	const CLEANUP_HOOK     = 'medialab_mail_log_cleanup';
	const MENU_SLUG        = 'agency-core-mail-log';

	private static $instance = null;

	/** Stack offener Log-IDs (LIFO) – siehe Datei-Kommentar oben. */
	private $pending_ids = array();

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( defined( 'MEDIALAB_CORE_FILE' ) ) {
			register_activation_hook( MEDIALAB_CORE_FILE, array( $this, 'create_table' ) );
		}
		add_action( 'plugins_loaded', array( $this, 'maybe_create_table' ) );

		// Erfassung
		add_filter( 'wp_mail', array( $this, 'capture_mail' ), 999 );
		add_action( 'wp_mail_succeeded', array( $this, 'mark_succeeded' ) );
		add_action( 'wp_mail_failed', array( $this, 'mark_failed' ) );

		// Aufbewahrung
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_entries' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cleanup' ) );

		// Admin
		add_action( 'admin_menu', array( $this, 'add_admin_page' ), 20 );
		add_action( 'admin_post_medialab_mail_log_cleanup_now', array( $this, 'handle_cleanup_now' ) );
		add_action( 'admin_post_medialab_mail_log_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'admin_post_medialab_mail_log_clear_all', array( $this, 'handle_clear_all' ) );
	}

	// ─────────────────────────────────────────────────────────────
	// Datenbank
	// ─────────────────────────────────────────────────────────────

	public static function get_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public function maybe_create_table(): void {
		if ( get_option( self::DB_VERSION_OPT ) === self::DB_VERSION ) return;
		$this->create_table();
		update_option( self::DB_VERSION_OPT, self::DB_VERSION );
	}

	public function create_table(): void {
		global $wpdb;
		$table           = self::get_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			to_email VARCHAR(500) NOT NULL DEFAULT '',
			subject VARCHAR(500) NOT NULL DEFAULT '',
			body LONGTEXT NULL,
			headers TEXT NULL,
			attachments TEXT NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'pending',
			error_message TEXT NULL,
			mode VARCHAR(20) NOT NULL DEFAULT 'smtp',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	// ─────────────────────────────────────────────────────────────
	// Erfassung
	// ─────────────────────────────────────────────────────────────

	public function capture_mail( array $args ): array {
		$to = $args['to'] ?? '';
		if ( is_array( $to ) ) $to = implode( ', ', $to );

		/**
		 * Filter: Erfassung einer bestimmten Mail unterdrücken (z.B. für
		 * Passwort-Reset-Links). Siehe Datei-Kommentar oben.
		 *
		 * @param bool  $capture
		 * @param array $args wp_mail()-Argumente (to, subject, message, headers, attachments)
		 */
		if ( ! apply_filters( 'medialab_mail_log_capture', true, $args ) ) {
			// Platzhalter auf dem Stack, damit succeeded/failed synchron bleiben
			$this->pending_ids[] = 0;
			return $args;
		}

		$attachments = $args['attachments'] ?? array();
		if ( is_string( $attachments ) ) {
			$attachments = array_filter( array_map( 'trim', explode( "\n", $attachments ) ) );
		}
		$attachment_names = array_map( 'basename', (array) $attachments );

		$headers = $args['headers'] ?? '';
		if ( is_array( $headers ) ) $headers = implode( "\n", $headers );

		global $wpdb;
		$wpdb->insert(
			self::get_table(),
			array(
				'to_email'    => mb_substr( (string) $to, 0, 500 ),
				'subject'     => mb_substr( (string) ( $args['subject'] ?? '' ), 0, 500 ),
				'body'        => (string) ( $args['message'] ?? '' ),
				'headers'     => (string) $headers,
				'attachments' => implode( ', ', $attachment_names ),
				'status'      => 'pending',
				'mode'        => get_option( 'medialab_smtp_mode', 'smtp' ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$this->pending_ids[] = (int) $wpdb->insert_id;
		return $args;
	}

	public function mark_succeeded( $mail_data ): void {
		$id = array_pop( $this->pending_ids );
		if ( ! $id ) return;

		global $wpdb;
		$wpdb->update(
			self::get_table(),
			array( 'status' => 'sent' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public function mark_failed( \WP_Error $error ): void {
		$id = array_pop( $this->pending_ids );
		if ( ! $id ) return;

		global $wpdb;
		$wpdb->update(
			self::get_table(),
			array(
				'status'        => 'failed',
				'error_message' => mb_substr( $error->get_error_message(), 0, 2000 ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	// ─────────────────────────────────────────────────────────────
	// Aufbewahrung / Bereinigung
	// ─────────────────────────────────────────────────────────────

	public function maybe_schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public function cleanup_old_entries(): int {
		global $wpdb;
		$days  = max( 1, (int) get_option( self::RETENTION_OPT, 365 ) );
		$table = self::get_table();

		return (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE created_at < %s",
			gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
		) );
	}

	public function handle_cleanup_now(): void {
		check_admin_referer( 'medialab_mail_log_cleanup_now' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung' );

		$deleted = $this->cleanup_old_entries();
		wp_redirect( add_query_arg( 'cleaned', $deleted, admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
		exit;
	}

	public function handle_clear_all(): void {
		check_admin_referer( 'medialab_mail_log_clear_all' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung' );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::get_table() );
		wp_redirect( add_query_arg( 'cleared', 1, admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
		exit;
	}

	// ─────────────────────────────────────────────────────────────
	// Zeitraum (analog zu MLT_GSC_API::get_active_range() im SEO Toolkit)
	// ─────────────────────────────────────────────────────────────

	public static function get_active_range(): array {
		if ( is_admin() && isset( $_GET['mla_start'], $_GET['mla_end'] ) ) {
			$start = sanitize_text_field( wp_unslash( $_GET['mla_start'] ) );
			$end   = sanitize_text_field( wp_unslash( $_GET['mla_end'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
				return compact( 'start', 'end' );
			}
		}

		if ( is_admin() && isset( $_GET['mla_range'] ) ) {
			$days = (int) $_GET['mla_range'];
			if ( in_array( $days, array( 7, 28, 90, 365 ), true ) ) {
				return array(
					'start' => gmdate( 'Y-m-d', strtotime( "-{$days} days" ) ),
					'end'   => gmdate( 'Y-m-d' ),
				);
			}
		}

		return array(
			'start' => gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
			'end'   => gmdate( 'Y-m-d' ),
		);
	}

	// ─────────────────────────────────────────────────────────────
	// Admin-Seite
	// ─────────────────────────────────────────────────────────────

	public function add_admin_page(): void {
		add_submenu_page(
			'agency-core',
			'E-Mail-Log',
			'E-Mail-Log',
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_GET['mla_view'] ) ) {
			$this->render_detail_view( (int) $_GET['mla_view'] );
			return;
		}

		$this->render_list_view();
	}

	private function build_where( array $filters ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], array( 'sent', 'failed' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $filters['status'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(to_email LIKE %s OR subject LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $filters['start'] ) && ! empty( $filters['end'] ) ) {
			$where[]  = 'created_at BETWEEN %s AND %s';
			$params[] = $filters['start'] . ' 00:00:00';
			$params[] = $filters['end'] . ' 23:59:59';
		}

		return array( implode( ' AND ', $where ), $params );
	}

	private function get_current_filters(): array {
		return array(
			'status' => isset( $_GET['mla_status'] ) ? sanitize_text_field( $_GET['mla_status'] ) : '',
			'search' => isset( $_GET['mla_s'] ) ? sanitize_text_field( wp_unslash( $_GET['mla_s'] ) ) : '',
		);
	}

	private function render_list_view(): void {
		global $wpdb;
		$table = self::get_table();

		$filters = $this->get_current_filters();
		$range   = self::get_active_range();

		list( $where_sql, $params ) = $this->build_where( array(
			'status' => $filters['status'],
			'search' => $filters['search'],
			'start'  => $range['start'],
			'end'    => $range['end'],
		) );

		$per_page = 30;
		$paged    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$offset   = ( $paged - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql    = "SELECT id, to_email, subject, status, mode, created_at FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$logs        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		list( $range_where, $range_params ) = $this->build_where( array( 'start' => $range['start'], 'end' => $range['end'] ) );
		$stats_sql = "SELECT status, COUNT(*) as cnt FROM {$table} WHERE {$range_where} GROUP BY status";
		$stats_raw = $range_params ? $wpdb->get_results( $wpdb->prepare( $stats_sql, $range_params ) ) : $wpdb->get_results( $stats_sql );
		$stats     = array( 'sent' => 0, 'failed' => 0, 'pending' => 0 );
		foreach ( $stats_raw as $row ) {
			if ( isset( $stats[ $row->status ] ) ) $stats[ $row->status ] = (int) $row->cnt;
		}
		$stats['total'] = array_sum( $stats );

		$base_url     = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		$shortcuts    = array( 7 => '7 Tage', 28 => '28 Tage', 90 => '90 Tage', 365 => '365 Tage' );
		$active_range = isset( $_GET['mla_range'] ) ? (int) $_GET['mla_range'] : null;
		$is_custom    = isset( $_GET['mla_start'] );
		$retention    = (int) get_option( self::RETENTION_OPT, 365 );

		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=medialab_mail_log_export_csv&' . http_build_query( $_GET ) ),
			'medialab_mail_log_export_csv'
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">E-Mail-Log</h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action">CSV exportieren</a>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['cleaned'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo (int) $_GET['cleaned']; ?> alte Einträge bereinigt.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Log vollständig geleert.</p></div>
			<?php endif; ?>

			<p class="description">
				Protokolliert alle über <code>wp_mail()</code> versendeten E-Mails inkl. Volltext.
				Aufbewahrung: <?php echo esc_html( $retention ); ?> Tage (automatische tägliche Bereinigung).
				Report-Einstellungen: <a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-core-mail-report' ) ); ?>">E-Mail-Report</a>
			</p>

			<div style="display:flex;gap:16px;margin:16px 0;">
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 20px;min-width:110px;">
					<div style="font-size:22px;font-weight:700;"><?php echo esc_html( $stats['total'] ); ?></div>
					<div style="color:#646970;font-size:12px;">Gesamt (Zeitraum)</div>
				</div>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 20px;min-width:110px;">
					<div style="font-size:22px;font-weight:700;color:#00a32a;"><?php echo esc_html( $stats['sent'] ); ?></div>
					<div style="color:#646970;font-size:12px;">Gesendet</div>
				</div>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 20px;min-width:110px;">
					<div style="font-size:22px;font-weight:700;color:#d63638;"><?php echo esc_html( $stats['failed'] ); ?></div>
					<div style="color:#646970;font-size:12px;">Fehlgeschlagen</div>
				</div>
			</div>

			<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">

				<?php foreach ( $shortcuts as $days => $label ) :
					$is_active = ( $active_range === $days && ! $is_custom );
					$url       = add_query_arg( 'mla_range', $days, remove_query_arg( array( 'mla_start', 'mla_end', 'paged' ), $base_url ) );
					if ( $filters['status'] ) $url = add_query_arg( 'mla_status', $filters['status'], $url );
					if ( $filters['search'] ) $url = add_query_arg( 'mla_s', $filters['search'], $url );
				?>
					<a href="<?php echo esc_url( $url ); ?>" class="button <?php echo $is_active ? 'button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>

				<span style="margin:0 4px;color:#c3c4c7;">|</span>
				<input type="date" name="mla_start" value="<?php echo esc_attr( $is_custom ? $range['start'] : '' ); ?>">
				<span>bis</span>
				<input type="date" name="mla_end" value="<?php echo esc_attr( $is_custom ? $range['end'] : '' ); ?>">

				<select name="mla_status">
					<option value="">Alle Status</option>
					<option value="sent" <?php selected( $filters['status'], 'sent' ); ?>>Gesendet</option>
					<option value="failed" <?php selected( $filters['status'], 'failed' ); ?>>Fehlgeschlagen</option>
				</select>

				<input type="search" name="mla_s" placeholder="Empfänger oder Betreff…" value="<?php echo esc_attr( $filters['search'] ); ?>">

				<button type="submit" class="button">Filtern</button>
				<?php if ( $filters['status'] || $filters['search'] || $is_custom || $active_range ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button-link">Zurücksetzen</a>
				<?php endif; ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th width="140">Datum</th>
						<th>Empfänger</th>
						<th>Betreff</th>
						<th width="110">Status</th>
						<th width="90">Modus</th>
						<th width="90">Details</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="6" style="text-align:center;padding:20px;color:#9ca3af;">Keine Einträge im gewählten Zeitraum.</td></tr>
					<?php else : foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $log->created_at ) ) ); ?></td>
							<td><?php echo esc_html( $log->to_email ); ?></td>
							<td><?php echo esc_html( $log->subject ); ?></td>
							<td><?php echo $this->status_badge( $log->status ); ?></td>
							<td><code><?php echo esc_html( $log->mode ); ?></code></td>
							<td><a href="<?php echo esc_url( add_query_arg( 'mla_view', $log->id, $base_url ) ); ?>">Ansehen</a></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>

			<?php if ( $total > $per_page ) : ?>
				<div class="tablenav bottom">
					<?php echo paginate_links( array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => (int) ceil( $total / $per_page ),
					) ); ?>
				</div>
			<?php endif; ?>

			<p style="margin-top:24px;display:flex;gap:8px;">
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=medialab_mail_log_cleanup_now' ), 'medialab_mail_log_cleanup_now' ) ); ?>" class="button">
					Jetzt bereinigen (&gt; <?php echo esc_html( $retention ); ?> Tage)
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=medialab_mail_log_clear_all' ), 'medialab_mail_log_clear_all' ) ); ?>"
					class="button"
					onclick="return confirm('Wirklich das komplette E-Mail-Log unwiderruflich löschen?');">
					Log komplett leeren
				</a>
			</p>
		</div>
		<?php
	}

	private function render_detail_view( int $id ): void {
		global $wpdb;
		$table    = self::get_table();
		$log      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		$back_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

		if ( ! $log ) {
			echo '<div class="wrap"><h1>E-Mail-Log</h1><p>Eintrag nicht gefunden.</p><p><a href="' . esc_url( $back_url ) . '" class="button">← Zurück</a></p></div>';
			return;
		}
		?>
		<div class="wrap">
			<h1>E-Mail-Log – Details</h1>
			<p><a href="<?php echo esc_url( $back_url ); ?>" class="button">← Zurück zur Liste</a></p>

			<table class="form-table" role="presentation">
				<tr><th>Datum</th><td><?php echo esc_html( wp_date( 'd.m.Y H:i:s', strtotime( $log->created_at ) ) ); ?></td></tr>
				<tr><th>Empfänger</th><td><?php echo esc_html( $log->to_email ); ?></td></tr>
				<tr><th>Betreff</th><td><?php echo esc_html( $log->subject ); ?></td></tr>
				<tr><th>Status</th><td><?php echo $this->status_badge( $log->status ); ?></td></tr>
				<tr><th>Versand-Modus</th><td><code><?php echo esc_html( $log->mode ); ?></code></td></tr>
				<?php if ( $log->attachments ) : ?>
				<tr><th>Anhänge</th><td><?php echo esc_html( $log->attachments ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $log->error_message ) : ?>
				<tr><th>Fehlermeldung</th><td style="color:#b32d2e;"><?php echo esc_html( $log->error_message ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $log->headers ) : ?>
				<tr><th>Headers</th><td><pre style="white-space:pre-wrap;margin:0;"><?php echo esc_html( $log->headers ); ?></pre></td></tr>
				<?php endif; ?>
			</table>

			<h2>Inhalt</h2>
			<iframe
				srcdoc="<?php echo esc_attr( $log->body ); ?>"
				sandbox=""
				style="width:100%;min-height:500px;border:1px solid #dcdcde;background:#fff;"
			></iframe>
			<p class="description">Der Inhalt wird in einem isolierten Frame angezeigt (<code>sandbox=""</code> – keine Skript-Ausführung, keine externen Requests).</p>
		</div>
		<?php
	}

	private function status_badge( string $status ): string {
		$map = array(
			'sent'    => array( '#00a32a', 'Gesendet' ),
			'failed'  => array( '#d63638', 'Fehlgeschlagen' ),
			'pending' => array( '#dba617', 'Ausstehend' ),
		);
		list( $color, $label ) = $map[ $status ] ?? array( '#787c82', $status );
		return sprintf(
			'<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:%1$s;color:#fff;font-size:11px;font-weight:600;">%2$s</span>',
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	// ─────────────────────────────────────────────────────────────
	// CSV-Export
	// ─────────────────────────────────────────────────────────────

	public function handle_export_csv(): void {
		check_admin_referer( 'medialab_mail_log_export_csv' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung' );

		global $wpdb;
		$table   = self::get_table();
		$filters = $this->get_current_filters();
		$range   = self::get_active_range();

		list( $where_sql, $params ) = $this->build_where( array(
			'status' => $filters['status'],
			'search' => $filters['search'],
			'start'  => $range['start'],
			'end'    => $range['end'],
		) );

		$sql  = "SELECT created_at, to_email, subject, status, mode, error_message FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mail-log-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM – Umlaute in Excel
		fputcsv( $out, array( 'Datum', 'Empfänger', 'Betreff', 'Status', 'Modus', 'Fehler' ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array( $row->created_at, $row->to_email, $row->subject, $row->status, $row->mode, $row->error_message ) );
		}
		fclose( $out );
		exit;
	}
}

MediaLab_Mail_Log::get_instance();
