<?php
/**
 * Class responsible for WP-CLI commands within GatherPress.
 *
 * This class handles WP-CLI commands specific to the GatherPress plugin,
 * allowing developers to interact with and manage plugin functionality via the command line.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress_Develop;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_CLI;

/**
 * Class Cli.
 *
 * The Cli class extends WP-CLI and provides custom WP-CLI commands
 * for interacting with and managing GatherPress functionality via the command line.
 *
 * @since 1.0.0
 */
class Cli extends WP_CLI {
	/**
	 * Generate credits data for the credits page.
	 *
	 * This method generates credits data for displaying on the credits page.
	 * It retrieves user data from WordPress.org profiles based on the provided version.
	 *
	 * ## OPTIONS
	 *
	 * [--version=<version>]
	 * : Plugin version to generate.
	 *
	 * ## EXAMPLES
	 *
	 *    # Generate credits.
	 *    $ wp gatherpress develop generate_credits --version=1.0.0
	 *    Success: New latest.php file has been generated.
	 *
	 * @codeCoverageIgnore Command is for internal purposes only.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       Positional arguments for the script.
	 * @param array $assoc_args Associative arguments for the script.
	 * @return void
	 */
	public function generate_version( array $args = array(), array $assoc_args = array() ): void {
		$credits = require_once GATHERPRESS_DEVELOP_CORE_PATH . '/data/credits.php';
		$version = $assoc_args['version'] ?? GATHERPRESS_VERSION;
		$latest  = GATHERPRESS_CORE_PATH . '/includes/data/credits.php';
		$data    = array();

		if ( empty( $credits[ $version ] ) ) {
			WP_CLI::error( 'Version does not exist' );
		}

		unlink( $latest ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		$file = fopen( $latest, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		$data['version'] = $version;
		$contributors    = [];

		foreach ( $credits[ $version ] as $group => $users ) {
			if ( 'contributors' === $group ) {
				sort( $users );
			} else {
				$contributors = array_merge( $contributors, $users );
			}

			$data[ $group ] = array();

			foreach ( $users as $user ) {
				$response  = wp_remote_request( sprintf( 'https://profiles.wordpress.org/wp-json/wporg/v1/users/%s', $user ) );
				$user_data = json_decode( $response['body'], true );

				// Remove unsecure data (eg http) and data we do not need.
				unset( $user_data['description'], $user_data['url'], $user_data['meta'], $user_data['_links'] );

				$data[ $group ][] = $user_data;
			}
		}

		fwrite( $file, "<?php\n\n" ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.PHP.DevelopmentFunctions.error_log_var_export
		fwrite( $file, "// Exit if accessed directly.\n" ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.PHP.DevelopmentFunctions.error_log_var_export
		fwrite( $file, "defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore\n\n" ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.PHP.DevelopmentFunctions.error_log_var_export
		fwrite( $file, 'return ' . var_export( $data, true ) . ";\n" ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite,WordPress.PHP.DevelopmentFunctions.error_log_var_export
		fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		static::success( 'New credits.php file has been generated.' );

		// Update gatherpress.php
		$plugin_file = GATHERPRESS_CORE_PATH . '/gatherpress.php';

		if ( ! file_exists( $plugin_file ) ) {
			WP_CLI::error( "The plugin file does not exist." );

			return;
		}

		$file_contents = file_get_contents( $plugin_file );

		if ( preg_match( '/^(\s*\*\s*Version:\s*)([\w\.-]+)(\s*)$/mi', $file_contents, $matches ) ) {
			$new_contents = preg_replace( '/^(\s*\*\s*Version:\s*)([\w\.-]+)(\s*)$/mi', '${1}' . $version . '${3}', $file_contents );

			if ( file_put_contents( $plugin_file, $new_contents ) !== false ) {
				WP_CLI::success( "Updated plugin file version to $version." );
			} else {
				WP_CLI::error( "Failed to update the plugin file." );
			}
		} else {
			WP_CLI::error( "Version header not found in the plugin file." );
		}

		// Update readme.me
		$readme_file  = GATHERPRESS_CORE_PATH . '/readme.md';
		$contributors = implode( ', ', $contributors );

		if ( ! file_exists( $readme_file ) ) {
			WP_CLI::error( "The readme file does not exist." );

			return;
		}

		$file_contents = file_get_contents( $readme_file );

		// readme version
		if ( preg_match( '/^(Stable tag:\s*)([\w\.-]+)(\s*)$/mi', $file_contents, $matches ) ) {
			$new_contents = preg_replace( '/^(Stable tag:\s*)([\w\.-]+)(\s*)$/mi', '${1}' . $version . '${3}', $file_contents );

			if ( file_put_contents( $readme_file, $new_contents ) !== false ) {
				WP_CLI::success( "Updated readme version to $version." );
			} else {
				WP_CLI::error( "Failed to update the readme file." );
			}
		} else {
			WP_CLI::error( "Version header not found in the readme file." );
		}

		// readme contributors
		if ( preg_match( '/(^Contributors:\s*)([^\r\n]*)($)/mi', $file_contents, $matches ) ) {
			$new_contents = preg_replace( '/(^Contributors:\s*)([^\r\n]*)($)/mi', '${1}' . $contributors . '${3}', $file_contents );

			if ( file_put_contents( $readme_file, $new_contents ) !== false ) {
				WP_CLI::success( "Updated readme contributors." );
			} else {
				WP_CLI::error( "Failed to update the readme file." );
			}
		} else {
			WP_CLI::error( "Version header not found in the readme file." );
		}

		// Update package.json
		chdir( GATHERPRESS_CORE_PATH );

		$package_file = GATHERPRESS_CORE_PATH . '/package.json';

		if ( ! file_exists( $package_file ) ) {
			WP_CLI::error( "The package.json file does not exist." );

			return;
		}

		$file_contents = file_get_contents( $package_file );

		if ( preg_match( '/^(\s*"version": ")([\w\.-]+)(",)$/mi', $file_contents, $matches ) ) {
			$new_contents = preg_replace( '/^(\s*"version": ")([\w\.-]+)(",)$/mi', '${1}' . $version . '${3}', $file_contents );

			if ( file_put_contents( $package_file, $new_contents ) !== false ) {
				WP_CLI::success( "Updated package.json version to $version." );
				shell_exec( 'curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.35.3/install.sh | bash' );
				shell_exec( 'nvm use' );
				shell_exec( 'npm i --package-lock-only' );
			} else {
				WP_CLI::error( "Failed to update the package.json file." );
			}
		} else {
			WP_CLI::error( "Version not found in the package.json file." );
		}
	}
}
