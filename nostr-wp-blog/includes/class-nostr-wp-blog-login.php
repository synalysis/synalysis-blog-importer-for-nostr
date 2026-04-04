<?php
/**
 * Optional wp-login.php sign-in via NIP-07 + NIP-42 (kind 22242).
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nostr_WP_Blog_Login {

	public const REST_NAMESPACE = 'nostr-wp-blog/v1';
	public const NONCE_ACTION  = 'nostr_wp_blog_nostr_login';
	public const TRANSIENT_PREFIX = 'nwb_nl_';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue_scripts' ) );
		add_action( 'login_form', array( $this, 'login_form' ) );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/login-challenge',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_login_challenge' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/login-verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_login_verify' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function login_enqueue_scripts(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		/*
		 * Inline bootstrap runs before nostr-login.js. Browsers do not expose an event when
		 * NIP-07 extensions inject window.nostr; we intercept assignment via a setter (when
		 * the property is still undefined) and dispatch nostr-wp-blog:provider.
		 */
		wp_register_script(
			'nostr-wp-blog-login-boot',
			false,
			array(),
			NOSTR_WP_BLOG_VERSION,
			true
		);
		wp_enqueue_script( 'nostr-wp-blog-login-boot' );
		wp_add_inline_script(
			'nostr-wp-blog-login-boot',
			$this->login_nostr_bootstrap_js(),
			'after'
		);

		wp_enqueue_script(
			'nostr-wp-blog-login',
			NOSTR_WP_BLOG_URL . 'assets/js/nostr-login.js',
			array( 'nostr-wp-blog-login-boot' ),
			NOSTR_WP_BLOG_VERSION,
			true
		);

		$redirect = '';
		if ( isset( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect = wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ), admin_url() );
		}
		if ( $redirect === '' ) {
			$redirect = admin_url();
		}

		wp_localize_script(
			'nostr-wp-blog-login',
			'nostrWpBlogLogin',
			array(
				'restUrl'    => esc_url_raw( rest_url( self::REST_NAMESPACE . '/' ) ),
				'nonce'      => wp_create_nonce( self::NONCE_ACTION ),
				'relayUri'   => $this->relay_uri(),
				'redirectTo' => $redirect,
				'strings'    => array(
					'button'    => __( 'Sign in with Nostr', 'nostr-wp-blog' ),
					'noExtension' => __( 'No Nostr extension found. Install a NIP-07-capable browser extension and try again.', 'nostr-wp-blog' ),
					'working'   => __( 'Waiting for signature…', 'nostr-wp-blog' ),
					'failed'    => __( 'Nostr sign-in failed.', 'nostr-wp-blog' ),
					'notAuthor' => __( 'This key is not listed as an author for this site.', 'nostr-wp-blog' ),
				),
			)
		);
	}

	public function login_form(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}
		?>
		<div id="nostr-wp-blog-login" class="nostr-wp-blog-login" style="margin-top:1em;padding-top:1em;border-top:1px solid #c3c4c7;">
			<p>
				<button type="button" class="button button-secondary" id="nostr-wp-blog-login-btn" disabled>
					<?php esc_html_e( 'Sign in with Nostr', 'nostr-wp-blog' ); ?>
				</button>
				<span id="nostr-wp-blog-login-status" class="description" style="display:block;margin-top:0.5em;" aria-live="polite"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public function rest_login_challenge( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'nostr_login_disabled', __( 'Nostr login is not available.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$nonce = isset( $params['nonce'] ) ? (string) $params['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Invalid or expired security token. Reload the login page.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$pubkey = isset( $params['pubkey'] ) ? strtolower( trim( (string) $params['pubkey'] ) ) : '';
		if ( strlen( $pubkey ) !== 64 || ! ctype_xdigit( $pubkey ) ) {
			return new \WP_Error( 'invalid_pubkey', __( 'Invalid public key.', 'nostr-wp-blog' ), array( 'status' => 400 ) );
		}

		$authors = array_flip( nostr_wp_blog_get_author_pubkey_hexes() );
		if ( ! isset( $authors[ $pubkey ] ) ) {
			return new \WP_Error( 'not_author', __( 'This key is not listed as an author for this site.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$challenge = strtolower( bin2hex( random_bytes( 32 ) ) );
		$token     = strtolower( bin2hex( random_bytes( 16 ) ) );

		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'pubkey'    => $pubkey,
				'challenge' => $challenge,
				'exp'       => time() + 300,
			),
			300
		);

		return new \WP_REST_Response(
			array(
				'token'     => $token,
				'challenge' => $challenge,
				'relayUri'  => $this->relay_uri(),
			),
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 */
	public function rest_login_verify( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'nostr_login_disabled', __( 'Nostr login is not available.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$nonce = isset( $params['nonce'] ) ? (string) $params['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Invalid or expired security token. Reload the login page.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$token = isset( $params['token'] ) ? strtolower( preg_replace( '/[^a-f0-9]/', '', (string) $params['token'] ) ) : '';
		if ( strlen( $token ) !== 32 ) {
			return new \WP_Error( 'invalid_token', __( 'Invalid session.', 'nostr-wp-blog' ), array( 'status' => 400 ) );
		}

		$data = get_transient( self::TRANSIENT_PREFIX . $token );

		if ( ! is_array( $data ) || ! isset( $data['pubkey'], $data['challenge'], $data['exp'] ) ) {
			return new \WP_Error( 'expired', __( 'Sign-in session expired. Try again.', 'nostr-wp-blog' ), array( 'status' => 410 ) );
		}
		if ( time() > (int) $data['exp'] ) {
			delete_transient( self::TRANSIENT_PREFIX . $token );
			return new \WP_Error( 'expired', __( 'Sign-in session expired. Try again.', 'nostr-wp-blog' ), array( 'status' => 410 ) );
		}

		$event_raw = $params['event'] ?? null;
		if ( ! is_array( $event_raw ) ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid signed event.', 'nostr-wp-blog' ), array( 'status' => 400 ) );
		}

		$event = $this->normalize_event_from_array( $event_raw );
		if ( $event === null ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid signed event.', 'nostr-wp-blog' ), array( 'status' => 400 ) );
		}

		$event_obj = wp_json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $event_obj ) ) {
			return new \WP_Error( 'invalid_event', __( 'Invalid signed event.', 'nostr-wp-blog' ), array( 'status' => 400 ) );
		}

		$verified = new \swentel\nostr\Event\Event();
		if ( ! $verified->verify( $event_obj ) ) {
			return new \WP_Error( 'bad_sig', __( 'Signature verification failed.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		if ( $event->kind !== 22242 ) {
			return new \WP_Error( 'wrong_kind', __( 'Wrong event kind.', 'nostr-wp-blog' ), array( 'status' => 400 ) );
		}

		$pk = strtolower( (string) $event->pubkey );
		if ( $pk !== (string) $data['pubkey'] ) {
			return new \WP_Error( 'pubkey_mismatch', __( 'Public key mismatch.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$authors = array_flip( nostr_wp_blog_get_author_pubkey_hexes() );
		if ( ! isset( $authors[ $pk ] ) ) {
			return new \WP_Error( 'not_author', __( 'This key is not listed as an author for this site.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$chal = $this->tag_value( $event->tags, 'challenge' );
		if ( ! hash_equals( (string) $data['challenge'], $chal ) ) {
			return new \WP_Error( 'challenge_mismatch', __( 'Challenge mismatch.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$relay = $this->tag_value( $event->tags, 'relay' );
		if ( $relay === '' || ! hash_equals( $this->relay_uri(), $relay ) ) {
			return new \WP_Error( 'relay_mismatch', __( 'Relay tag mismatch.', 'nostr-wp-blog' ), array( 'status' => 403 ) );
		}

		$skew = abs( time() - (int) $event->created_at );
		if ( $skew > 900 ) {
			return new \WP_Error( 'stale', __( 'Event timestamp is too far from server time.', 'nostr-wp-blog' ), array( 'status' => 400 ) );
		}

		$uid = (int) nostr_wp_blog_get_settings()['nostr_login_user_id'];
		$user = get_userdata( $uid );
		if ( ! $user ) {
			return new \WP_Error( 'no_user', __( 'Nostr login is not configured.', 'nostr-wp-blog' ), array( 'status' => 500 ) );
		}

		delete_transient( self::TRANSIENT_PREFIX . $token );

		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, true );
		do_action( 'wp_login', $user->user_login, $user );

		$redirect = isset( $params['redirect_to'] ) ? wp_validate_redirect( (string) $params['redirect_to'], admin_url() ) : admin_url();

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'redirect' => $redirect,
			),
			200
		);
	}

	private function is_enabled(): bool {
		$s = nostr_wp_blog_get_settings();
		return ! empty( $s['nostr_login_enabled'] )
			&& (int) ( $s['nostr_login_user_id'] ?? 0 ) > 0
			&& nostr_wp_blog_get_author_pubkey_hexes() !== array();
	}

	private function relay_uri(): string {
		return trailingslashit( home_url( '/', is_ssl() ? 'https' : 'http' ) );
	}

	/**
	 * @param mixed $tags Tags from JSON.
	 */
	private function tag_value( mixed $tags, string $name ): string {
		if ( ! is_array( $tags ) ) {
			return '';
		}
		foreach ( $tags as $tag ) {
			if ( ! is_array( $tag ) || ! isset( $tag[0], $tag[1] ) ) {
				continue;
			}
			if ( (string) $tag[0] !== $name ) {
				continue;
			}
			if ( ! is_string( $tag[1] ) ) {
				return '';
			}
			return $tag[1];
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $raw Raw event from JSON.
	 */
	private function normalize_event_from_array( array $raw ): ?\stdClass {
		$id        = isset( $raw['id'] ) && is_string( $raw['id'] ) ? $raw['id'] : '';
		$pubkey    = isset( $raw['pubkey'] ) && is_string( $raw['pubkey'] ) ? strtolower( $raw['pubkey'] ) : '';
		$sig       = isset( $raw['sig'] ) && is_string( $raw['sig'] ) ? $raw['sig'] : '';
		$kind      = isset( $raw['kind'] ) ? (int) $raw['kind'] : -1;
		$content   = isset( $raw['content'] ) && is_string( $raw['content'] ) ? $raw['content'] : '';
		$created   = isset( $raw['created_at'] ) ? (int) $raw['created_at'] : 0;
		$tags_in   = $raw['tags'] ?? null;
		if ( ! is_array( $tags_in ) ) {
			return null;
		}
		$tags = array();
		foreach ( $tags_in as $row ) {
			if ( ! is_array( $row ) ) {
				return null;
			}
			$norm = array();
			foreach ( $row as $cell ) {
				if ( ! is_string( $cell ) ) {
					return null;
				}
				$norm[] = $cell;
			}
			$tags[] = $norm;
		}
		if ( $id === '' || strlen( $pubkey ) !== 64 || $sig === '' || $created < 1 ) {
			return null;
		}
		$o            = new \stdClass();
		$o->id        = $id;
		$o->pubkey    = $pubkey;
		$o->created_at = $created;
		$o->kind      = $kind;
		$o->tags      = $tags;
		$o->content   = $content;
		$o->sig       = $sig;
		return $o;
	}

	/**
	 * Inline script: observe window.nostr (NIP-07) via assignment hook + announce when ready.
	 * There is no browser event when extensions finish injecting; this runs before nostr-login.js.
	 */
	private function login_nostr_bootstrap_js(): string {
		return <<<'JS'
(function (w) {
	var EVT = 'nostr-wp-blog:provider';
	function ready(n) {
		return (
			n &&
			typeof n.getPublicKey === 'function' &&
			typeof n.signEvent === 'function'
		);
	}
	function announce(n) {
		if (!ready(n)) {
			return;
		}
		w.dispatchEvent(new CustomEvent(EVT, { detail: n }));
	}
	var existing = w.nostr;
	if (ready(existing)) {
		queueMicrotask(function () {
			announce(existing);
		});
		return;
	}
	if (typeof existing !== 'undefined') {
		return;
	}
	try {
		var holder;
		Object.defineProperty(w, 'nostr', {
			configurable: true,
			enumerable: true,
			get: function () {
				return holder;
			},
			set: function (v) {
				holder = v;
				announce(v);
			},
		});
	} catch (err) {
	}
})(window);
JS;
	}
}
