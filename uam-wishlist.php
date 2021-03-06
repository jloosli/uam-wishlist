<?php
/*
 * Plugin Name: User Access Manager to Wishlist Plugin
 * Version: 1.0
 * Plugin URI: https://github.com/jloosli/uam-wishlist
 * Description: User Access Manager -> Wishlist converter
 * Author: Jared Loosli
 * Author URI: https://github.com/jloosli/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: uam-wishlist
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Jared Loosli
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Load plugin class files
require_once( 'includes/class-uam-wishlist.php' );
require_once( 'includes/class-uam-wishlist-settings.php' );

// Load plugin libraries
require_once( 'includes/lib/class-uam-wishlist-admin-api.php' );
require_once( 'includes/lib/class-uam-wishlist-post-type.php' );
require_once( 'includes/lib/class-uam-wishlist-taxonomy.php' );

/**
 * Returns the main instance of UAM_Wishlist to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object UAM_Wishlist
 */
function UAM_Wishlist () {
	$instance = UAM_Wishlist::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = UAM_Wishlist_Settings::instance( $instance );
	}

	return $instance;
}

UAM_Wishlist();