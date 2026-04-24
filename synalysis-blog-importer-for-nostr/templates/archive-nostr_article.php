<?php
/**
 * Archive template for Nostr articles.
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

nostr_wp_blog_template_header();
?>
<main id="primary" class="site-main nostr-wp-blog nostr-wp-blog--archive">
	<header class="nostr-wp-blog-archive-header">
		<?php
		$nostr_wp_blog_archive_settings = nostr_wp_blog_get_settings();
		$nostr_wp_blog_list_title       = isset( $nostr_wp_blog_archive_settings['archive_list_title'] ) ? trim( (string) $nostr_wp_blog_archive_settings['archive_list_title'] ) : '';
		if ( $nostr_wp_blog_list_title !== '' ) {
			echo '<h1 class="nostr-wp-blog-archive-title">' . esc_html( $nostr_wp_blog_list_title ) . '</h1>';
		} else {
			the_archive_title( '<h1 class="nostr-wp-blog-archive-title">', '</h1>' );
		}
		?>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="nostr-wp-blog-archive-grid">
			<?php
			while ( have_posts() ) {
				the_post();
				nostr_wp_blog_render_card();
			}
			?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No articles yet. Run a sync from Settings → Synalysis Blog Importer.', 'synalysis-blog-importer-for-nostr' ); ?></p>
	<?php endif; ?>
</main>
<?php
nostr_wp_blog_template_footer();
