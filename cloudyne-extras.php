<?php
/**
 * Plugin Name: Cloudyne Extras
 * Version: 1.0.0
 * Plugin URI: http://www.hughlashbrooke.com/
 * Description: This is your starter template for your next WordPress plugin.
 * Author: Hugh Lashbrooke
 * Author URI: http://www.hughlashbrooke.com/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: cloudyne-extras
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Hugh Lashbrooke
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-cloudyne-extras.php';
require_once 'includes/class-cloudyne-extras-smtp.php';
require_once 'includes/class-cloudyne-extras-media.php';
require_once 'includes/class-cloudyne-extras-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-cloudyne-extras-admin-api.php';
require_once 'includes/lib/class-cloudyne-extras-post-type.php';
require_once 'includes/lib/class-cloudyne-extras-taxonomy.php';

/**
 * Returns the main instance of Cloudyne_Extras to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Cloudyne_Extras
 */
function cloudyne_extras() {
	$instance = Cloudyne_Extras::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = Cloudyne_Extras_Settings::instance( $instance );
	}

	$instance->add_hooks();

	return $instance;
}

cloudyne_extras();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class cli_images_to_webp extends WP_CLI_Command
	{
		function convert( $args, $assoc_args ) {
			$cldy_media = new Cloudyne_Extras_Media();

			// Arg: --force-all: Convert all images, even if they already have a webp version
			// Arg: --folder=<folder>: Convert all images in a folder relative to wp-content (vanilla) / app (bedrock)

			$only_missing = 1;
			if (isset($assoc_args['force-all']) && $assoc_args['force-all']) {
				$only_missing = 0;
			}
			
			$folder = 'uploads';
			if (isset($assoc_args['folder'])) {
				$folder = $assoc_args['folder'];
			}
			$dirList = $cldy_media->recursiveDirectoryList($folder);
			foreach ($dirList as $dir) {
				echo "Converting images in $dir ...  ";
				$cldy_media->convertMediaLibrary($only_missing, $dir);
			}
			echo "Finished";
		}
	}

	WP_CLI::add_command( 'cloudyne-webp', 'cli_images_to_webp' );
}