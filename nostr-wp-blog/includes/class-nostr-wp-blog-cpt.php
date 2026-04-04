<?php
/**
 * Custom post type and rewrite rules.
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers nostr_article and dynamic rewrites.
 */
final class Nostr_WP_Blog_CPT {

	public const POST_TYPE = 'nostr_article';

	public function register(): void {
		add_action( 'init', array( $this, 'do_register_post_type' ), 5 );
		add_action( 'init', array( $this, 'register_rewrite_rules' ), 20 );
		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 2 );
	}

	public function do_register_post_type(): void {
		$labels = array(
			'name'               => __( 'Nostr articles', 'nostr-wp-blog' ),
			'singular_name'      => __( 'Nostr article', 'nostr-wp-blog' ),
			'add_new'            => __( 'Add New', 'nostr-wp-blog' ),
			'add_new_item'       => __( 'Add New Article', 'nostr-wp-blog' ),
			'edit_item'          => __( 'Edit Article', 'nostr-wp-blog' ),
			'new_item'           => __( 'New Article', 'nostr-wp-blog' ),
			'view_item'          => __( 'View Article', 'nostr-wp-blog' ),
			'search_items'       => __( 'Search Articles', 'nostr-wp-blog' ),
			'not_found'          => __( 'No articles found', 'nostr-wp-blog' ),
			'not_found_in_trash' => __( 'No articles found in Trash', 'nostr-wp-blog' ),
			'menu_name'          => __( 'Nostr articles', 'nostr-wp-blog' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'query_var'           => true,
				'capability_type'     => 'post',
				'has_archive'         => true,
				'hierarchical'        => false,
				'show_in_rest'        => false,
				'rewrite'             => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
				'menu_icon'           => 'dashicons-media-text',
			)
		);
	}

	/**
	 * Pretty permalinks (rewrite is disabled on the CPT; we own rules).
	 */
	public function filter_post_type_link( string $permalink, \WP_Post $post ): string {
		if ( $post->post_type !== self::POST_TYPE ) {
			return $permalink;
		}
		$settings = nostr_wp_blog_get_settings();
		$archive  = isset( $settings['archive_slug'] ) ? sanitize_title( (string) $settings['archive_slug'] ) : 'blog';
		if ( $archive === '' ) {
			$archive = 'blog';
		}
		$mode = isset( $settings['single_url_mode'] ) ? (string) $settings['single_url_mode'] : 'prefixed';
		if ( $mode === 'root' ) {
			return home_url( user_trailingslashit( $post->post_name ) );
		}
		return home_url( user_trailingslashit( $archive . '/' . $post->post_name ) );
	}

	public function register_rewrite_rules(): void {
		$settings = nostr_wp_blog_get_settings();
		$archive  = isset( $settings['archive_slug'] ) ? sanitize_title( (string) $settings['archive_slug'] ) : 'blog';
		if ( $archive === '' ) {
			$archive = 'blog';
		}

		$archive_regex = preg_quote( $archive, '#' );

		add_rewrite_rule(
			'^' . $archive_regex . '/?$',
			'index.php?post_type=' . self::POST_TYPE,
			'top'
		);
		add_rewrite_rule(
			'^' . $archive_regex . '/page/([0-9]{1,9})/?$',
			'index.php?post_type=' . self::POST_TYPE . '&paged=$matches[1]',
			'top'
		);

		$mode = isset( $settings['single_url_mode'] ) ? (string) $settings['single_url_mode'] : 'prefixed';

		if ( $mode === 'root' ) {
			$post_ids = get_posts(
				array(
					'post_type'              => self::POST_TYPE,
					'post_status'            => 'publish',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $post_ids as $post_id ) {
				$slug = get_post_field( 'post_name', $post_id, 'raw' );
				if ( ! is_string( $slug ) || $slug === '' ) {
					continue;
				}
				$slug_regex = preg_quote( $slug, '#' );
				add_rewrite_rule(
					'^' . $slug_regex . '/?$',
					'index.php?post_type=' . self::POST_TYPE . '&name=' . $slug,
					'top'
				);
			}
		} else {
			add_rewrite_rule(
				'^' . $archive_regex . '/([^/]+)/?$',
				'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]',
				'top'
			);
		}
	}

	public static function flush_rewrite_rules(): void {
		delete_option( 'rewrite_rules' );
		flush_rewrite_rules( false );
	}
}
