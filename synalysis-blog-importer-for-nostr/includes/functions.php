<?php
/**
 * Helpers for Synalysis Blog Importer for Nostr.
 *
 * @package SynalysisBlogImporterForNostr
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings with defaults.
 *
 * @return array<string, mixed>
 */
function synalysis_blog_importer_get_settings(): array {
	$defaults = array(
		'authors'            => '',
		'relays'             => "wss://relay.damus.io\nwss://nos.lol\nwss://relay.nostr.band",
		'archive_slug'       => 'blog',
		'archive_list_title' => '',
		'show_article_tags'  => true,
		'nip05_enabled'        => false,
		'nip05_identity_map'   => array(),
		'single_url_mode'      => 'prefixed',
		'sync_interval'      => 'hourly',
		'last_sync'          => '',
		'last_sync_errors'   => '',
		'last_sync_count'    => 0,
		'request_timeout'    => 45,
		'event_limit'        => 500,
	);

	$saved = get_option( 'synalysis_blog_importer_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return array_merge( $defaults, $saved );
}

/**
 * @param array<string, mixed> $settings Partial settings.
 */
function synalysis_blog_importer_save_settings( array $settings ): void {
	$current = synalysis_blog_importer_get_settings();
	update_option( 'synalysis_blog_importer_settings', array_merge( $current, $settings ), true );
}

/**
 * @return list<string>
 */
function synalysis_blog_importer_parse_lines( string $text ): array {
	$lines = preg_split( '/\r\n|\r|\n/', $text ) ?: array();
	$out   = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line !== '' ) {
			$out[] = $line;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Whether to use the theme's get_header() / get_footer().
 *
 * Block themes must use a minimal shell: they have no header.php/footer.php, and
 * locate_template() can behave inconsistently across WP versions. Classic themes
 * need both template files or core emits a deprecation when loading headers.
 */
function synalysis_blog_importer_should_use_theme_wrappers(): bool {
	if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
		return false;
	}
	$header = locate_template( 'header.php', false, false );
	$footer = locate_template( 'footer.php', false, false );
	return is_string( $header ) && $header !== '' && is_string( $footer ) && $footer !== '';
}

/**
 * Open front template: theme wrappers or a minimal document (block themes).
 */
function synalysis_blog_importer_template_header(): void {
	if ( synalysis_blog_importer_should_use_theme_wrappers() ) {
		get_header();
		return;
	}
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'synalysis-blog-importer-for-nostr' ); ?>>
	<?php
	wp_body_open();
}

/**
 * Close front template.
 */
function synalysis_blog_importer_template_footer(): void {
	if ( synalysis_blog_importer_should_use_theme_wrappers() ) {
		get_footer();
		return;
	}
	wp_footer();
	echo '</body></html>';
}

/**
 * Stable address for a NIP-23 replaceable article.
 */
function synalysis_blog_importer_article_address( string $pubkey_hex, string $d_tag ): string {
	return '30023:' . strtolower( $pubkey_hex ) . ':' . $d_tag;
}

/**
 * @param object $event Relay event object (stdClass).
 */
function synalysis_blog_importer_event_tag( object $event, string $name ): string {
	if ( empty( $event->tags ) || ! is_array( $event->tags ) ) {
		return '';
	}
	foreach ( $event->tags as $tag ) {
		if ( ! is_array( $tag ) || ! isset( $tag[0] ) || $tag[0] !== $name ) {
			continue;
		}
		if ( isset( $tag[1] ) && is_string( $tag[1] ) ) {
			return $tag[1];
		}
	}
	return '';
}

/**
 * All values for a tag name (e.g. "t" topics).
 *
 * @return list<string>
 */
function synalysis_blog_importer_event_tags_named( object $event, string $name ): array {
	$vals = array();
	if ( empty( $event->tags ) || ! is_array( $event->tags ) ) {
		return $vals;
	}
	foreach ( $event->tags as $tag ) {
		if ( is_array( $tag ) && isset( $tag[0], $tag[1] ) && $tag[0] === $name && is_string( $tag[1] ) ) {
			$vals[] = $tag[1];
		}
	}
	return $vals;
}

/**
 * Normalize npub or 64-char hex to lowercase hex pubkey, or null if invalid.
 */
/**
 * Unique author pubkeys from settings (hex), preserving order.
 *
 * @return list<string>
 */
function synalysis_blog_importer_get_author_pubkey_hexes(): array {
	$lines = synalysis_blog_importer_parse_lines( (string) synalysis_blog_importer_get_settings()['authors'] );
	$out   = array();
	$seen  = array();
	foreach ( $lines as $line ) {
		$hex = synalysis_blog_importer_normalize_pubkey_hex( $line );
		if ( $hex === null || isset( $seen[ $hex ] ) ) {
			continue;
		}
		$seen[ $hex ] = true;
		$out[]        = $hex;
	}
	return $out;
}

function synalysis_blog_importer_normalize_pubkey_hex( string $key ): ?string {
	$key = trim( $key );
	if ( $key === '' ) {
		return null;
	}
	if ( str_starts_with( $key, 'npub' ) ) {
		try {
			$k   = new \swentel\nostr\Key\Key();
			$hex = $k->convertToHex( $key );
			return strtolower( $hex );
		} catch ( \Throwable ) {
			return null;
		}
	}
	$hex = strtolower( ltrim( $key, '0x' ) );
	if ( strlen( $hex ) === 64 && ctype_xdigit( $hex ) ) {
		return $hex;
	}
	return null;
}

/**
 * NIP-05 local-name => hex pubkey (rebuilt when settings are saved from Authors + kind 0 profiles).
 *
 * @return array<string, string>
 */
function synalysis_blog_importer_get_nip05_name_map(): array {
	$m = synalysis_blog_importer_get_settings()['nip05_identity_map'] ?? array();
	if ( ! is_array( $m ) ) {
		return array();
	}
	$out = array();
	foreach ( $m as $name => $hex ) {
		if ( ! is_string( $name ) || ! is_string( $hex ) ) {
			continue;
		}
		$name = strtolower( $name );
		$hex  = strtolower( $hex );
		if ( $name !== '' && strlen( $hex ) === 64 && ctype_xdigit( $hex ) ) {
			$out[ $name ] = $hex;
		}
	}
	return $out;
}

/**
 * Sanitize a Nostr profile "name" (or similar) into a NIP-05 local part.
 */
function synalysis_blog_importer_sanitize_nip05_local_name( string $raw ): string {
	$s = strtolower( trim( wp_strip_all_tags( $raw ) ) );
	$s = preg_replace( '/\s+/', '-', $s );
	$s = is_string( $s ) ? preg_replace( '/[^a-z0-9_.-]+/', '', $s ) : '';
	$s = is_string( $s ) ? trim( $s, '.-' ) : '';
	if ( $s === '' ) {
		return '';
	}
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $s ) > 60 ) {
		$s = mb_substr( $s, 0, 60 );
		$s = rtrim( $s, '-' );
	} elseif ( strlen( $s ) > 60 ) {
		$s = substr( $s, 0, 60 );
		$s = rtrim( $s, '-' );
	}
	return $s;
}

/**
 * (Re)schedule background sync.
 */
function synalysis_blog_importer_schedule_cron(): void {
	$hook = Synalysis_Blog_Importer_Sync::CRON_HOOK;
	wp_clear_scheduled_hook( $hook );

	$settings = synalysis_blog_importer_get_settings();
	$interval = isset( $settings['sync_interval'] ) ? (string) $settings['sync_interval'] : 'hourly';
	$allowed  = array( 'synalysis_blog_importer_quarterhourly', 'hourly', 'twicedaily', 'daily' );
	if ( ! in_array( $interval, $allowed, true ) ) {
		$interval = 'hourly';
	}

	wp_schedule_event( time() + 60, $interval, $hook );
}

/**
 * @param array<string, mixed> $args WP_Query args overrides.
 */
function synalysis_blog_importer_article_query( array $args = array() ): \WP_Query {
	$defaults = array(
		'post_type'      => Synalysis_Blog_Importer_CPT::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => get_option( 'posts_per_page', 10 ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	return new \WP_Query( array_merge( $defaults, $args ) );
}

/**
 * Cached display name from kind 0 metadata (sync), for single article header.
 */
function synalysis_blog_importer_get_article_author_display( int $post_id ): string {
	$v = get_post_meta( $post_id, Synalysis_Blog_Importer_Sync::META_AUTHOR_DISPLAY, true );
	if ( ! is_string( $v ) || $v === '' ) {
		return '';
	}
	return sanitize_text_field( $v );
}

/**
 * Topic/hashtag tags from the Nostr event (`t` tags), for display under the article.
 *
 * @return list<string>
 */
function synalysis_blog_importer_get_article_topic_tags( int $post_id ): array {
	$raw = get_post_meta( $post_id, Synalysis_Blog_Importer_Sync::META_TOPIC_TAGS, true );
	if ( ! is_string( $raw ) || $raw === '' ) {
		return array();
	}
	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		return array();
	}
	$out = array();
	foreach ( $decoded as $item ) {
		if ( is_string( $item ) ) {
			$t = sanitize_text_field( $item );
			if ( $t !== '' && ! in_array( $t, $out, true ) ) {
				$out[] = $t;
			}
		}
	}
	return $out;
}

/**
 * Card markup for archive / shortcode (loop context).
 */
function synalysis_blog_importer_render_card(): void {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return;
	}

	$img = get_post_meta( $post_id, Synalysis_Blog_Importer_Sync::META_IMAGE, true );
	$img = is_string( $img ) ? esc_url( $img ) : '';
	?>
	<article <?php post_class( 'synalysis-blog-importer-card' ); ?>>
		<?php if ( $img !== '' ) : ?>
			<a class="synalysis-blog-importer-card__media" href="<?php the_permalink(); ?>">
				<img src="<?php echo esc_url( $img ); ?>" alt="" loading="lazy" width="640" height="360" />
			</a>
		<?php endif; ?>
		<div class="synalysis-blog-importer-card__body">
			<h2 class="synalysis-blog-importer-card__title">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</h2>
			<time class="synalysis-blog-importer-card__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			<?php if ( has_excerpt() ) : ?>
				<p class="synalysis-blog-importer-card__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
			<?php endif; ?>
			<a class="synalysis-blog-importer-card__read" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read article', 'synalysis-blog-importer-for-nostr' ); ?></a>
		</div>
	</article>
	<?php
}
