<?php
/*
Plugin Name: Gravity Forms DPS PxPay
Plugin URI: http://snippets.webaware.com.au/wordpress-plugins/gravityforms-dps-pxpay/
Description: Integrates Gravity Forms with DPS PxPay payment gateway, enabling end users to purchase goods and services through Gravity Forms.
Version: 1.0.1
Author: WebAware
Author URI: http://www.webaware.com.au/
*/

/*
copyright (c) 2013 WebAware Pty Ltd (email : rmckay@webaware.com.au)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
useful references:
http://www.paymentexpress.com/Technical_Resources/Ecommerce_Hosted/PxPay
*/

/*
TODO: properly handle validation exceptions
TODO: expand readme doco of field mappings (ref: http://wordpress.org/support/topic/pxpay-invoice-reference-cannot-be-empty)
*/

if (!defined('GFDPSPXPAY_PLUGIN_ROOT')) {
	define('GFDPSPXPAY_PLUGIN_ROOT', dirname(__FILE__) . '/');
	define('GFDPSPXPAY_PLUGIN_NAME', basename(dirname(__FILE__)) . '/' . basename(__FILE__));
	define('GFDPSPXPAY_PLUGIN_OPTIONS', 'gfdpspxpay_plugin');
	define('GFDPSPXPAY_PLUGIN_VERSION', '1.0.1');

	// custom post types
	define('GFDPSPXPAY_TYPE_FEED', 'gfdpspxpay_feed');

	// end point for the DPS PxPay API
	define('GFDPSPXPAY_API_URL', 'https://sec.paymentexpress.com/pxpay/pxaccess.aspx');

	// end point for return to website
	define('GFDPSPXPAY_RETURN', 'gfdpspxpay_return');

	// name used as cURL user agent
	define('GFDPSPXPAY_CURL_USER_AGENT', 'Gravity Forms DPS PxPay');
}

/**
* autoload classes as/when needed
*
* @param string $class_name name of class to attempt to load
*/
function gfdpspxpay_autoload($class_name) {
	static $classMap = array (
		'GFDpsPxPayAdmin'						=> 'class.GFDpsPxPayAdmin.php',
		'GFDpsPxPayFeed'						=> 'class.GFDpsPxPayFeed.php',
		'GFDpsPxPayFeedAdmin'					=> 'class.GFDpsPxPayFeedAdmin.php',
		'GFDpsPxPayFormData'					=> 'class.GFDpsPxPayFormData.php',
		'GFDpsPxPayOptionsAdmin'				=> 'class.GFDpsPxPayOptionsAdmin.php',
		'GFDpsPxPayPayment'						=> 'class.GFDpsPxPayPayment.php',
		'GFDpsPxPayPlugin'						=> 'class.GFDpsPxPayPlugin.php',
		'GFDpsPxPayResult'						=> 'class.GFDpsPxPayResult.php',
	);

	if (isset($classMap[$class_name])) {
		require GFDPSPXPAY_PLUGIN_ROOT . $classMap[$class_name];
	}
}
spl_autoload_register('gfdpspxpay_autoload');

// instantiate the plug-in
GFDpsPxPayPlugin::getInstance();
