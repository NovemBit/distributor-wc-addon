<?php
/**
 * Plugin Name:       Distributor { Add-on name } Add-on
 * Description:       { Add-on description }
 * Version:           1.0.0
 * Author:            Novembit
 * Author URI:        https://novembit.com
 * License:           GPLv3 or later
 * Domain Path:       /lang/
 * GitHub Plugin URI: { Add-on repository URI }
 * Text Domain:       distributor-{ Add-on prefix }
 *
 * @package distributor-{ Add-on slug }
 */

/**
 * Bootstrap function
 */
function dt_{ Add - on strictslug }_add_on_bootstrap() {
	if ( ! function_exists( '\Distributor\ExternalConnectionCPT\setup' ) ) {
		if ( is_admin() ) {
			add_action(
				'admin_notices',
				function() {
					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-error' ), esc_html( 'You need to have Distributor plug-in activated to run the {Add-on name}.', 'distributor-acf' ) );
				}
			);
		}
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'manager.php';
}

add_action( 'plugins_loaded', 'dt_{ Add-on strictslug }_add_on_bootstrap' );
