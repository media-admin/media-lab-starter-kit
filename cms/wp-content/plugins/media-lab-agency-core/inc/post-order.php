<?php
/**
 * Drag & Drop Post Order
 *
 * Ermöglicht das Sortieren von Posts und CPTs per Drag & Drop
 * in der WP-Admin-Listenansicht. Welche CPTs sortierbar sind,
 * wird über Einstellungen → Post Order konfiguriert.
 *
 * Sortierung wird in wp_posts.menu_order gespeichert.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Post_Order {

	/**
	 * Option-Key für aktivierte CPTs
	 */
	const OPTION_KEY = 'medialab_sortable_post_types';

	/**
	 * Eingebaute Post Types, die niemals in der Auswahl erscheinen
	 */
	private $excluded_types = array(
		'attachment', 'revision', 'nav_menu_item',
		'custom_css', 'customize_changeset', 'oembed_cache',
		'user_request', 'wp_block', 'wp_template',
		'wp_template_part', 'wp_global_styles', 'wp_navigation',
		'wp_font_family', 'wp_font_face',
		// eigene Core-CPTs, die nie sortiert werden sollen
		'notification',
	);

	public function __construct() {
		// Admin
		add_action( 'admin_menu',            array( $this, 'register_settings_page' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices',         array( $this, 'maybe_show_notice' ) );

		// AJAX
		add_action( 'wp_ajax_medialab_update_post_order', array( $this, 'ajax_update_order' ) );

		// Query-Hooks
		add_action( 'pre_get_posts', array( $this, 'default_order_in_admin' ) );
		add_action( 'pre_get_posts', array( $this, 'default_order_in_frontend' ) );
	}

	// -------------------------------------------------------------------------
	// Hilfsmethoden
	// -------------------------------------------------------------------------

	/**
	 * Gibt die Liste der aktuell aktivierten, sortierbaren Post Types zurück.
	 * 'page' ist immer enthalten (Built-in Default aus alter Implementierung).
	 *
	 * @return array
	 */
	public function get_sortable_types(): array {
		$saved = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		// 'page' immer sortierbar (war vorheriger Standard)
		return array_unique( array_merge( array( 'page' ), $saved ) );
	}

	/**
	 * Gibt alle öffentlichen CPTs zurück, die in der Einstellungsseite
	 * angeboten werden (exkl. Blacklist und 'page', das fix ist).
	 *
	 * @return WP_Post_Type[]
	 */
	private function get_selectable_types(): array {
		$args = array(
			'public'   => true,
			'_builtin' => false, // nur Custom Post Types
		);

		$cpts = get_post_types( $args, 'objects' );

		// Blacklist filtern
		foreach ( $this->excluded_types as $slug ) {
			unset( $cpts[ $slug ] );
		}

		// 'post' separat anbieten (builtin, aber sinnvoll sortierbar)
		$post_type_obj = get_post_type_object( 'post' );
		if ( $post_type_obj ) {
			$cpts = array( 'post' => $post_type_obj ) + $cpts;
		}

		return $cpts;
	}

	// -------------------------------------------------------------------------
	// Einstellungsseite
	// -------------------------------------------------------------------------

	public function register_settings_page(): void {
		add_options_page(
			__( 'Post Order', 'media-lab-core' ),
			__( 'Post Order', 'media-lab-core' ),
			'manage_options',
			'medialab-post-order',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'medialab_post_order_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize: Nur gültige, registrierte Post Type Slugs zulassen.
	 */
	public function sanitize_post_types( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$all_types = get_post_types( array(), 'names' );
		$sanitized = array();

		foreach ( $input as $slug ) {
			$slug = sanitize_key( $slug );
			if ( in_array( $slug, $all_types, true ) ) {
				$sanitized[] = $slug;
			}
		}

		return array_unique( $sanitized );
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$selectable    = $this->get_selectable_types();
		$active_types  = $this->get_sortable_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Post Order – Sortierbare Post Types', 'media-lab-core' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Wähle die Post Types aus, für die Drag & Drop Sortierung in der Admin-Listenansicht aktiviert werden soll. Die Reihenfolge wird über das Feld menu_order gespeichert und automatisch im Frontend angewendet.', 'media-lab-core' ); ?>
			</p>
			<hr>
			<form method="post" action="options.php">
				<?php settings_fields( 'medialab_post_order_group' ); ?>

				<table class="wp-list-table widefat striped medialab-post-order-table" style="max-width:700px;">
					<thead>
						<tr>
							<th style="width:40px;"></th>
							<th><?php esc_html_e( 'Post Type', 'media-lab-core' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'media-lab-core' ); ?></th>
							<th><?php esc_html_e( 'Typ', 'media-lab-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $selectable ) ) : ?>
							<tr>
								<td colspan="4">
									<?php esc_html_e( 'Keine sortierbaren Post Types gefunden.', 'media-lab-core' ); ?>
								</td>
							</tr>
						<?php else : ?>

							<?php
							// 'page' als fixen, deaktivierten Eintrag ganz oben zeigen
							$page_obj = get_post_type_object( 'page' );
							if ( $page_obj ) :
							?>
							<tr style="opacity:.6;">
								<td>
									<input type="checkbox" disabled checked
										title="<?php esc_attr_e( 'Seiten sind immer sortierbar', 'media-lab-core' ); ?>">
								</td>
								<td>
									<strong><?php echo esc_html( $page_obj->labels->name ); ?></strong>
									<span class="description" style="font-size:11px;display:block;">
										<?php esc_html_e( 'Immer aktiviert', 'media-lab-core' ); ?>
									</span>
								</td>
								<td><code>page</code></td>
								<td><?php esc_html_e( 'Built-in', 'media-lab-core' ); ?></td>
							</tr>
							<?php endif; ?>

							<?php foreach ( $selectable as $slug => $obj ) :
								$is_checked = in_array( $slug, $active_types, true );
								$is_builtin = $obj->_builtin ?? false;
							?>
							<tr>
								<td>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[]"
										value="<?php echo esc_attr( $slug ); ?>"
										id="mlpo_<?php echo esc_attr( $slug ); ?>"
										<?php checked( $is_checked ); ?>
									>
								</td>
								<td>
									<label for="mlpo_<?php echo esc_attr( $slug ); ?>">
										<strong><?php echo esc_html( $obj->labels->name ); ?></strong>
									</label>
								</td>
								<td><code><?php echo esc_html( $slug ); ?></code></td>
								<td>
									<?php echo $is_builtin
										? esc_html__( 'Built-in', 'media-lab-core' )
										: esc_html__( 'Custom', 'media-lab-core' );
									?>
								</td>
							</tr>
							<?php endforeach; ?>

						<?php endif; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Einstellungen speichern', 'media-lab-core' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Hinweise', 'media-lab-core' ); ?></h2>
			<ul style="list-style:disc;padding-left:1.5em;max-width:700px;">
				<li><?php esc_html_e( 'Die Sortierung wird in der Datenbankspalte menu_order gespeichert.', 'media-lab-core' ); ?></li>
				<li><?php esc_html_e( 'Für aktivierte Post Types wird im Frontend automatisch orderby=menu_order gesetzt (nur Main Query, kein explizites orderby).', 'media-lab-core' ); ?></li>
				<li><?php esc_html_e( 'Die Drag & Drop Handles erscheinen in der Admin-Listenansicht des jeweiligen Post Types.', 'media-lab-core' ); ?></li>
				<li><?php esc_html_e( 'Seiten (page) sind immer sortierbar und erscheinen daher nicht in der Auswahl.', 'media-lab-core' ); ?></li>
			</ul>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Admin Notice nach Speichern
	// -------------------------------------------------------------------------

	public function maybe_show_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'settings_page_medialab-post-order' ) {
			return;
		}

		// WP zeigt nach options.php Redirect settings-updated in der URL
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Post Order Einstellungen gespeichert.', 'media-lab-core' )
				. '</p></div>';
		}
	}

	// -------------------------------------------------------------------------
	// Scripts & Styles
	// -------------------------------------------------------------------------

	public function enqueue_scripts( string $hook ): void {
		if ( $hook !== 'edit.php' ) return;

		$post_type     = sanitize_key( $_GET['post_type'] ?? 'post' );
		$active_types  = $this->get_sortable_types();

		if ( ! in_array( $post_type, $active_types, true ) ) return;

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'medialab-post-order',
			MEDIALAB_CORE_URL . 'assets/js/post-order.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			MEDIALAB_CORE_VERSION,
			true
		);
		wp_localize_script( 'medialab-post-order', 'medialabPostOrder', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'medialab_post_order' ),
			'postType' => $post_type,
			'i18n'     => array(
				'saving' => __( 'Speichern…', 'media-lab-core' ),
				'saved'  => __( 'Reihenfolge gespeichert', 'media-lab-core' ),
				'error'  => __( 'Fehler beim Speichern', 'media-lab-core' ),
			),
		) );

		wp_enqueue_style(
			'medialab-post-order',
			MEDIALAB_CORE_URL . 'assets/css/post-order.css',
			array(),
			MEDIALAB_CORE_VERSION
		);
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function ajax_update_order(): void {
		check_ajax_referer( 'medialab_post_order', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Keine Berechtigung', 'media-lab-core' ), 403 );
		}

		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();

		if ( empty( $order ) ) {
			wp_send_json_error( __( 'Keine Daten empfangen', 'media-lab-core' ) );
		}

		$updated = 0;
		foreach ( $order as $position => $post_id ) {
			if ( $post_id <= 0 ) continue;

			$result = wp_update_post( array(
				'ID'         => $post_id,
				'menu_order' => $position,
			) );

			if ( $result && ! is_wp_error( $result ) ) {
				$updated++;
			}
		}

		wp_send_json_success( array(
			'updated' => $updated,
			'message' => sprintf(
				/* translators: %d: Anzahl aktualisierter Posts */
				__( '%d Einträge aktualisiert', 'media-lab-core' ),
				$updated
			),
		) );
	}

	// -------------------------------------------------------------------------
	// pre_get_posts Hooks
	// -------------------------------------------------------------------------

	/**
	 * Admin-Listenansichten nach menu_order sortieren.
	 */
	public function default_order_in_admin( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) return;

		$post_type    = $query->get( 'post_type' ) ?: ( sanitize_key( $_GET['post_type'] ?? 'post' ) );
		$active_types = $this->get_sortable_types();

		if ( ! in_array( $post_type, $active_types, true ) ) return;

		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * Frontend Main Query für aktivierte CPTs auf menu_order setzen.
	 * 'post' und 'page' werden bewusst nicht automatisch umgestellt
	 * (Standard-Chronologie bleibt erhalten).
	 */
	public function default_order_in_frontend( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) return;

		$post_type    = $query->get( 'post_type' );
		$active_types = $this->get_sortable_types();

		// Nur CPTs, nicht page/post
		$cpt_only = array_diff( $active_types, array( 'post', 'page' ) );

		if ( in_array( $post_type, $cpt_only, true ) && ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}
}

new MediaLab_Post_Order();
