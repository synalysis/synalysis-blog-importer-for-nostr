<?php
/**
 * Admin settings and manual sync.
 *
 * @package SynalysisBlogImporterForNostr
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Synalysis_Blog_Importer_Settings {

	public const OPTION_GROUP = 'synalysis_blog_importer';
	public const PAGE_SLUG   = 'synalysis-blog-importer-for-nostr';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_synalysis_blog_importer_sync_now', array( $this, 'handle_sync_now' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	public function cron_schedules( array $schedules ): array {
		$schedules['synalysis_blog_importer_quarterhourly'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'synalysis-blog-importer-for-nostr' ),
		);
		return $schedules;
	}

	public function add_menu(): void {
		add_options_page(
			__( 'Synalysis Blog Importer', 'synalysis-blog-importer-for-nostr' ),
			__( 'Synalysis Blog Importer', 'synalysis-blog-importer-for-nostr' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			'synalysis_blog_importer_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$current = synalysis_blog_importer_get_settings();

		if ( isset( $input['authors'] ) ) {
			$current['authors'] = sanitize_textarea_field( (string) $input['authors'] );
		}
		if ( isset( $input['relays'] ) ) {
			$current['relays'] = sanitize_textarea_field( (string) $input['relays'] );
		}
		if ( isset( $input['archive_slug'] ) ) {
			$slug = sanitize_title( (string) $input['archive_slug'] );
			$current['archive_slug'] = $slug !== '' ? $slug : 'blog';
		}
		if ( isset( $input['archive_list_title'] ) ) {
			$t = sanitize_text_field( (string) $input['archive_list_title'] );
			if ( function_exists( 'mb_strlen' ) && mb_strlen( $t ) > 200 ) {
				$t = mb_substr( $t, 0, 200 );
			} elseif ( strlen( $t ) > 200 ) {
				$t = substr( $t, 0, 200 );
			}
			$current['archive_list_title'] = $t;
		}
		if ( isset( $input['single_url_mode'] ) ) {
			$mode = (string) $input['single_url_mode'];
			$current['single_url_mode'] = in_array( $mode, array( 'prefixed', 'root' ), true ) ? $mode : 'prefixed';
		}
		if ( array_key_exists( 'show_article_tags', $input ) ) {
			$current['show_article_tags'] = (string) $input['show_article_tags'] === '1';
		}
		if ( array_key_exists( 'nip05_enabled', $input ) ) {
			$current['nip05_enabled'] = (string) $input['nip05_enabled'] === '1';
		}
		if ( isset( $input['sync_interval'] ) ) {
			$iv = (string) $input['sync_interval'];
			$current['sync_interval'] = in_array( $iv, array( 'synalysis_blog_importer_quarterhourly', 'hourly', 'twicedaily', 'daily' ), true )
				? $iv
				: 'hourly';
		}
		if ( isset( $input['request_timeout'] ) ) {
			$current['request_timeout'] = max( 5, min( 120, (int) $input['request_timeout'] ) );
		}
		if ( isset( $input['event_limit'] ) ) {
			$current['event_limit'] = max( 1, min( 2000, (int) $input['event_limit'] ) );
		}

		$sync = new Synalysis_Blog_Importer_Sync();
		$nip  = $sync->build_nip05_identity_map( $current );
		$current['nip05_identity_map'] = $nip['map'];
		unset( $current['nip05_mappings'] );

		if ( $nip['missing_profile_count'] > 0 ) {
			set_transient(
				'synalysis_blog_importer_nip05_notice',
				sprintf(
					/* translators: %d: number of authors without a kind 0 profile on relays */
					__( 'NIP-05: no kind 0 profile was found on your relays for %d author(s); fallback local names were used. Check relays and author npubs.', 'synalysis-blog-importer-for-nostr' ),
					$nip['missing_profile_count']
				),
				60
			);
		} else {
			delete_transient( 'synalysis_blog_importer_nip05_notice' );
		}

		synalysis_blog_importer_schedule_cron();
		Synalysis_Blog_Importer_CPT::flush_rewrite_rules();

		set_transient( 'synalysis_blog_importer_settings_saved', '1', 30 );

		return $current;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s = synalysis_blog_importer_get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( __( 'Synalysis Blog Importer', 'synalysis-blog-importer-for-nostr' ) ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="synalysis_blog_importer_authors"><?php esc_html_e( 'Authors (npub or hex pubkey, one per line)', 'synalysis-blog-importer-for-nostr' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="4" id="synalysis_blog_importer_authors" name="synalysis_blog_importer_settings[authors]"><?php echo esc_textarea( (string) $s['authors'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="synalysis_blog_importer_relays"><?php esc_html_e( 'Relays (wss://, one per line)', 'synalysis-blog-importer-for-nostr' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="5" id="synalysis_blog_importer_relays" name="synalysis_blog_importer_settings[relays]"><?php echo esc_textarea( (string) $s['relays'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'NIP-05 verification', 'synalysis-blog-importer-for-nostr' ); ?></th>
						<td>
							<?php
							$idmap = isset( $s['nip05_identity_map'] ) && is_array( $s['nip05_identity_map'] ) ? $s['nip05_identity_map'] : array();
							$nip05_sample_name = 'example';
							$first_author_hexes = synalysis_blog_importer_get_author_pubkey_hexes();
							if ( $first_author_hexes !== array() ) {
								$want = $first_author_hexes[0];
								foreach ( $idmap as $lname => $phex ) {
									if ( ! is_string( $lname ) || ! is_string( $phex ) ) {
										continue;
									}
									if ( strtolower( $phex ) === $want ) {
										$nip05_sample_name = $lname;
										break;
									}
								}
							}
							$nip05_sample_url = add_query_arg( 'name', $nip05_sample_name, home_url( '/.well-known/nostr.json' ) );
							?>
							<input type="hidden" name="synalysis_blog_importer_settings[nip05_enabled]" value="0" />
							<label for="synalysis_blog_importer_nip05_enabled">
								<input type="checkbox" name="synalysis_blog_importer_settings[nip05_enabled]" id="synalysis_blog_importer_nip05_enabled" value="1" <?php checked( ! empty( $s['nip05_enabled'] ) ); ?> />
								<?php esc_html_e( 'Serve /.well-known/nostr.json for NIP-05', 'synalysis-blog-importer-for-nostr' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Clients request this URL with a name query argument and receive the matching hex pubkey plus the relay list from this page.', 'synalysis-blog-importer-for-nostr' ); ?>
								<code><?php echo esc_html( $nip05_sample_url ); ?></code>
							</p>
							<p class="description"><?php esc_html_e( 'Local names are taken from each author’s kind 0 profile (short “name” field, then display name). Mappings are refreshed when you save these settings.', 'synalysis-blog-importer-for-nostr' ); ?></p>
							<?php
							if ( $idmap !== array() ) {
								echo '<p><strong>' . esc_html__( 'Current NIP-05 names', 'synalysis-blog-importer-for-nostr' ) . '</strong></p><ul class="synalysis-blog-importer-nip05-list" style="list-style:disc;margin-left:1.5em;">';
								foreach ( $idmap as $lname => $phex ) {
									if ( ! is_string( $lname ) || ! is_string( $phex ) ) {
										continue;
									}
									echo '<li><code>' . esc_html( $lname ) . '</code> → <code>' . esc_html( substr( $phex, 0, 16 ) ) . '…</code></li>';
								}
								echo '</ul>';
							} else {
								echo '<p class="description">' . esc_html__( 'No identities yet. Add authors and relays above, enable this option, and save to fetch profiles and build names.', 'synalysis-blog-importer-for-nostr' ) . '</p>';
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="synalysis_blog_importer_archive_slug"><?php esc_html_e( 'Archive URL slug', 'synalysis-blog-importer-for-nostr' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="synalysis_blog_importer_archive_slug" name="synalysis_blog_importer_settings[archive_slug]" value="<?php echo esc_attr( (string) $s['archive_slug'] ); ?>" />
							<p class="description"><?php esc_html_e( 'List of articles: https://yoursite.example/{slug}/', 'synalysis-blog-importer-for-nostr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="synalysis_blog_importer_archive_list_title"><?php esc_html_e( 'Archive page title', 'synalysis-blog-importer-for-nostr' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="synalysis_blog_importer_archive_list_title" name="synalysis_blog_importer_settings[archive_list_title]" value="<?php echo esc_attr( (string) $s['archive_list_title'] ); ?>" maxlength="200" />
							<p class="description"><?php esc_html_e( 'Heading shown above the article list. Leave empty to use the default WordPress title for this archive.', 'synalysis-blog-importer-for-nostr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Single article URLs', 'synalysis-blog-importer-for-nostr' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="synalysis_blog_importer_settings[single_url_mode]" value="prefixed" <?php checked( (string) $s['single_url_mode'], 'prefixed' ); ?> />
									<?php esc_html_e( 'Under archive slug (recommended)', 'synalysis-blog-importer-for-nostr' ); ?>
								</label><br />
								<label>
									<input type="radio" name="synalysis_blog_importer_settings[single_url_mode]" value="root" <?php checked( (string) $s['single_url_mode'], 'root' ); ?> />
									<?php esc_html_e( 'Site root (one path segment; may collide with pages)', 'synalysis-blog-importer-for-nostr' ); ?>
								</label>
							</fieldset>
							<p class="description" style="margin-top:1em;">
								<input type="hidden" name="synalysis_blog_importer_settings[show_article_tags]" value="0" />
								<label>
									<input type="checkbox" name="synalysis_blog_importer_settings[show_article_tags]" id="synalysis_blog_importer_show_article_tags" value="1" <?php checked( ! empty( $s['show_article_tags'] ) ); ?> />
									<?php esc_html_e( 'Show topic tags under each article', 'synalysis-blog-importer-for-nostr' ); ?>
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'Tags come from hashtag tags (t) on the article event. Run sync after changing authors or relays to refresh stored tags.', 'synalysis-blog-importer-for-nostr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="synalysis_blog_importer_sync_interval"><?php esc_html_e( 'Sync interval', 'synalysis-blog-importer-for-nostr' ); ?></label></th>
						<td>
							<select id="synalysis_blog_importer_sync_interval" name="synalysis_blog_importer_settings[sync_interval]">
								<option value="synalysis_blog_importer_quarterhourly" <?php selected( (string) $s['sync_interval'], 'synalysis_blog_importer_quarterhourly' ); ?>><?php esc_html_e( 'Every 15 minutes', 'synalysis-blog-importer-for-nostr' ); ?></option>
								<option value="hourly" <?php selected( (string) $s['sync_interval'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'synalysis-blog-importer-for-nostr' ); ?></option>
								<option value="twicedaily" <?php selected( (string) $s['sync_interval'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice daily', 'synalysis-blog-importer-for-nostr' ); ?></option>
								<option value="daily" <?php selected( (string) $s['sync_interval'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'synalysis-blog-importer-for-nostr' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="synalysis_blog_importer_request_timeout"><?php esc_html_e( 'Relay request timeout (seconds)', 'synalysis-blog-importer-for-nostr' ); ?></label></th>
						<td>
							<input type="number" min="5" max="120" class="small-text" id="synalysis_blog_importer_request_timeout" name="synalysis_blog_importer_settings[request_timeout]" value="<?php echo esc_attr( (string) (int) $s['request_timeout'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="synalysis_blog_importer_event_limit"><?php esc_html_e( 'Max events per relay', 'synalysis-blog-importer-for-nostr' ); ?></label></th>
						<td>
							<input type="number" min="1" max="2000" class="small-text" id="synalysis_blog_importer_event_limit" name="synalysis_blog_importer_settings[event_limit]" value="<?php echo esc_attr( (string) (int) $s['event_limit'] ); ?>" />
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save changes', 'synalysis-blog-importer-for-nostr' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Manual sync', 'synalysis-blog-importer-for-nostr' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="synalysis_blog_importer_sync_now" />
				<?php wp_nonce_field( 'synalysis_blog_importer_sync_now' ); ?>
				<?php submit_button( __( 'Sync now', 'synalysis-blog-importer-for-nostr' ), 'secondary' ); ?>
			</form>

			<h2><?php esc_html_e( 'Status', 'synalysis-blog-importer-for-nostr' ); ?></h2>
			<?php
			$counts   = wp_count_posts( Synalysis_Blog_Importer_CPT::POST_TYPE );
			$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
			?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of published relay-imported articles. */
						__( 'Published articles: %d', 'synalysis-blog-importer-for-nostr' ),
						$published
					)
				);
				?>
			</p>
			<p>
				<?php
				$last = (string) $s['last_sync'];
				if ( $last !== '' ) {
					printf(
						/* translators: %s: ISO datetime */
						esc_html__( 'Last sync: %s', 'synalysis-blog-importer-for-nostr' ),
						esc_html( $last )
					);
				} elseif ( $published > 0 ) {
					esc_html_e( 'No sync has been recorded in these settings yet, but you already have published articles. They may have been created manually, imported, or synced on another copy of the site. Run Sync now to refresh from relays and update this status.', 'synalysis-blog-importer-for-nostr' );
				} else {
					esc_html_e( 'No sync has completed yet.', 'synalysis-blog-importer-for-nostr' );
				}
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %d: number of articles updated last run */
					esc_html__( 'Articles updated in last run: %d', 'synalysis-blog-importer-for-nostr' ),
					(int) $s['last_sync_count']
				);
				?>
			</p>
			<?php
			$err = (string) $s['last_sync_errors'];
			if ( $err !== '' ) {
				echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Last errors', 'synalysis-blog-importer-for-nostr' ) . '</strong></p><pre class="synalysis-blog-importer-log">' . esc_html( $err ) . '</pre></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Whether the current admin screen is this plugin’s Settings submenu.
	 */
	private function is_plugin_settings_screen(): bool {
		if ( ! is_admin() ) {
			return false;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && isset( $screen->id ) && $screen->id === 'settings_page_' . self::PAGE_SLUG;
	}

	public function handle_sync_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'synalysis-blog-importer-for-nostr' ) );
		}
		check_admin_referer( 'synalysis_blog_importer_sync_now' );

		$sync = new Synalysis_Blog_Importer_Sync();
		$sync->sync( true );

		wp_safe_redirect(
			add_query_arg(
				'synalysis_blog_importer_synced',
				'1',
				admin_url( 'options-general.php?page=' . self::PAGE_SLUG )
			)
		);
		exit;
	}

	public function admin_notices(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! $this->is_plugin_settings_screen() ) {
			return;
		}
		if ( get_transient( 'synalysis_blog_importer_settings_saved' ) ) {
			delete_transient( 'synalysis_blog_importer_settings_saved' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved. Rewrite rules were flushed.', 'synalysis-blog-importer-for-nostr' ) . '</p></div>';
		}
		if (
			isset( $_GET['synalysis_blog_importer_synced'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after nonce-protected admin-post redirect.
			&& sanitize_text_field( wp_unslash( (string) $_GET['synalysis_blog_importer_synced'] ) ) === '1'
		) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sync finished.', 'synalysis-blog-importer-for-nostr' ) . '</p></div>';
		}
		$nip_note = get_transient( 'synalysis_blog_importer_nip05_notice' );
		if ( is_string( $nip_note ) && $nip_note !== '' ) {
			delete_transient( 'synalysis_blog_importer_nip05_notice' );
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $nip_note ) . '</p></div>';
		}
	}
}
