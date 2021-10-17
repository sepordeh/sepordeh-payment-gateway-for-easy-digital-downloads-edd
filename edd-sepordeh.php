<?php
/**
 * Plugin Name: Sepordeh Payment Gateway for Easy Digital Downloads (EDD)
 * Author: Sepordeh
 * Description: This plugin activate <a href="https://Sepordeh.com">Sepordeh</a> payment method in EDD
 * Version: 3.0.0
 * Plugin URI: https://wordpress.org/plugins/edd-sepordeh
 * Author: Sepordeh.com
 * Author URI: https://Sepordeh.com/
 */

function edd_sepordeh_load_textdomain() {
	load_plugin_textdomain( 'edd-sepordeh', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'edd_sepordeh_load_textdomain' );

// Toman Currency
require 'includes/toman-currency.php';

// Include the main file
require 'gateways/sepordeh.php';
