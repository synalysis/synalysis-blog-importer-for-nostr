<?php
/**
 * Single Nostr article.
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

nostr_wp_blog_template_header();

while ( have_posts() ) {
	the_post();
	$post_id                 = get_the_ID();
	$nostr_wp_blog_img_raw   = get_post_meta( $post_id, Nostr_WP_Blog_Sync::META_IMAGE, true );
	$nostr_wp_blog_img       = is_string( $nostr_wp_blog_img_raw ) ? esc_url( $nostr_wp_blog_img_raw ) : '';
	$nostr_wp_blog_author    = nostr_wp_blog_get_article_author_display( $post_id );
	?>
	<main id="primary" class="site-main nostr-wp-blog nostr-wp-blog--single">
		<article <?php post_class( 'nostr-wp-blog-article' ); ?>>
			<header class="nostr-wp-blog-article__header">
				<h1 class="nostr-wp-blog-article__title"><?php the_title(); ?></h1>
				<div class="nostr-wp-blog-article__meta">
					<time class="nostr-wp-blog-article__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<?php if ( $nostr_wp_blog_author !== '' ) : ?>
						<span class="nostr-wp-blog-article__author"><?php
							/* translators: %s: author display name. */
							echo esc_html( sprintf( __( 'By %s', 'synalysis-blog-importer-for-nostr' ), $nostr_wp_blog_author ) );
						?></span>
					<?php endif; ?>
				</div>
			</header>
			<?php if ( $nostr_wp_blog_img !== '' ) : ?>
				<figure class="nostr-wp-blog-article__hero">
					<img src="<?php echo esc_url( $nostr_wp_blog_img ); ?>" alt="" width="1200" height="630" />
				</figure>
			<?php endif; ?>
			<?php if ( has_excerpt() ) : ?>
				<p class="nostr-wp-blog-article__summary"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
			<?php endif; ?>
			<div class="nostr-wp-blog-article__content entry-content">
				<?php the_content(); ?>
			</div>
			<?php
			$nostr_wp_blog_tpl_settings = nostr_wp_blog_get_settings();
			$nostr_wp_blog_show_tags    = ! isset( $nostr_wp_blog_tpl_settings['show_article_tags'] ) || $nostr_wp_blog_tpl_settings['show_article_tags'];
			if ( $nostr_wp_blog_show_tags ) {
				$nostr_wp_blog_topic_tags = nostr_wp_blog_get_article_topic_tags( $post_id );
				if ( $nostr_wp_blog_topic_tags !== array() ) {
					?>
					<footer class="nostr-wp-blog-article__tags" aria-label="<?php esc_attr_e( 'Tags', 'synalysis-blog-importer-for-nostr' ); ?>">
						<span class="nostr-wp-blog-article__tags-label"><?php esc_html_e( 'Tags', 'synalysis-blog-importer-for-nostr' ); ?></span>
						<ul class="nostr-wp-blog-tag-list">
							<?php foreach ( $nostr_wp_blog_topic_tags as $tag ) : ?>
								<li class="nostr-wp-blog-tag-list__item"><span class="nostr-wp-blog-tag"><?php echo esc_html( $tag ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</footer>
					<?php
				}
			}
			?>
		</article>
	</main>
	<?php
}

nostr_wp_blog_template_footer();
