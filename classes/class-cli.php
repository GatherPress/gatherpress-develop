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
			}

			// Only leads + team land in the wp.org plugin header's
			// `Contributors:` line. The contributors group still appears on
			// the credits page (via $data below), but it gets churn-y as more
			// people land single-PR contributions, and wp.org's plugin
			// directory lists those as "Contributors" with a level of billing
			// that doesn't match the actual involvement.
			if ( 'contributors' !== $group ) {
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

		// Generate README.md and readme.txt from parts.
		$contributors = implode( ', ', $contributors );
		$this->generate_readmes( $version, $contributors );

		// Update package.json.
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

				// Refresh `package-lock.json` is intentionally NOT run here.
				// Previous versions tried to install nvm + run `nvm use` +
				// `npm i --package-lock-only` via shell_exec, but inside the
				// lando container there's no Node toolchain and `nvm` is a
				// bash function that shell_exec can't source. Reminder the
				// developer to do it on the host where their nvm + Node are
				// actually installed.
				WP_CLI::log( '' );
				WP_CLI::log( 'Next step (run on your host, not inside lando — lando containers have no Node):' );
				WP_CLI::log( '  cd <your gatherpress checkout>' );
				WP_CLI::log( '  nvm use && npm i --package-lock-only' );
				WP_CLI::log( '' );
				WP_CLI::log( 'That refreshes package-lock.json to match the new package.json version.' );
			} else {
				WP_CLI::error( "Failed to update the package.json file." );
			}
		} else {
			WP_CLI::error( "Version not found in the package.json file." );
		}

		// Sync GatherPress Alpha to the same version (lockstep companion) and
		// refresh the Supported Versions table in both SECURITY.md files.
		$this->update_alpha_version( $version );
		$this->update_security_versions( $version );
	}

	/**
	 * Sync the GatherPress Alpha plugin's version header to match core.
	 *
	 * GatherPress Alpha tracks the core plugin version in lockstep. It lives in
	 * a sibling plugin directory next to core (../gatherpress-alpha), so the
	 * path is derived from GATHERPRESS_CORE_PATH's parent.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version The full plugin version to set (e.g. "0.34.0-beta.1").
	 * @return void
	 */
	private function update_alpha_version( string $version ): void {
		$alpha_file = dirname( GATHERPRESS_CORE_PATH ) . '/gatherpress-alpha/gatherpress-alpha.php';

		if ( ! file_exists( $alpha_file ) ) {
			WP_CLI::warning( 'GatherPress Alpha plugin not found alongside core; skipping alpha version sync.' );

			return;
		}

		$file_contents = file_get_contents( $alpha_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents

		if ( ! preg_match( '/^(\s*\*\s*Version:\s*)([\w\.-]+)(\s*)$/mi', $file_contents ) ) {
			WP_CLI::error( 'Version header not found in the GatherPress Alpha plugin file.' );

			return;
		}

		$new_contents = preg_replace( '/^(\s*\*\s*Version:\s*)([\w\.-]+)(\s*)$/mi', '${1}' . $version . '${3}', $file_contents );

		if ( file_put_contents( $alpha_file, $new_contents ) !== false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::success( "Updated GatherPress Alpha version to $version." );
		} else {
			WP_CLI::error( 'Failed to update the GatherPress Alpha plugin file.' );
		}
	}

	/**
	 * Refresh the Supported Versions table in SECURITY.md for core and alpha.
	 *
	 * Sets the supported row to the current major.minor (e.g. "0.34.x") and the
	 * unsupported row to "< {major.minor}" (e.g. "< 0.34"), deriving major.minor
	 * from the full version with any -alpha/-beta/-rc suffix stripped.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version The full plugin version (e.g. "0.34.0-beta.1").
	 * @return void
	 */
	private function update_security_versions( string $version ): void {
		if ( ! preg_match( '/^(\d+\.\d+)/', $version, $matches ) ) {
			WP_CLI::error( "Could not derive major.minor from version: $version" );

			return;
		}

		$major_minor = $matches[1];
		$files       = array(
			'core'  => GATHERPRESS_CORE_PATH . '/SECURITY.md',
			'alpha' => dirname( GATHERPRESS_CORE_PATH ) . '/gatherpress-alpha/SECURITY.md',
		);

		foreach ( $files as $label => $file ) {
			if ( ! file_exists( $file ) ) {
				WP_CLI::warning( "SECURITY.md for $label not found ($file); skipping." );

				continue;
			}

			$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents

			// Supported row, e.g. "| 0.34.x | :white_check_mark: |".
			$contents = preg_replace( '/(\|\s*)\d+\.\d+(\.x\s*\|\s*:white_check_mark:)/', '${1}' . $major_minor . '${2}', $contents );

			// Unsupported row, e.g. "| < 0.34 | :x: |".
			$contents = preg_replace( '/(\|\s*<\s*)\d+\.\d+(\s*\|\s*:x:)/', '${1}' . $major_minor . '${2}', $contents );

			if ( file_put_contents( $file, $contents ) !== false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				WP_CLI::success( "Updated SECURITY.md ($label) to {$major_minor}.x supported / < {$major_minor} unsupported." );
			} else {
				WP_CLI::error( "Failed to update SECURITY.md ($label)." );
			}
		}
	}

	/**
	 * Generate README.md and readme.txt from parts.
	 *
	 * Assembles both readme files from template parts stored in gatherpress-develop/parts/.
	 * README.md is GitHub-focused, readme.txt is WordPress.org-focused.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version      The plugin version.
	 * @param string $contributors Comma-separated list of contributor usernames.
	 * @return void
	 */
	private function generate_readmes( string $version, string $contributors ): void {
		$tested_up_to = $this->get_tested_up_to();

		// Generate README.md for GitHub.
		$readme_md = $this->build_github_readme( $version, $contributors, $tested_up_to );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( GATHERPRESS_CORE_PATH . '/README.md', $readme_md ) !== false ) {
			WP_CLI::success( 'Generated README.md.' );
		} else {
			WP_CLI::error( 'Failed to generate README.md.' );
		}

		// Generate readme.txt for WordPress.org.
		$readme_txt = $this->build_wporg_readme( $version, $contributors, $tested_up_to );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( GATHERPRESS_CORE_PATH . '/readme.txt', $readme_txt ) !== false ) {
			WP_CLI::success( 'Generated readme.txt.' );
		} else {
			WP_CLI::error( 'Failed to generate readme.txt.' );
		}
	}

	/**
	 * Build the GitHub README.md content from parts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version      The plugin version.
	 * @param string $contributors Comma-separated list of contributor usernames.
	 * @param string $tested_up_to The WordPress version tested up to.
	 * @return string The assembled README.md content.
	 */
	private function build_github_readme( string $version, string $contributors, string $tested_up_to ): string {
		$output  = "# GatherPress\n\n";
		$output .= "<!-- markdownlint-disable-next-line MD045 -->\n";
		$output .= "![](.wordpress-org/banner-1544x500.jpg)\n\n";
		$output .= '**' . trim( $this->read_part( 'shared/description.md' ) ) . "**\n\n";
		$version_encoded = rawurlencode( $version );
		$output         .= "[![Try it in WordPress Playground](https://img.shields.io/badge/Try_it-in_WordPress_Playground-blue?logo=wordpress&logoColor=%23fff&labelColor=%233858e9&color=%233858e9)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/GatherPress/gatherpress/main/.wordpress-org/blueprints/blueprint.json) ![Version](https://img.shields.io/static/v1?label=version&message={$version_encoded}&color=blue)\n\n";
		$output       .= $this->read_part( 'github/badges.md' ) . "\n";
		$output .= "## Screenshots\n\n";
		$output .= $this->build_github_screenshots() . "\n";
		$output .= "## Features\n\n";
		$output .= $this->read_part( 'shared/features.md' ) . "\n";
		$output .= "## Getting Started\n\n";
		$output .= $this->read_part( 'github/quick-start.md' ) . "\n";
		$output .= "## Get Involved\n\n";
		$output .= $this->read_part( 'github/get-involved.md' ) . "\n";
		$output .= "## Third-Party Libraries\n\n";
		$output .= $this->read_part( 'shared/third-party-libraries.md' ) . "\n";
		$output .= "## External Services\n\n";
		$output .= $this->read_part( 'github/external-services.md' ) . "\n";
		$output .= "## More Information\n\n";
		$output .= $this->read_part( 'github/more-info.md' ) . "\n";
		$output .= "---\n\n";
		$output .= $this->read_part( 'github/footer.md' );

		return $output;
	}

	/**
	 * Build the WordPress.org readme.txt content from parts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version      The plugin version.
	 * @param string $contributors Comma-separated list of contributor usernames.
	 * @param string $tested_up_to The WordPress version tested up to.
	 * @return string The assembled readme.txt content.
	 */
	private function build_wporg_readme( string $version, string $contributors, string $tested_up_to ): string {
		$output  = "=== GatherPress ===\n";
		$output .= "Contributors: {$contributors}\n";
		$output .= "Tags: events, event, meetup, community\n";
		$output .= "Tested up to: {$tested_up_to}\n";
		$output .= "Stable tag: {$version}\n";
		$output .= "License: GPL v2 or later\n";
		$output .= "License URI: https://www.gnu.org/licenses/gpl-2.0.html\n\n";
		$output .= trim( $this->read_part( 'shared/description.md' ) ) . "\n\n";
		$output .= "== Description ==\n\n";
		$output .= $this->read_part( 'shared/features.md' ) . "\n";
		$output .= "== Installation ==\n\n";
		$output .= $this->read_part( 'shared/installation.md' ) . "\n";
		$output .= "== Screenshots ==\n\n";
		$output .= $this->read_part( 'shared/screenshots.md' ) . "\n";
		$output .= "== Changelog ==\n\n";
		$output .= "For the full changelog, visit the [GitHub releases page](https://github.com/GatherPress/gatherpress/releases).\n\n";
		$output .= "== Frequently Asked Questions ==\n\n";
		$output .= "Visit our [FAQ page](https://github.com/GatherPress/gatherpress/blob/main/docs/faq.md) for answers to common questions.\n\n";
		$output .= "== External Services ==\n\n";
		$output .= $this->read_part( 'wporg/external-services.md' );

		return $output;
	}

	/**
	 * Build GitHub screenshot markdown with image references.
	 *
	 * Reads shared screenshot descriptions and adds image markdown for GitHub rendering.
	 *
	 * @since 1.0.0
	 *
	 * @return string The screenshot section with images.
	 */
	private function build_github_screenshots(): string {
		$screenshots = $this->read_part( 'shared/screenshots.md' );
		$image_map   = array(
			1 => '.wordpress-org/screenshot-1.png',
			2 => '.wordpress-org/screenshot-2.png',
			3 => '.wordpress-org/screenshot-5.png',
		);

		$output = '';
		$lines  = explode( "\n", trim( $screenshots ) );

		foreach ( $lines as $line ) {
			if ( preg_match( '/^(\d+)\.\s+(.+)$/', $line, $matches ) ) {
				$num         = (int) $matches[1];
				$description = $matches[2];
				$output     .= "{$num}. {$description}\n";

				if ( isset( $image_map[ $num ] ) ) {
					$output .= "   ![screenshot-{$num}]({$image_map[ $num ]})\n";
				}
			}
		}

		return $output;
	}

	/**
	 * Read the current "Tested up to" value from readme.txt.
	 *
	 * @since 1.0.0
	 *
	 * @return string The tested up to WordPress version.
	 */
	private function get_tested_up_to(): string {
		$readme_file = GATHERPRESS_CORE_PATH . '/readme.txt';

		if ( file_exists( $readme_file ) ) {
			$contents = file_get_contents( $readme_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents

			if ( preg_match( '/^Tested up to:\s*([\w\.-]+)\s*$/mi', $contents, $matches ) ) {
				return $matches[1];
			}
		}

		return '6.9';
	}

	/**
	 * Read a template part file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path Path relative to the parts directory.
	 * @return string The file contents.
	 */
	private function read_part( string $relative_path ): string {
		$file = GATHERPRESS_DEVELOP_CORE_PATH . '/parts/' . $relative_path;

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "Part file not found: {$relative_path}" );
		}

		return file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
	}
}
