<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;
if ( is_user_logged_in() ) {
	$user_info  = get_userdata( get_current_user_id() );
	$first_name = ucfirst( esc_html( $user_info->first_name ) );
} ?>

<p class="thanks"><?php esc_html_e( 'Thank you! You have now access to all our exclusive content.', 'pwll_lang' ); ?></p>

<a class="pwll-button back-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'pwll_lang' ); ?></a>
