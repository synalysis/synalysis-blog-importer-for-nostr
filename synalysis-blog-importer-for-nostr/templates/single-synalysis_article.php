<?php
/**
 * Single Nostr article.
 *
 * @package SynalysisBlogImporterForNostr
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

synalysis_blog_importer_template_header();

while ( have_posts() ) {
	the_post();
	$post_id                 = get_the_ID();
	$synalysis_blog_importer_img_raw   = get_post_meta( $post_id, Synalysis_Blog_Importer_Sync::META_IMAGE, true );
	$synalysis_blog_importer_img       = is_string( $synalysis_blog_importer_img_raw ) ? esc_url( $synalysis_blog_importer_img_raw ) : '';
	$synalysis_blog_importer_author    = synalysis_blog_importer_get_article_author_display( $post_id );
	?>
	<main id="primary" class="site-main synalysis-blog-importer synalysis-blog-importer--single">
		<article <?php post_class( 'synalysis-blog-importer-article' ); ?>>
			<header class="synalysis-blog-importer-article__header">
				<h1 class="synalysis-blog-importer-article__title"><?php the_title(); ?></h1>
				<div class="synalysis-blog-importer-article__meta">
					<time class="synalysis-blog-importer-article__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<?php if ( $synalysis_blog_importer_author !== '' ) : ?>
						<span class="synalysis-blog-importer-article__author"><?php
							/* translators: %s: author display name. */
							echo esc_html( sprintf( __( 'By %s', 'synalysis-blog-importer-for-nostr' ), $synalysis_blog_importer_author ) );
						?></span>
					<?php endif; ?>
				</div>
			</header>
			<?php if ( $synalysis_blog_importer_img !== '' ) : ?>
				<figure class="synalysis-blog-importer-article__hero">
					<img src="<?php echo esc_url( $synalysis_blog_importer_img ); ?>" alt="" width="1200" height="630" />
				</figure>
			<?php endif; ?>
			<?php if ( has_excerpt() ) : ?>
				<p class="synalysis-blog-importer-article__summary"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
			<?php endif; ?>
			<div class="synalysis-blog-importer-article__content entry-content">
				<?php the_content(); ?>
			</div>
			<?php
			$synalysis_blog_importer_tpl_settings = synalysis_blog_importer_get_settings();
			$synalysis_blog_importer_show_tags    = ! isset( $synalysis_blog_importer_tpl_settings['show_article_tags'] ) || $synalysis_blog_importer_tpl_settings['show_article_tags'];
			if ( $synalysis_blog_importer_show_tags ) {
				$synalysis_blog_importer_topic_tags = synalysis_blog_importer_get_article_topic_tags( $post_id );
				if ( $synalysis_blog_importer_topic_tags !== array() ) {
					?>
					<footer class="synalysis-blog-importer-article__tags" aria-label="<?php esc_attr_e( 'Tags', 'synalysis-blog-importer-for-nostr' ); ?>">
						<span class="synalysis-blog-importer-article__tags-label"><?php esc_html_e( 'Tags', 'synalysis-blog-importer-for-nostr' ); ?></span>
						<ul class="synalysis-blog-importer-tag-list">
							<?php foreach ( $synalysis_blog_importer_topic_tags as $tag ) : ?>
								<li class="synalysis-blog-importer-tag-list__item"><span class="synalysis-blog-importer-tag"><?php echo esc_html( $tag ); ?></span></li>
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

synalysis_blog_importer_template_footer();
