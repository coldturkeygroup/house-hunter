<?php namespace ColdTurkey\HouseHunter;
/*
 * Plugin Name: House Hunter
 * Version: 1.8.1
 * Plugin URI: http://www.coldturkeygroup.com/
 * Description: A form for prospective home buyers to fill out to request more information from a real estate agent.
 * Author: Cold Turkey Group
 * Author URI: http://www.coldturkeygroup.com/
 * Requires at least: 4.0
 * Tested up to: 4.3
 *
 * @package House Hunter
 * @author Aaron Huisinga
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'HOUSE_HUNTER_PLUGIN_PATH' ) )
	define( 'HOUSE_HUNTER_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! defined( 'HOUSE_HUNTER_PLUGIN_VERSION' ) )
	define( 'HOUSE_HUNTER_PLUGIN_VERSION', '1.8.1' );

require_once( 'classes/class-house-hunter.php' );

global $house_hunter;
$house_hunter = new HouseHunter( __FILE__, new FrontDesk() );

if ( is_admin() ) {
	require_once( 'classes/class-house-hunter-admin.php' );
	new HouseHunter_Admin( __FILE__ );
}
