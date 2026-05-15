<?php
/**
 * Premium-access helper used across the paywall to determine whether a user
 * may bypass locked content.
 *
 * Sources of access:
 *  - WooCommerce-based premium flag (`_has_premium_access` meta) — set by
 *    `public/premium-access.php` based on completed orders / active subs.
 *  - TikSwipe Ad Removal membership (TSAR_Membership::is_premium) — paying
 *    for ad-removal also unlocks paywalled videos.
 *  - Admin "navigate as premium member" preview switch.
 *
 * @package pwll\inc
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'pwll_user_has_premium_access' ) ) {
	function pwll_user_has_premium_access( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}
		$user_id = (int) $user_id;

		$has = false;

		if ( $user_id > 0 ) {
			$has = 'on' === get_user_meta( $user_id, '_has_premium_access', true );

			if ( ! $has && class_exists( 'TSAR_Membership' ) ) {
				$has = TSAR_Membership::is_premium( $user_id );
			}
		}

		if ( ! $has && function_exists( 'xbox_get_field_value' ) ) {
			$preview = xbox_get_field_value( 'pwll-options', 'pwll-navigate-as-premium-member', 'off' );
			if ( 'on' === $preview && user_can( $user_id, 'administrator' ) ) {
				$has = true;
			}
		}

		return (bool) apply_filters( 'pwll_user_has_premium_access', $has, $user_id );
	}
}
