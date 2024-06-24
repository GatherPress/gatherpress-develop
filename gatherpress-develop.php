<?php
/**
 * Plugin Name:  GatherPress Develop
 * Plugin URI:   https://gatherpress.org/
 * Description:  Powering Communities with WordPress.
 * Author:       The GatherPress Community
 * Author URI:   https://gatherpress.org/
 * Version:      1.0.0
 * Requires PHP: 7.4
 * Text Domain:  gatherpress-develop
 * Domain Path: /languages
 * License:      GPLv2 or later (license.txt)
 *
 * @package GatherPress
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define( 'GATHERPRESS_DEVELOP_CORE_PATH', __DIR__ );


if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/classes/class-cli.php';

	WP_CLI::add_command( 'gatherpress develop', GatherPress_Develop\Cli::class );
}
