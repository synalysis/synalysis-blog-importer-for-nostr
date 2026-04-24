<?php
/**
 * Plugin Name:       Synalysis Blog Importer for Nostr
 * Plugin URI:        https://github.com/synalysis/nostr-wp-blog
 * Description:       Imports NIP-23 long-form articles from Nostr relays into WordPress with archive and single templates, optional NIP-05, and optional extension sign-in (not affiliated with the Nostr protocol or WordPress).
 * Version:           1.1.3
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Synalysis
 * Author URI:        https://github.com/synalysis
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       synalysis-blog-importer-for-nostr
 * Domain Path:       /languages
 *
 * @package SynalysisBlogImporterForNostr
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NOSTR_WP_BLOG_FILE', __FILE__ );
define( 'NOSTR_WP_BLOG_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOSTR_WP_BLOG_URL', plugin_dir_url( __FILE__ ) );
define( 'NOSTR_WP_BLOG_VERSION', '1.1.3' );

if ( ! is_readable( NOSTR_WP_BLOG_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			// Limit to Plugins screen (Guideline 11): avoid sitewide dashboard banners for a dev-only state.
			if ( ! $screen || $screen->id !== 'plugins' ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Synalysis Blog Importer for Nostr: run Composer in the plugin directory (composer install) so vendor/autoload.php exists.', 'synalysis-blog-importer-for-nostr' );
			echo '</p></div>';
		}
	);
	return;
}

require_once NOSTR_WP_BLOG_DIR . 'vendor/autoload.php';
require_once NOSTR_WP_BLOG_DIR . 'includes/functions.php';

/**
 * Plugin activation.
 */
function nostr_wp_blog_activate(): void {
	if ( ! is_readable( NOSTR_WP_BLOG_DIR . 'vendor/autoload.php' ) ) {
		return;
	}
	require_once NOSTR_WP_BLOG_DIR . 'vendor/autoload.php';
	require_once NOSTR_WP_BLOG_DIR . 'includes/functions.php';

	$cpt = new Nostr_WP_Blog_CPT();
	$cpt->do_register_post_type();
	$cpt->register_rewrite_rules();
	Nostr_WP_Blog_Nip05::register_rewrite_rules();
	flush_rewrite_rules( false );
	nostr_wp_blog_schedule_cron();
}

/**
 * Plugin deactivation.
 */
function nostr_wp_blog_deactivate(): void {
	wp_clear_scheduled_hook( 'nostr_wp_blog_sync_cron' );
	flush_rewrite_rules( false );
}

register_activation_hook( NOSTR_WP_BLOG_FILE, 'nostr_wp_blog_activate' );
register_deactivation_hook( NOSTR_WP_BLOG_FILE, 'nostr_wp_blog_deactivate' );

$nostr_wp_blog_plugin = new Nostr_WP_Blog_Plugin();
$nostr_wp_blog_plugin->run();
