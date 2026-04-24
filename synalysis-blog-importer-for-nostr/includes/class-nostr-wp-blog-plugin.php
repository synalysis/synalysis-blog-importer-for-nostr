<?php
/**
 * Loads components and front assets.
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nostr_WP_Blog_Plugin {

	private Nostr_WP_Blog_CPT      $cpt;
	private Nostr_WP_Blog_Sync     $sync;
	private Nostr_WP_Blog_SEO      $seo;
	private Nostr_WP_Blog_Settings $settings;
	private Nostr_WP_Blog_Nip05   $nip05;
	private Nostr_WP_Blog_Login   $login;

	public function __construct() {
		$this->cpt      = new Nostr_WP_Blog_CPT();
		$this->sync     = new Nostr_WP_Blog_Sync();
		$this->seo      = new Nostr_WP_Blog_SEO();
		$this->settings = new Nostr_WP_Blog_Settings();
		$this->nip05    = new Nostr_WP_Blog_Nip05();
		$this->login    = new Nostr_WP_Blog_Login();
	}

	public function run(): void {
		$this->cpt->register();
		$this->sync->register();
		$this->seo->register();
		$this->settings->register();
		$this->nip05->register();
		$this->login->register();

		add_filter( 'template_include', array( $this, 'template_include' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'kses_allowed_protocols', array( $this, 'kses_protocols' ) );
		add_shortcode( 'nostr_wp_blog', array( $this, 'shortcode_list' ) );
		add_action( 'wp', array( $this, 'maybe_strip_wpautop' ) );
	}

	public function maybe_strip_wpautop(): void {
		if ( is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			remove_filter( 'the_content', 'wpautop' );
		}
	}

	/**
	 * @param list<string> $protocols
	 * @return list<string>
	 */
	public function kses_protocols( array $protocols ): array {
		$protocols[] = 'nostr';
		return $protocols;
	}

	public function enqueue_assets(): void {
		if ( ! is_post_type_archive( Nostr_WP_Blog_CPT::POST_TYPE ) && ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			$shortcode = false;
			if ( is_singular() ) {
				$post = get_post();
				if ( $post && has_shortcode( (string) $post->post_content, 'nostr_wp_blog' ) ) {
					$shortcode = true;
				}
			}
			if ( ! $shortcode ) {
				return;
			}
		}

		wp_enqueue_style(
			'synalysis-blog-importer-for-nostr',
			NOSTR_WP_BLOG_URL . 'assets/css/front.css',
			array(),
			NOSTR_WP_BLOG_VERSION
		);
	}

	public function template_include( string $template ): string {
		if ( is_post_type_archive( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			$plugin_tpl = NOSTR_WP_BLOG_DIR . 'templates/archive-nostr_article.php';
			if ( is_readable( $plugin_tpl ) ) {
				return $plugin_tpl;
			}
		}
		if ( is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			$plugin_tpl = NOSTR_WP_BLOG_DIR . 'templates/single-nostr_article.php';
			if ( is_readable( $plugin_tpl ) ) {
				return $plugin_tpl;
			}
		}
		return $template;
	}

	/**
	 * @param array<string, string> $atts
	 */
	public function shortcode_list( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'posts_per_page' => (string) get_option( 'posts_per_page', 10 ),
			),
			$atts,
			'nostr_wp_blog'
		);

		$q = nostr_wp_blog_article_query(
			array(
				'posts_per_page' => max( 1, (int) $atts['posts_per_page'] ),
			)
		);

		ob_start();
		if ( $q->have_posts() ) {
			echo '<div class="nostr-wp-blog nostr-wp-blog--shortcode">';
			while ( $q->have_posts() ) {
				$q->the_post();
				nostr_wp_blog_render_card();
			}
			echo '</div>';
			wp_reset_postdata();
		}

		return (string) ob_get_clean();
	}
}
