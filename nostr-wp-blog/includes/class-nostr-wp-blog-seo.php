<?php
/**
 * Title, meta description, Open Graph, Twitter, JSON-LD.
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Nostr_WP_Blog_SEO {

	public function register(): void {
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ), 20, 1 );
		add_action( 'wp_head', array( $this, 'output_head' ), 2 );

		add_filter( 'wpseo_title', array( $this, 'yoast_title' ), 20 );
		add_filter( 'wpseo_metadesc', array( $this, 'yoast_metadesc' ), 20 );
		add_filter( 'wpseo_opengraph_title', array( $this, 'yoast_og_title' ), 20 );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'yoast_og_desc' ), 20 );
		add_filter( 'wpseo_opengraph_url', array( $this, 'yoast_og_url' ), 20 );
		add_filter( 'wpseo_opengraph_image', array( $this, 'yoast_og_image' ), 20 );

		add_filter( 'rank_math/frontend/title', array( $this, 'rankmath_title' ), 20 );
		add_filter( 'rank_math/frontend/description', array( $this, 'rankmath_desc' ), 20 );
		add_filter( 'rank_math/opengraph/facebook/image', array( $this, 'rankmath_og_image' ), 20 );

		add_filter( 'wp_sitemaps_post_types', array( $this, 'sitemap_post_types' ) );
	}

	/**
	 * @param list<string> $post_types
	 * @return list<string>
	 */
	public function sitemap_post_types( array $post_types ): array {
		if ( ! in_array( Nostr_WP_Blog_CPT::POST_TYPE, $post_types, true ) ) {
			$post_types[] = Nostr_WP_Blog_CPT::POST_TYPE;
		}
		return $post_types;
	}

	public function filter_document_title( string $title ): string {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $title;
		}
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return $title;
		}
		$t = get_the_title();
		return $t !== '' ? $t : $title;
	}

	/**
	 * @param array<string, string> $parts
	 * @return array<string, string>
	 */
	public function filter_document_title_parts( array $parts ): array {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $parts;
		}
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return $parts;
		}
		$t = get_the_title();
		if ( $t !== '' ) {
			$parts['title'] = $t;
		}
		return $parts;
	}

	public function yoast_title( string $title ): string {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $title;
		}
		$t = get_the_title();
		return $t !== '' ? $t : $title;
	}

	public function yoast_metadesc( string $desc ): string {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $desc;
		}
		$d = $this->get_description();
		return $d !== '' ? $d : $desc;
	}

	public function yoast_og_title( string $title ): string {
		return $this->yoast_title( $title );
	}

	public function yoast_og_desc( string $desc ): string {
		return $this->yoast_metadesc( $desc );
	}

	public function yoast_og_url( string $url ): string {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $url;
		}
		return get_permalink() ?: $url;
	}

	public function yoast_og_image( string $image ): string {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $image;
		}
		$u = $this->get_image_url();
		return $u !== '' ? $u : $image;
	}

	public function rankmath_title( string $title ): string {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $title;
		}
		$t = get_the_title();
		return $t !== '' ? $t : $title;
	}

	public function rankmath_desc( string $desc ): string {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $desc;
		}
		$d = $this->get_description();
		return $d !== '' ? $d : $desc;
	}

	public function rankmath_og_image( string $image ): string {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return $image;
		}
		$u = $this->get_image_url();
		return $u !== '' ? $u : $image;
	}

	public function output_head(): void {
		if ( ! is_singular( Nostr_WP_Blog_CPT::POST_TYPE ) ) {
			return;
		}

		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}

		$title = get_the_title();
		$desc  = $this->get_description();
		$url   = get_permalink();
		$img   = $this->get_image_url();

		if ( $desc !== '' ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . "\" />\n";
		}

		if ( $url ) {
			echo '<link rel="canonical" href="' . esc_url( $url ) . "\" />\n";
		}

		if ( $title !== '' ) {
			echo '<meta property="og:title" content="' . esc_attr( $title ) . "\" />\n";
		}
		if ( $desc !== '' ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . "\" />\n";
		}
		if ( $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . "\" />\n";
		}
		echo '<meta property="og:type" content="article" />' . "\n";
		if ( $img !== '' ) {
			echo '<meta property="og:image" content="' . esc_url( $img ) . "\" />\n";
		}

		if ( $img !== '' ) {
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $title ) . "\" />\n";
			if ( $desc !== '' ) {
				echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . "\" />\n";
			}
			echo '<meta name="twitter:image" content="' . esc_url( $img ) . "\" />\n";
		} else {
			echo '<meta name="twitter:card" content="summary" />' . "\n";
			echo '<meta name="twitter:title" content="' . esc_attr( $title ) . "\" />\n";
			if ( $desc !== '' ) {
				echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . "\" />\n";
			}
		}

		$this->output_json_ld();
	}

	private function output_json_ld(): void {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || $post->post_type !== Nostr_WP_Blog_CPT::POST_TYPE ) {
			return;
		}

		$published = get_post_meta( $post->ID, Nostr_WP_Blog_Sync::META_PUBLISHED_AT, true );
		$created   = get_post_meta( $post->ID, Nostr_WP_Blog_Sync::META_EVENT_CREATED, true );
		$pub_ts    = ( is_string( $published ) && ctype_digit( $published ) ) ? (int) $published : null;
		if ( $pub_ts === null || $pub_ts <= 0 ) {
			$pub_ts = strtotime( $post->post_date_gmt . ' UTC' );
		}
		$mod_ts = is_string( $created ) && ctype_digit( $created ) ? (int) $created : strtotime( $post->post_modified_gmt . ' UTC' );

		$img = $this->get_image_url();
		$url = get_permalink( $post );

		$data = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BlogPosting',
			'headline'         => get_the_title( $post ),
			'description'      => $this->get_description_for_post( $post ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $url,
			),
		);

		if ( $pub_ts > 0 ) {
			$data['datePublished'] = gmdate( 'c', $pub_ts );
		}
		if ( $mod_ts > 0 ) {
			$data['dateModified'] = gmdate( 'c', $mod_ts );
		}

		if ( $img !== '' ) {
			$data['image'] = array( $img );
		}

		$pubkey = get_post_meta( $post->ID, Nostr_WP_Blog_Sync::META_PUBKEY, true );
		if ( is_string( $pubkey ) && $pubkey !== '' ) {
			$data['author'] = array(
				'@type' => 'Person',
				'name'  => substr( $pubkey, 0, 16 ) . '…',
			);
		}

		$json = wp_json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		if ( $json ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD from wp_json_encode() with JSON_HEX_* (safe in HTML).
			printf( "<script type=\"application/ld+json\">%s</script>\n", $json );
		}
	}

	private function get_description(): string {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		return $this->get_description_for_post( $post );
	}

	private function get_description_for_post( \WP_Post $post ): string {
		if ( $post->post_excerpt !== '' ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '…' );
	}

	private function get_image_url(): string {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		$u = get_post_meta( $post->ID, Nostr_WP_Blog_Sync::META_IMAGE, true );
		return is_string( $u ) ? esc_url_raw( $u ) : '';
	}

}
