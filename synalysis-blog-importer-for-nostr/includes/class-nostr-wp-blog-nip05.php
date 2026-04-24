<?php
/**
 * NIP-05: serve /.well-known/nostr.json?name=<local-part> with pubkey + relays.
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nostr_WP_Blog_Nip05 {

	public const QUERY_VAR = 'nostr_wp_blog_nip05';

	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ), 5 );
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve' ), 0 );
	}

	public static function register_rewrite_rules(): void {
		add_rewrite_rule(
			'^\\.well-known/nostr\\.json$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	public function add_rewrite_rule(): void {
		self::register_rewrite_rules();
	}

	/**
	 * @param list<string> $vars
	 * @return list<string>
	 */
	public function filter_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_serve(): void {
		if ( ! $this->is_well_known_request() ) {
			return;
		}

		$settings = nostr_wp_blog_get_settings();
		if ( empty( $settings['nip05_enabled'] ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$name = isset( $_GET['name'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$name = strtolower( $name );
		if ( strlen( $name ) > 255 ) {
			$name = substr( $name, 0, 255 );
		}

		$map = nostr_wp_blog_get_nip05_name_map();

		$names_payload  = array();
		$relays_payload = array();

		if ( $name !== '' && isset( $map[ $name ] ) ) {
			$pk = $map[ $name ];
			$names_payload[ $name ] = $pk;
			$relays                   = nostr_wp_blog_parse_lines( (string) $settings['relays'] );
			$relays_payload[ $pk ]    = array_values( $relays );
		}

		$body = wp_json_encode(
			array(
				'names'  => $names_payload,
				'relays' => $relays_payload,
			),
			JSON_UNESCAPED_SLASHES
		);

		if ( ! is_string( $body ) ) {
			$body = '{"names":{},"relays":{}}';
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Cache-Control: public, max-age=300' );
		status_header( 200 );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON API response.
		echo $body;
		exit;
	}

	private function is_well_known_request(): bool {
		if ( (int) get_query_var( self::QUERY_VAR, 0 ) === 1 ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only to compare request path via wp_parse_url(); not echoed.
		$req_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$req_path = wp_parse_url( $req_uri, PHP_URL_PATH );
		if ( ! is_string( $req_path ) || $req_path === '' ) {
			return false;
		}

		$expected = wp_parse_url( home_url( '/.well-known/nostr.json' ), PHP_URL_PATH );
		if ( ! is_string( $expected ) || $expected === '' ) {
			return false;
		}

		return untrailingslashit( $req_path ) === untrailingslashit( $expected );
	}
}
