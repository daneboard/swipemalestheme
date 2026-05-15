<?php
/**
 * Config plugin file.
 *
 * @package PWLL\Main
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Navigation config
 */
self::$config['nav'] = array(
	'400'          => array(
		'slug'     => 'pwll-default-page',
		'callback' => 'pwll_default_page',
		'title'    => 'Paywall',
		'icon'     => 'fa-lock',
	),
	'pwll-options' => array(
		'slug' => 'pwll-options',
	),
);
