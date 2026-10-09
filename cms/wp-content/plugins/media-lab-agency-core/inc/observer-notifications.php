<?php
/**
 * Beobachter-Rolle & zusätzliche Benachrichtigungsempfänger
 *
 * Ergänzt die einzelne WordPress-`admin_email` um beliebig viele zusätzliche
 * Empfänger (Bcc) für ausgewählte System-Mails und legt eine Rolle
 * "Beobachter" mit reinen Leserechten an.
 *
 * Bewusst KEIN generischer wp_mail-Filter: Würde man jede Mail an die
 * Admin-Adresse duplizieren, bekämen Beobachter auch Passwort-Reset-Links,
 * sobald die Admin-Adresse zugleich die E-Mail eines Benutzers ist.
 * Stattdessen werden nur gezielt freigegebene Mail-Typen erweitert.
 *
 * Einstellungen: Agency Core → Benachrichtigungen / Beobachter
 * Option:        medialab_observer_notifications (Array: emails, types)
 *
 * @package Agency_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaLab_Observer_Notifications {

	const OPTION = 'medialab_observer_notifications';
	const GROUP  = 'medialab_observer_group';
	const PAGE   = 'agency-core-observer';
	const PARENT = 'agency-core';
	const ROLE   = 'medialab_observer';

	/**
	 * Mail-Typen, die erweitert werden können.
	 * Key => [ Label, Beschreibung, Standard aktiv ]
	 */
	private static function types(): array {
		return [
			'updates'  => [
				'Automatische Updates',
				'Ergebnis-Mails zu WordPress-, Plugin- und Theme-Updates.',
				1,
			],
			'fatal'    => [
				'Fatal-Error-Benachrichtigung',
				'Mail bei kritischen PHP-Fehlern. Enthält den Link zum Wiederherstellungsmodus (Login ist weiterhin nötig) – nur für vertrauenswürdige Empfänger aktivieren.',
				0,
			],
			'comments' => [
				'Kommentare zur Freigabe',
				'Hinweis, wenn ein Kommentar auf Moderation wartet.',
				0,
			],
			'users'    => [
				'Neue Benutzerregistrierungen',
				'Hinweis an den Admin, wenn sich ein neuer Benutzer registriert.',
				0,
			],
		];
	}

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'ensure_role' ] );
		// Priorität 100: ACF registriert das Top-Level-Menü "Agency Core" auf admin_menu 99.
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ], 100 );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

		add_filter( 'auto_core_update_email', [ __CLASS__, 'filter_updates' ] );
		add_filter( 'auto_plugin_theme_update_email', [ __CLASS__, 'filter_updates' ] );
		add_filter( 'recovery_mode_email', [ __CLASS__, 'filter_fatal' ] );
		add_filter( 'wp_new_user_notification_email_admin', [ __CLASS__, 'filter_users' ] );
		add_filter( 'comment_moderation_recipients', [ __CLASS__, 'filter_comments' ] );
	}

	// ─────────────────────────────────────────────────────────────
	// ROLLE
	// ─────────────────────────────────────────────────────────────

	/**
	 * Legt die Rolle "Beobachter" an, falls sie fehlt (idempotent, ein
	 * Rollen-Lookup pro Request, kein Activation-Hook nötig).
	 */
	public static function ensure_role(): void {
		if ( get_role( self::ROLE ) ) {
			return;
		}

		add_role(
			self::ROLE,
			'Beobachter',
			[
				'read'                   => true,
				'view_site_health_checks' => true,
			]
		);
	}

	// ─────────────────────────────────────────────────────────────
	// EINSTELLUNGEN
	// ─────────────────────────────────────────────────────────────

	public static function register_settings(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				'default'           => [],
			]
		);
	}

	public static function add_settings_page(): void {
		add_submenu_page(
			self::PARENT,
			'Benachrichtigungen / Beobachter',
			'Benachrichtigungen / Beobachter',
			'manage_options',
			self::PAGE,
			[ __CLASS__, 'render_page' ],
			13 // nach "Heartbeat Monitoring" (ACF-Unterseiten: 1–12)
		);
	}

	/**
	 * @param mixed $input Rohdaten aus dem Formular.
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : [];

		// E-Mail-Adressen: Zeilenumbruch, Komma, Semikolon oder Leerzeichen als Trenner.
		$raw     = isset( $input['emails'] ) ? (string) $input['emails'] : '';
		$parts   = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		$emails  = [];
		$invalid = [];

		foreach ( $parts as $part ) {
			$mail = sanitize_email( $part );
			if ( $mail && is_email( $mail ) ) {
				$emails[] = strtolower( $mail );
			} else {
				$invalid[] = $part;
			}
		}

		if ( $invalid ) {
			add_settings_error(
				self::OPTION,
				'medialab_observer_invalid',
				'Ungültige Adressen wurden ignoriert: ' . esc_html( implode( ', ', $invalid ) ),
				'warning'
			);
		}

		$types = [];
		foreach ( array_keys( self::types() ) as $key ) {
			$types[ $key ] = ! empty( $input['types'][ $key ] ) ? 1 : 0;
		}

		return [
			'emails' => array_values( array_unique( $emails ) ),
			'types'  => $types,
		];
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$emails = implode( "\n", self::get_emails() );
		?>
		<div class="wrap">
			<h1>Benachrichtigungen / Beobachter</h1>
			<?php settings_errors(); // Außerhalb von "Einstellungen" zeigt WordPress die Meldungen nicht automatisch. ?>
			<p>
				WordPress kennt nur eine Admin-E-Mail
				(<code><?php echo esc_html( get_option( 'admin_email' ) ); ?></code>).
				Die Adressen hier erhalten ausgewählte System-Mails zusätzlich als Bcc.
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="medialab-observer-emails">E-Mail-Adressen</label>
						</th>
						<td>
							<textarea
								id="medialab-observer-emails"
								name="<?php echo esc_attr( self::OPTION ); ?>[emails]"
								rows="5"
								class="large-text code"
								placeholder="kunde@beispiel.at"
							><?php echo esc_textarea( $emails ); ?></textarea>
							<p class="description">Eine Adresse pro Zeile (Komma oder Semikolon funktionieren ebenfalls).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Mail-Typen</th>
						<td>
							<fieldset>
								<?php foreach ( self::types() as $key => $type ) : ?>
									<label style="display:block;margin-bottom:.6em;">
										<input
											type="checkbox"
											name="<?php echo esc_attr( self::OPTION ); ?>[types][<?php echo esc_attr( $key ); ?>]"
											value="1"
											<?php checked( self::enabled( $key ) ); ?>
										>
										<strong><?php echo esc_html( $type[0] ); ?></strong><br>
										<span class="description"><?php echo esc_html( $type[1] ); ?></span>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────
	// HELFER
	// ─────────────────────────────────────────────────────────────

	/**
	 * Alle gespeicherten Zusatzadressen (validiert).
	 */
	private static function get_emails(): array {
		$opt = get_option( self::OPTION, [] );
		if ( ! is_array( $opt ) || empty( $opt['emails'] ) || ! is_array( $opt['emails'] ) ) {
			return [];
		}
		return array_values( array_filter( $opt['emails'], 'is_email' ) );
	}

	/**
	 * Empfänger ohne die reguläre Admin-Adresse (kein Doppelversand).
	 */
	private static function recipients(): array {
		$admin = strtolower( (string) get_option( 'admin_email' ) );

		return array_values(
			array_filter(
				self::get_emails(),
				static function ( $mail ) use ( $admin ) {
					return strtolower( $mail ) !== $admin;
				}
			)
		);
	}

	/**
	 * Ist ein Mail-Typ aktiv? Ohne gespeicherte Option gelten die Standardwerte.
	 */
	private static function enabled( string $type ): bool {
		$opt = get_option( self::OPTION, null );

		if ( is_array( $opt ) && isset( $opt['types'] ) && is_array( $opt['types'] ) ) {
			return ! empty( $opt['types'][ $type ] );
		}

		$types = self::types();
		return ! empty( $types[ $type ][2] );
	}

	/**
	 * Hängt die Zusatzempfänger als Bcc-Header an ein Mail-Array an.
	 *
	 * @param mixed $email Array mit to/subject/body|message/headers.
	 * @return mixed
	 */
	private static function add_bcc( $email, string $type ) {
		if ( ! is_array( $email ) || ! self::enabled( $type ) ) {
			return $email;
		}

		$extra = self::recipients();
		if ( ! $extra ) {
			return $email;
		}

		$headers = $email['headers'] ?? [];
		if ( ! is_array( $headers ) ) {
			$headers = preg_split( '/\r\n|\r|\n/', (string) $headers, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		}

		$headers[]        = 'Bcc: ' . implode( ', ', $extra );
		$email['headers'] = $headers;

		return $email;
	}

	// ─────────────────────────────────────────────────────────────
	// FILTER-CALLBACKS
	// ─────────────────────────────────────────────────────────────

	public static function filter_updates( $email ) {
		return self::add_bcc( $email, 'updates' );
	}

	public static function filter_fatal( $email ) {
		return self::add_bcc( $email, 'fatal' );
	}

	public static function filter_users( $email ) {
		return self::add_bcc( $email, 'users' );
	}

	/**
	 * Kommentar-Moderation arbeitet mit einer Empfängerliste statt Headern.
	 *
	 * @param mixed $emails
	 * @return mixed
	 */
	public static function filter_comments( $emails ) {
		if ( ! self::enabled( 'comments' ) ) {
			return $emails;
		}

		$extra = self::recipients();
		if ( ! $extra ) {
			return $emails;
		}

		return array_values( array_unique( array_merge( (array) $emails, $extra ) ) );
	}
}

MediaLab_Observer_Notifications::init();
