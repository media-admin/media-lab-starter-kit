<?php
/**
 * Media Lab Agency Core – Hardening-Modul
 *
 * Verhindert, dass ein kompromittierter Administrator-Account (oder eine
 * gekaperte Admin-Session) über das WordPress-Backend Code auf den Server
 * bringt. Das ist der Angriffsweg der Vorfälle bei ib-mosbacher.at und
 * at.media-lab.dev (Webshell als "Plugin" per Plugins → Installieren →
 * Hochladen, POST auf wp-admin/update.php?action=upload-plugin).
 *
 * Was gesperrt wird (für ALLE Benutzer, auch Administratoren):
 *  - Plugins/Themes installieren und hochladen
 *      (install_plugins, upload_plugins, install_themes, upload_themes)
 *  - Plugin-/Theme-Editor
 *      (edit_plugins, edit_themes, edit_files)
 *  - Deaktivieren/Löschen von media-lab-agency-core selbst, damit der Schutz
 *      nicht erst ausgeschaltet werden kann ("Plugin deaktivieren → Upload").
 *
 * Was bewusst NICHT gesperrt wird:
 *  - Updates von Core, Plugins, Themes (update_core, update_plugins,
 *    update_themes) → automatische Updates und MainWP-Updates laufen weiter.
 *  - Aktivieren/Deaktivieren anderer, bereits installierter Plugins.
 *
 * Neue Plugins und Themes kommen ausschließlich per SFTP/Git auf den Server.
 *
 * Ausnahmen (nur über Code/Serverkonfiguration, NICHT über die Datenbank –
 * sonst könnte ein Angreifer mit Admin-Rechten den Schutz selbst abschalten):
 *  1. wp-config.php:  define( 'MLA_ALLOW_FILE_MODS', true );
 *     → temporär setzen, Plugin im Backend installieren, Zeile wieder entfernen.
 *     Der wöchentliche Security-Scan meldet die gesetzte Konstante als Fehler,
 *     damit sie nicht vergessen wird.
 *  2. wp-config.php:  define( 'WP_ENVIRONMENT_TYPE', 'local' );
 *     → für lokale Entwicklung (Valet). 'development' wirkt genauso.
 *  3. Filter 'mla_hardening_active' (nur aus Code, z. B. in einem Projekt-Plugin).
 *
 * Blockierte Versuche (Installation/Upload/Editor-Speichern) werden im
 * Activity Log protokolliert und per E-Mail gemeldet (max. 1 Mail pro
 * 15 Minuten). Empfänger: Option mla_security_notify_email, sonst admin_email.
 * Ein blockierter Upload bedeutet fast immer: Jemand mit Admin-Zugang
 * versucht gerade, Code einzuschleusen → Passwörter ändern, Sessions beenden.
 *
 * Grenzen: Der Schutz greift gegen das Standard-Backend und die REST-API.
 * Plugins, die selbst Code ausführen oder Dateien entpacken (z. B. Migrations-
 * oder Snippet-Plugins wie All-in-One WP Migration, Code Snippets), bleiben ein
 * Risiko und gehören auf Live-Seiten nicht installiert bzw. deaktiviert.
 *
 * @since   1.41.0
 * @package Media_Lab_Agency_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MLA_HARDENING_LOADED' ) ) {
	return;
}
define( 'MLA_HARDENING_LOADED', true );

final class MLA_Hardening {

	/** Transient für das Mail-Limit. */
	const ALERT_TRANSIENT = 'mla_hardening_alert_sent';

	/** Mindestabstand zwischen zwei Alarm-Mails in Sekunden. */
	const ALERT_INTERVAL = 900;

	/**
	 * Capabilities, die nie erteilt werden. upload_* wird von WordPress intern
	 * auf install_* abgebildet (wp-includes/capabilities.php), wir sperren
	 * trotzdem beide, damit die Sperre unabhängig von dieser Abbildung bleibt.
	 */
	const BLOCKED_CAPS = array(
		'install_plugins',
		'upload_plugins',
		'install_themes',
		'upload_themes',
		'edit_plugins',
		'edit_themes',
		'edit_files',
	);

	/** Meta-Capabilities, die für das eigene Plugin gesperrt werden. */
	const PROTECTED_PLUGIN_CAPS = array( 'deactivate_plugin', 'delete_plugin' );

	/** Aktionen auf update.php, die Code installieren. */
	const INSTALL_ACTIONS = array(
		'install-plugin',
		'upload-plugin',
		'upload-plugin-cancel-overwrite',
		'install-theme',
		'upload-theme',
	);

	/** AJAX-Aktionen, die Code installieren oder Dateien schreiben. */
	const AJAX_ACTIONS = array(
		'install-plugin',
		'install-theme',
		'edit-theme-plugin-file',
	);

	/** Seiten, die bei aktiver Sperre eine verständliche Meldung zeigen. */
	const BLOCKED_PAGES = array(
		'plugin-install.php',
		'theme-install.php',
		'plugin-editor.php',
		'theme-editor.php',
	);

	private static $booted = false;

	/**
	 * Registriert die Filter, sofern die Sperre aktiv ist.
	 */
	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		if ( ! self::is_active() ) {
			return;
		}

		add_filter( 'map_meta_cap', array( __CLASS__, 'filter_meta_caps' ), 99, 4 );
		add_filter( 'pre_update_option_active_plugins', array( __CLASS__, 'keep_core_active' ), 99, 2 );
		add_filter( 'plugin_action_links_' . MEDIALAB_CORE_BASENAME, array( __CLASS__, 'strip_deactivate_link' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'guard_requests' ), 1 );
	}

	/* ---------------------------------------------------------------
	 * Status (wird auch vom Security-Scanner genutzt)
	 * ------------------------------------------------------------- */

	/**
	 * Ist die Sperre aktiv?
	 */
	public static function is_active(): bool {
		return 'active' === self::status()['state'];
	}

	/**
	 * @return array{state:string,reason:string}
	 *         state: active | constant | environment | filter
	 */
	public static function status(): array {
		if ( defined( 'MLA_ALLOW_FILE_MODS' ) && MLA_ALLOW_FILE_MODS ) {
			return array(
				'state'  => 'constant',
				'reason' => 'MLA_ALLOW_FILE_MODS ist in wp-config.php gesetzt.',
			);
		}

		$env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		if ( in_array( $env, array( 'local', 'development' ), true ) ) {
			return array(
				'state'  => 'environment',
				'reason' => sprintf( 'WP_ENVIRONMENT_TYPE ist "%s".', $env ),
			);
		}

		if ( ! apply_filters( 'mla_hardening_active', true ) ) {
			return array(
				'state'  => 'filter',
				'reason' => 'Der Filter mla_hardening_active schaltet die Sperre ab.',
			);
		}

		return array(
			'state'  => 'active',
			'reason' => '',
		);
	}

	/* ---------------------------------------------------------------
	 * Capability-Sperre
	 * ------------------------------------------------------------- */

	/**
	 * map_meta_cap-Filter. 'do_not_allow' wird auch bei Multisite-Super-Admins
	 * respektiert, anders als ein Entzug über user_has_cap.
	 *
	 * @param string[] $caps    Primitive Capabilities, auf die abgebildet wurde.
	 * @param string   $cap     Angefragte Capability.
	 * @param int      $user_id Benutzer-ID.
	 * @param array    $args    Zusatzargumente (bei *_plugin: Plugin-Basename).
	 * @return string[]
	 */
	public static function filter_meta_caps( $caps, $cap, $user_id = 0, $args = array() ) {
		if ( in_array( $cap, self::BLOCKED_CAPS, true ) ) {
			return array( 'do_not_allow' );
		}

		if (
			in_array( $cap, self::PROTECTED_PLUGIN_CAPS, true )
			&& isset( $args[0] )
			&& MEDIALAB_CORE_BASENAME === $args[0]
		) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/**
	 * Hält media-lab-agency-core in der Liste der aktiven Plugins, egal auf
	 * welchem Weg (Plugins-Seite, Bulk-Aktion, REST-API) deaktiviert wird.
	 * Greift nur, wenn das Plugin vorher aktiv war.
	 *
	 * @param mixed $value     Neuer Wert von active_plugins.
	 * @param mixed $old_value Bisheriger Wert.
	 * @return mixed
	 */
	public static function keep_core_active( $value, $old_value ) {
		if ( ! is_array( $value ) || ! is_array( $old_value ) ) {
			return $value;
		}

		if (
			in_array( MEDIALAB_CORE_BASENAME, $old_value, true )
			&& ! in_array( MEDIALAB_CORE_BASENAME, $value, true )
		) {
			$value[] = MEDIALAB_CORE_BASENAME;
			sort( $value );
		}

		return $value;
	}

	/**
	 * Entfernt den "Deaktivieren"-Link des eigenen Plugins in der Plugin-Liste.
	 *
	 * @param array $links
	 * @return array
	 */
	public static function strip_deactivate_link( $links ) {
		if ( is_array( $links ) ) {
			unset( $links['deactivate'] );
		}
		return $links;
	}

	/* ---------------------------------------------------------------
	 * Request-Überwachung
	 * ------------------------------------------------------------- */

	/**
	 * Läuft auf admin_init (inkl. admin-ajax.php), bevor WordPress selbst
	 * die Berechtigung prüft: meldet Versuche und ersetzt die generische
	 * WordPress-Fehlermeldung auf den Installations-/Editor-Seiten.
	 */
	public static function guard_requests(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		global $pagenow;

		$action = isset( $_REQUEST['action'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		// 1) Echte Installations-/Schreibversuche → melden. WordPress lehnt sie
		//    anschließend selbst per Capability-Prüfung ab.
		$is_install = 'update.php' === $pagenow && in_array( $action, self::INSTALL_ACTIONS, true );
		$is_ajax    = wp_doing_ajax() && in_array( $action, self::AJAX_ACTIONS, true );

		if ( $is_install || $is_ajax ) {
			self::report_attempt( $action );
			return;
		}

		// 2) Reines Aufrufen der Installations-/Editor-Seiten → verständliche
		//    Meldung statt "Sorry, you are not allowed…", ohne Alarm.
		if ( in_array( $pagenow, self::BLOCKED_PAGES, true ) ) {
			wp_die(
				'<p>' . esc_html__( 'Die Installation und Bearbeitung von Plugins und Themes im Backend ist aus Sicherheitsgründen gesperrt. Plugins und Themes werden per SFTP bzw. Git bereitgestellt.', 'media-lab-core' ) . '</p>',
				esc_html__( 'Gesperrt', 'media-lab-core' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
	}

	/**
	 * Protokolliert einen blockierten Versuch und benachrichtigt per Mail.
	 */
	private static function report_attempt( string $action ): void {
		global $pagenow;

		$user = wp_get_current_user();
		$ip   = self::remote_ip();
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( preg_replace( '/[^\x20-\x7E]/', '', (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 200 )
			: '';

		$details = sprintf(
			'Aktion: %s | Seite: %s | IP: %s | User-Agent: %s',
			$action,
			$pagenow,
			$ip,
			$ua
		);

		// Activity Log (inc/activity-log.php).
		do_action( 'medialab_log_event', 'blocked_file_mod', 'security', (int) $user->ID, (string) $user->user_login, $details );

		// Mail mit Mindestabstand, damit ein Angreifer keinen Mail-Flood auslöst.
		if ( get_transient( self::ALERT_TRANSIENT ) ) {
			return;
		}
		set_transient( self::ALERT_TRANSIENT, 1, self::ALERT_INTERVAL );

		$to = get_option( 'mla_security_notify_email' ) ?: get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf( '[Sicherheit] Blockierter Plugin-/Theme-Upload auf %s', home_url() );

		$body  = "Auf dieser Website wurde gerade versucht, Code über das WordPress-Backend zu installieren.\n";
		$body .= "Der Versuch wurde durch das Hardening-Modul blockiert.\n\n";
		$body .= sprintf( "Benutzer:    %s (ID %d)\n", $user->user_login, $user->ID );
		$body .= sprintf( "Aktion:      %s\n", $action );
		$body .= sprintf( "IP-Adresse:  %s\n", $ip );
		$body .= sprintf( "User-Agent:  %s\n", $ua );
		$body .= sprintf( "Zeitpunkt:   %s\n\n", wp_date( 'd.m.Y H:i:s' ) );
		$body .= "Wenn das nicht ihr selbst wart: Der Account ist vermutlich kompromittiert.\n";
		$body .= "Sofort Passwort ändern, alle Sitzungen beenden und die Benutzerliste auf unbekannte Administratoren prüfen.\n\n";
		$body .= 'Activity Log: ' . admin_url( 'admin.php?page=activity-log' ) . "\n";
		$body .= 'Security Scan: ' . admin_url( 'admin.php?page=mla-security-scan' ) . "\n";

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Client-IP aus REMOTE_ADDR. X-Forwarded-For wird bewusst nicht
	 * ausgewertet, weil der Header vom Client frei gesetzt werden kann.
	 */
	private static function remote_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unbekannt';
	}
}
