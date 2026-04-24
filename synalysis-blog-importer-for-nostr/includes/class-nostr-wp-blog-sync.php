<?php
/**
 * Relay fetch, dedupe, and post upsert.
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\RelayResponse\RelayResponseEvent;
use swentel\nostr\Request\Request;
use swentel\nostr\Subscription\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nostr_WP_Blog_Sync {

	public const CRON_HOOK = 'nostr_wp_blog_sync_cron';

	public const META_ADDRESS       = '_nostr_wp_blog_address';
	public const META_EVENT_ID      = '_nostr_wp_blog_event_id';
	public const META_PUBKEY        = '_nostr_wp_blog_pubkey';
	public const META_D_TAG         = '_nostr_wp_blog_d_tag';
	public const META_IMAGE         = '_nostr_wp_blog_image';
	public const META_PUBLISHED_AT  = '_nostr_wp_blog_published_at';
	public const META_EVENT_CREATED = '_nostr_wp_blog_event_created_at';
	public const META_MARKDOWN      = '_nostr_wp_blog_markdown';
	public const META_AUTHOR_DISPLAY = '_nostr_wp_blog_author_display';
	public const META_TOPIC_TAGS     = '_nostr_wp_blog_topic_tags';

	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_sync' ) );
	}

	public function run_scheduled_sync(): void {
		$this->sync( false );
	}

	/**
	 * @return array{imported:int, skipped:int, errors:list<string>}
	 */
	public function sync( bool $manual ): array {
		$settings = nostr_wp_blog_get_settings();
		$authors  = nostr_wp_blog_parse_lines( (string) $settings['authors'] );
		$relays   = nostr_wp_blog_parse_lines( (string) $settings['relays'] );

		$result = array(
			'imported' => 0,
			'errors'   => array(),
		);

		if ( $authors === array() ) {
			$result['errors'][] = __( 'No author npubs or pubkeys configured.', 'synalysis-blog-importer-for-nostr' );
			$this->persist_sync_meta( $result, $manual );
			return $result;
		}

		if ( $relays === array() ) {
			$result['errors'][] = __( 'No relay URLs configured.', 'synalysis-blog-importer-for-nostr' );
			$this->persist_sync_meta( $result, $manual );
			return $result;
		}

		$events = array();
		foreach ( $relays as $relay_url ) {
			try {
				$batch = $this->fetch_from_relay( $relay_url, $authors, $settings );
				foreach ( $batch as $ev ) {
					$events[] = $ev;
				}
			} catch ( \Throwable $e ) {
				$result['errors'][] = sprintf(
					/* translators: 1: relay URL, 2: error message */
					__( 'Relay %1$s: %2$s', 'synalysis-blog-importer-for-nostr' ),
					$relay_url,
					$e->getMessage()
				);
			}
		}

		$deduped = $this->dedupe_replaceable( $events );

		$pubkeys = array();
		foreach ( $deduped as $event ) {
			if ( ! empty( $event->pubkey ) ) {
				$pubkeys[ strtolower( (string) $event->pubkey ) ] = true;
			}
		}
		$pubkeys = array_keys( $pubkeys );
		$profiles = $this->collect_author_profiles( $relays, $pubkeys, $settings );

		foreach ( $deduped as $event ) {
			if ( $this->upsert_article( $event, $result, $profiles ) ) {
				++$result['imported'];
			}
		}

		$this->persist_sync_meta( $result, $manual );

		if ( $result['imported'] > 0 || $manual ) {
			Nostr_WP_Blog_CPT::flush_rewrite_rules();
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return list<object>
	 */
	private function fetch_from_relay( string $relay_url, array $authors, array $settings ): array {
		$timeout = isset( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 45;
		$limit   = isset( $settings['event_limit'] ) ? max( 1, min( 2000, (int) $settings['event_limit'] ) ) : 500;

		$filter = new Filter();
		$filter->setAuthors( $authors );
		$filter->setKinds( array( 30023 ) );
		$filter->setLimit( $limit );

		$subscription   = new Subscription();
		$requestMessage = new RequestMessage( $subscription->getId(), array( $filter ) );

		$relay   = new Relay( $relay_url );
		$request = new Request( $relay, $requestMessage );
		$request->setTimeout( $timeout );

		$responses = $request->send();
		$events    = array();

		$list = $responses[ $relay_url ] ?? array();
		if ( ! is_array( $list ) ) {
			return $events;
		}

		foreach ( $list as $message ) {
			if ( $message instanceof RelayResponseEvent && isset( $message->event ) && is_object( $message->event ) ) {
				$events[] = $message->event;
			}
		}

		return $events;
	}

	/**
	 * Fetch kind 0 metadata from relays and pick the newest profile per pubkey.
	 *
	 * @param list<string>          $relay_urls
	 * @param list<string>          $pubkeys_hex Lowercase hex pubkeys.
	 * @param array<string, mixed>  $settings
	 * @return array<string, string> Pubkey => display name
	 */
	private function collect_author_profiles( array $relay_urls, array $pubkeys_hex, array $settings ): array {
		$by_pk = $this->collect_latest_kind0_by_pubkey( $relay_urls, $pubkeys_hex, $settings );
		$out   = array();
		foreach ( $by_pk as $pk => $row ) {
			$n = $this->parse_kind0_display_name( $row['event'] );
			if ( $n !== '' ) {
				$out[ $pk ] = $n;
			}
		}
		return $out;
	}

	/**
	 * Latest kind 0 event per pubkey (merged across relays).
	 *
	 * @param list<string>         $relay_urls
	 * @param list<string>       $pubkeys_hex
	 * @param array<string, mixed> $settings
	 * @return array<string, array{created: int, event: object}>
	 */
	private function collect_latest_kind0_by_pubkey( array $relay_urls, array $pubkeys_hex, array $settings ): array {
		if ( $pubkeys_hex === array() ) {
			return array();
		}

		$best = array();

		foreach ( $relay_urls as $relay_url ) {
			try {
				$batch = $this->fetch_kind_zero_from_relay( $relay_url, $pubkeys_hex, $settings );
				foreach ( $batch as $ev ) {
					if ( ! isset( $ev->pubkey, $ev->created_at ) ) {
						continue;
					}
					$pk = strtolower( (string) $ev->pubkey );
					$t  = (int) $ev->created_at;
					if ( ! isset( $best[ $pk ] ) || $t > $best[ $pk ]['created'] ) {
						$best[ $pk ] = array(
							'created' => $t,
							'event'   => $ev,
						);
					}
				}
			} catch ( \Throwable ) {
				// Best-effort; article relay errors are tracked separately.
			}
		}

		return $best;
	}

	/**
	 * Build NIP-05 name => hex pubkey map from Authors (kind 0 "name" field, then display name fallbacks).
	 *
	 * @param array<string, mixed> $settings Merged settings (authors, relays, timeouts, …).
	 * @return array{map: array<string, string>, missing_profile_count: int}
	 */
	public function build_nip05_identity_map( array $settings ): array {
		$empty = array(
			'map'                    => array(),
			'missing_profile_count'  => 0,
		);

		$author_lines = nostr_wp_blog_parse_lines( (string) ( $settings['authors'] ?? '' ) );
		$relays       = nostr_wp_blog_parse_lines( (string) ( $settings['relays'] ?? '' ) );
		if ( $author_lines === array() || $relays === array() ) {
			return $empty;
		}

		$ordered_pubkeys = array();
		$seen            = array();
		foreach ( $author_lines as $line ) {
			$hex = nostr_wp_blog_normalize_pubkey_hex( $line );
			if ( $hex === null || isset( $seen[ $hex ] ) ) {
				continue;
			}
			$seen[ $hex ]            = true;
			$ordered_pubkeys[] = $hex;
		}

		if ( $ordered_pubkeys === array() ) {
			return $empty;
		}

		$by_pk = $this->collect_latest_kind0_by_pubkey( $relays, $ordered_pubkeys, $settings );

		$map                  = array();
		$missing_profile_count = 0;

		foreach ( $ordered_pubkeys as $pk ) {
			$raw_short = '';
			if ( isset( $by_pk[ $pk ] ) ) {
				$raw_short = $this->parse_kind0_short_name( $by_pk[ $pk ]['event'] );
			} else {
				++$missing_profile_count;
			}

			$base = nostr_wp_blog_sanitize_nip05_local_name( $raw_short );
			if ( $base === '' ) {
				$base = 'n-' . substr( $pk, 0, 8 );
			}

			$candidate = $base;
			for ( $i = 0; $i < 100; $i++ ) {
				if ( ! isset( $map[ $candidate ] ) || $map[ $candidate ] === $pk ) {
					break;
				}
				$candidate = $base . '-' . substr( $pk, 0, min( 16, 4 + $i ) );
			}
			$map[ $candidate ] = $pk;
		}

		return array(
			'map'                   => $map,
			'missing_profile_count' => $missing_profile_count,
		);
	}

	/**
	 * @param list<string>         $pubkeys_hex
	 * @param array<string, mixed> $settings
	 * @return list<object>
	 */
	private function fetch_kind_zero_from_relay( string $relay_url, array $pubkeys_hex, array $settings ): array {
		$timeout = isset( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 45;
		$n       = count( $pubkeys_hex );
		$limit   = max( 100, min( 2000, $n * 15 ) );

		$filter = new Filter();
		$filter->setAuthors( $pubkeys_hex );
		$filter->setKinds( array( 0 ) );
		$filter->setLimit( $limit );

		$subscription   = new Subscription();
		$requestMessage = new RequestMessage( $subscription->getId(), array( $filter ) );

		$relay   = new Relay( $relay_url );
		$request = new Request( $relay, $requestMessage );
		$request->setTimeout( $timeout );

		$responses = $request->send();
		$events    = array();

		$list = $responses[ $relay_url ] ?? array();
		if ( ! is_array( $list ) ) {
			return $events;
		}

		foreach ( $list as $message ) {
			if ( $message instanceof RelayResponseEvent && isset( $message->event ) && is_object( $message->event ) ) {
				$events[] = $message->event;
			}
		}

		return $events;
	}

	/**
	 * Display name from kind 0 JSON content (NIP-01 metadata + common client fields).
	 */
	private function parse_kind0_display_name( object $event ): string {
		if ( ! isset( $event->content ) || ! is_string( $event->content ) ) {
			return '';
		}
		$data = json_decode( $event->content, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$candidates = array( 'display_name', 'displayName', 'name' );
		foreach ( $candidates as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$s = trim( wp_strip_all_tags( $data[ $key ] ) );
			if ( $s !== '' ) {
				return mb_substr( $s, 0, 200 );
			}
		}
		return '';
	}

	/**
	 * Prefer short "name" from kind 0 (NIP-01), then display_name variants — for NIP-05 local parts.
	 */
	private function parse_kind0_short_name( object $event ): string {
		if ( ! isset( $event->content ) || ! is_string( $event->content ) ) {
			return '';
		}
		$data = json_decode( $event->content, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$candidates = array( 'name', 'displayName', 'display_name' );
		foreach ( $candidates as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_string( $data[ $key ] ) ) {
				continue;
			}
			$s = trim( wp_strip_all_tags( $data[ $key ] ) );
			if ( $s !== '' ) {
				return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, 200 ) : substr( $s, 0, 200 );
			}
		}
		return '';
	}

	/**
	 * @param list<object> $events
	 * @return list<object>
	 */
	private function dedupe_replaceable( array $events ): array {
		$best = array();
		foreach ( $events as $event ) {
			if ( ! isset( $event->pubkey, $event->kind, $event->created_at ) ) {
				continue;
			}
			$d = nostr_wp_blog_event_tag( $event, 'd' );
			if ( $d === '' ) {
				continue;
			}
			$key = strtolower( (string) $event->pubkey ) . ':' . $d;
			if ( ! isset( $best[ $key ] ) || (int) $event->created_at > (int) $best[ $key ]->created_at ) {
				$best[ $key ] = $event;
			}
		}
		return array_values( $best );
	}

	/**
	 * @param array{imported:int, errors:list<string>} $result
	 * @param array<string, string>                    $profiles Pubkey => display name from kind 0
	 */
	private function upsert_article( object $event, array &$result, array $profiles = array() ): bool {
		$d = nostr_wp_blog_event_tag( $event, 'd' );
		if ( $d === '' || empty( $event->pubkey ) || empty( $event->id ) ) {
			return false;
		}

		$pubkey  = strtolower( (string) $event->pubkey );
		$address = nostr_wp_blog_article_address( $pubkey, $d );

		$title_tag = nostr_wp_blog_event_tag( $event, 'title' );
		$summary   = nostr_wp_blog_event_tag( $event, 'summary' );
		$image     = nostr_wp_blog_event_tag( $event, 'image' );
		$pub_at    = nostr_wp_blog_event_tag( $event, 'published_at' );

		$title = $title_tag !== '' ? $title_tag : $d;

		$content_md = isset( $event->content ) && is_string( $event->content ) ? $event->content : '';
		$html         = wp_kses_post( Nostr_WP_Blog_Markdown::to_html( $content_md ) );

		$dates = $this->resolve_post_dates( $event, $pub_at );

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded sync: one row by stable address meta.
		$existing = get_posts(
			array(
				'post_type'      => Nostr_WP_Blog_CPT::POST_TYPE,
				'post_status'    => 'any',
				'meta_key'       => self::META_ADDRESS,
				'meta_value'     => $address,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$postarr = array(
			'post_type'      => Nostr_WP_Blog_CPT::POST_TYPE,
			'post_title'     => $title,
			'post_excerpt'   => $summary,
			'post_content'   => $html,
			'post_status'    => 'publish',
			'post_date'      => $dates['local'],
			'post_date_gmt'  => $dates['gmt'],
		);

		if ( $existing !== array() ) {
			$postarr['ID'] = (int) $existing[0];
		} else {
			$slug = $this->build_new_article_slug(
				$title_tag,
				$d,
				$pubkey,
				(string) $event->id,
				$address
			);
			$slug = trim( (string) apply_filters( 'nostr_wp_blog_new_article_slug', $slug, $event, $pubkey, $d, $address ) );
			if ( $slug === '' ) {
				$slug = 'article-' . substr( hash( 'sha256', $address ), 0, 12 );
			}
			$slug = $this->ensure_unique_slug( $slug, $pubkey, $d );
			$postarr['post_name'] = $slug;
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			$result['errors'][] = $post_id->get_error_message();
			return false;
		}

		update_post_meta( $post_id, self::META_ADDRESS, $address );
		update_post_meta( $post_id, self::META_EVENT_ID, (string) $event->id );
		update_post_meta( $post_id, self::META_PUBKEY, $pubkey );
		update_post_meta( $post_id, self::META_D_TAG, $d );
		update_post_meta( $post_id, self::META_IMAGE, esc_url_raw( $image ) );
		update_post_meta( $post_id, self::META_MARKDOWN, $content_md );
		update_post_meta( $post_id, self::META_EVENT_CREATED, (string) (int) $event->created_at );
		if ( $pub_at !== '' && ctype_digit( $pub_at ) ) {
			update_post_meta( $post_id, self::META_PUBLISHED_AT, $pub_at );
		} else {
			delete_post_meta( $post_id, self::META_PUBLISHED_AT );
		}

		if ( isset( $profiles[ $pubkey ] ) && $profiles[ $pubkey ] !== '' ) {
			update_post_meta( $post_id, self::META_AUTHOR_DISPLAY, $profiles[ $pubkey ] );
		}

		$topic_tags = nostr_wp_blog_event_tags_named( $event, 't' );
		$topics_clean = array();
		foreach ( $topic_tags as $t ) {
			$t = sanitize_text_field( $t );
			if ( $t !== '' && ! in_array( $t, $topics_clean, true ) ) {
				$topics_clean[] = $t;
			}
		}
		if ( $topics_clean !== array() ) {
			update_post_meta(
				$post_id,
				self::META_TOPIC_TAGS,
				wp_json_encode( $topics_clean, JSON_UNESCAPED_UNICODE )
			);
		} else {
			delete_post_meta( $post_id, self::META_TOPIC_TAGS );
		}

		return true;
	}

	/**
	 * Build post_name for a newly imported article (first insert only; updates keep the existing slug).
	 *
	 * Prefers the NIP-23 title over the d tag so URLs are readable. When d (or title) is only a long
	 * timestamp-style string, uses a short n-{pubkey}-{tail} pattern instead of a huge number.
	 *
	 * @param string $title_tag NIP-23 title tag (may be empty).
	 * @param string $d         NIP-23 d tag.
	 */
	private function build_new_article_slug(
		string $title_tag,
		string $d,
		string $pubkey,
		string $event_id,
		string $address
	): string {
		$source = $title_tag !== '' ? $title_tag : $d;
		$base   = sanitize_title( $source );

		if ( $base === '' && $title_tag !== '' && $d !== '' ) {
			$base = sanitize_title( $d );
		}

		if ( $base === '' ) {
			$base = sanitize_title( substr( $pubkey, 0, 12 ) . '-' . substr( $event_id, 0, 8 ) );
		}

		if ( preg_match( '/^\d{6,}$/', $base ) ) {
			$tail = substr( $base, -min( 12, strlen( $base ) ) );
			$base = 'n-' . substr( $pubkey, 0, 8 ) . '-' . $tail;
		}

		$base = trim( $base, '-' );
		if ( $base === '' ) {
			$base = 'article-' . substr( hash( 'sha256', $address ), 0, 12 );
		}

		if ( strlen( $base ) > 180 ) {
			$base = rtrim( substr( $base, 0, 180 ), '-' );
		}

		return $base;
	}

	private function ensure_unique_slug( string $slug, string $pubkey, string $d ): string {
		$address = nostr_wp_blog_article_address( $pubkey, $d );
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded sync: one row by stable address meta.
		$own_id  = get_posts(
			array(
				'post_type'      => Nostr_WP_Blog_CPT::POST_TYPE,
				'post_status'    => 'any',
				'meta_key'       => self::META_ADDRESS,
				'meta_value'     => $address,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$base = $slug;
		$n    = 0;
		while ( $n < 1000 ) {
			$candidate = $n === 0 ? $base : $base . '-' . $n;
			$clash_ids = get_posts(
				array(
					'post_type'      => Nostr_WP_Blog_CPT::POST_TYPE,
					'post_status'    => 'any',
					'name'           => $candidate,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( $clash_ids === array() ) {
				return $candidate;
			}
			$clash_id = (int) $clash_ids[0];
			if ( $own_id !== array() && $clash_id === (int) $own_id[0] ) {
				return $candidate;
			}
			++$n;
		}

		return $base . '-' . wp_generate_password( 4, false, false );
	}

	/**
	 * @return array{local:string, gmt:string}
	 */
	private function resolve_post_dates( object $event, string $pub_at ): array {
		$ts = null;
		if ( $pub_at !== '' && ctype_digit( $pub_at ) ) {
			$ts = (int) $pub_at;
		}
		if ( $ts === null || $ts <= 0 ) {
			$ts = isset( $event->created_at ) ? (int) $event->created_at : time();
		}
		$gmt   = gmdate( 'Y-m-d H:i:s', $ts );
		$local = get_date_from_gmt( $gmt );
		return array(
			'gmt'   => $gmt,
			'local' => $local,
		);
	}

	/**
	 * @param array{imported:int, errors:list<string>} $result
	 */
	private function persist_sync_meta( array $result, bool $manual ): void {
		nostr_wp_blog_save_settings(
			array(
				'last_sync'        => gmdate( 'c' ),
				'last_sync_errors' => implode( "\n", $result['errors'] ),
				'last_sync_count'  => $result['imported'],
			)
		);
	}
}
