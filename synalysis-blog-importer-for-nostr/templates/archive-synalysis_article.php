<?php
/**
 * Archive template for Nostr articles.
 *
 * @package SynalysisBlogImporterForNostr
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

synalysis_blog_importer_template_header();
?>
<main id="primary" class="site-main synalysis-blog-importer synalysis-blog-importer--archive">
	<header class="synalysis-blog-importer-archive-header">
		<?php
		$synalysis_blog_importer_archive_settings = synalysis_blog_importer_get_settings();
		$synalysis_blog_importer_list_title       = isset( $synalysis_blog_importer_archive_settings['archive_list_title'] ) ? trim( (string) $synalysis_blog_importer_archive_settings['archive_list_title'] ) : '';
		if ( $synalysis_blog_importer_list_title !== '' ) {
			echo '<h1 class="synalysis-blog-importer-archive-title">' . esc_html( $synalysis_blog_importer_list_title ) . '</h1>';
		} else {
			the_archive_title( '<h1 class="synalysis-blog-importer-archive-title">', '</h1>' );
		}
		?>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="synalysis-blog-importer-archive-grid">
			<?php
			while ( have_posts() ) {
				the_post();
				synalysis_blog_importer_render_card();
			}
			?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No articles yet. Run a sync from Settings → Synalysis Blog Importer.', 'synalysis-blog-importer-for-nostr' ); ?></p>
	<?php endif; ?>
</main>
<?php
synalysis_blog_importer_template_footer();
