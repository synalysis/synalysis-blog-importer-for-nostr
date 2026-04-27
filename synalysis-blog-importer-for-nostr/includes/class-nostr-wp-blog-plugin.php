<?php
/**
 * Loads components and front assets.
 *
 * @package SynalysisBlogImporterForNostr
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Synalysis_Blog_Importer_Plugin {

	private Synalysis_Blog_Importer_CPT      $cpt;
	private Synalysis_Blog_Importer_Sync     $sync;
	private Synalysis_Blog_Importer_SEO      $seo;
	private Synalysis_Blog_Importer_Settings $settings;
	private Synalysis_Blog_Importer_Nip05   $nip05;

	public function __construct() {
		$this->cpt      = new Synalysis_Blog_Importer_CPT();
		$this->sync     = new Synalysis_Blog_Importer_Sync();
		$this->seo      = new Synalysis_Blog_Importer_SEO();
		$this->settings = new Synalysis_Blog_Importer_Settings();
		$this->nip05    = new Synalysis_Blog_Importer_Nip05();
	}

	public function run(): void {
		$this->cpt->register();
		$this->sync->register();
		$this->seo->register();
		$this->settings->register();
		$this->nip05->register();

		add_filter( 'template_include', array( $this, 'template_include' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'kses_allowed_protocols', array( $this, 'kses_protocols' ) );
		add_shortcode( 'synalysis_blog_importer', array( $this, 'shortcode_list' ) );
		add_action( 'wp', array( $this, 'maybe_strip_wpautop' ) );
	}

	public function maybe_strip_wpautop(): void {
		if ( is_singular( Synalysis_Blog_Importer_CPT::POST_TYPE ) ) {
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
		if ( ! is_post_type_archive( Synalysis_Blog_Importer_CPT::POST_TYPE ) && ! is_singular( Synalysis_Blog_Importer_CPT::POST_TYPE ) ) {
			$shortcode = false;
			if ( is_singular() ) {
				$post = get_post();
				if ( $post && has_shortcode( (string) $post->post_content, 'synalysis_blog_importer' ) ) {
					$shortcode = true;
				}
			}
			if ( ! $shortcode ) {
				return;
			}
		}

		wp_enqueue_style(
			'synalysis-blog-importer-for-nostr',
			SYNALYSIS_BLOG_IMPORTER_URL . 'assets/css/front.css',
			array(),
			SYNALYSIS_BLOG_IMPORTER_VERSION
		);
	}

	public function template_include( string $template ): string {
		if ( is_post_type_archive( Synalysis_Blog_Importer_CPT::POST_TYPE ) ) {
			$plugin_tpl = SYNALYSIS_BLOG_IMPORTER_DIR . 'templates/archive-synalysis_article.php';
			if ( is_readable( $plugin_tpl ) ) {
				return $plugin_tpl;
			}
		}
		if ( is_singular( Synalysis_Blog_Importer_CPT::POST_TYPE ) ) {
			$plugin_tpl = SYNALYSIS_BLOG_IMPORTER_DIR . 'templates/single-synalysis_article.php';
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
			'synalysis_blog_importer'
		);

		$q = synalysis_blog_importer_article_query(
			array(
				'posts_per_page' => max( 1, (int) $atts['posts_per_page'] ),
			)
		);

		ob_start();
		if ( $q->have_posts() ) {
			echo '<div class="synalysis-blog-importer synalysis-blog-importer--shortcode">';
			while ( $q->have_posts() ) {
				$q->the_post();
				synalysis_blog_importer_render_card();
			}
			echo '</div>';
			wp_reset_postdata();
		}

		return (string) ob_get_clean();
	}
}
