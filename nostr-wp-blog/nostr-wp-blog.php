<?php
/**
 * Plugin Name:       Nostr WP Blog
 * Plugin URI:        https://github.com/synalysis/nostr-wp-blog
 * Description:       Sync NIP-23 long-form articles from Nostr relays into WordPress with archive, single templates, and SEO metadata.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Synalysis
 * Author URI:        https://github.com/synalysis
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       nostr-wp-blog
 * Domain Path:       /languages
 *
 * @package NostrWpBlog
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NOSTR_WP_BLOG_FILE', __FILE__ );
define( 'NOSTR_WP_BLOG_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOSTR_WP_BLOG_URL', plugin_dir_url( __FILE__ ) );
define( 'NOSTR_WP_BLOG_VERSION', '1.0.0' );

/**
 * Load plugin translations.
 */
function nostr_wp_blog_load_textdomain(): void {
	load_plugin_textdomain(
		'nostr-wp-blog',
		false,
		dirname( plugin_basename( NOSTR_WP_BLOG_FILE ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'nostr_wp_blog_load_textdomain' );

if ( ! is_readable( NOSTR_WP_BLOG_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Nostr WP Blog: run Composer in the plugin directory (composer install) so vendor/autoload.php exists.', 'nostr-wp-blog' );
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
