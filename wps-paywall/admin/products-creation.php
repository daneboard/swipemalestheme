<?php
/**********************************/
/****** LIFETIME MEMBERSHIP *******/
/**********************************/
add_action( 'init', 'pwll_premium_membership_lifetime_product_creation' );
function pwll_premium_membership_lifetime_product_creation() {

	if ( ! class_exists( 'WooCommerce', false ) ) {
		return;
	}

	$premium_membership_lifetime_product_exist = pwll_get_product_by_sku( 'premium-membership-lifetime' );
	if ( $premium_membership_lifetime_product_exist !== null ) {
		return;
	}

	$premium_membership_lifetime_product_args = array(
		'post_content' => '',
		'post_status'  => 'publish', // (Draft | Pending | Publish)
		'post_title'   => 'Premium Membership / Lifetime',
		'post_parent'  => '',
		'post_type'    => 'product',
	);
	// Create a simple WooCommerce product
	$premium_membership_lifetime_product_id = wp_insert_post( $premium_membership_lifetime_product_args );

	$premium_membership_lifetime_product_obj = wc_get_product( $premium_membership_lifetime_product_id );

	$premium_membership_lifetime_product_obj->set_sku( 'premium-membership-lifetime' );
	$premium_membership_lifetime_product_obj->set_price( 99 );
	$premium_membership_lifetime_product_obj->set_regular_price( 99 );
	// $premium_membership_lifetime_product_obj->set_tax_class('digital-goods');
	$premium_membership_lifetime_product_obj->set_catalog_visibility( 'hidden' );
	$premium_membership_lifetime_product_obj->set_manage_stock( false );
	$premium_membership_lifetime_product_obj->set_virtual( true );
	$premium_membership_lifetime_product_obj->set_downloadable( true );
	$premium_membership_lifetime_product_obj->set_sold_individually( true );
	$premium_membership_lifetime_product_obj->save();

	// Set the premium category
	wp_set_object_terms( $premium_membership_lifetime_product_id, 'Premium Membership', 'product_cat' );

	// Setting the product type
	wp_set_object_terms( $premium_membership_lifetime_product_id, 'simple', 'product_type' );
}

/********************************************************************/
/****** SUBSCRIPTION VARIABLES WITH WOOCOMMERCE SUBSCRIPTIONS */
/********************************************************************/
add_action( 'init', 'pwll_premium_membership_subscription_product_creation' );
function pwll_premium_membership_subscription_product_creation() {
	$premium_membership_subscription_plans_exist = pwll_get_product_by_sku( 'premium-membership-subscription' );
	if ( $premium_membership_subscription_plans_exist !== null || ! class_exists( 'WC_Subscriptions', false ) ) {
		return;
	}

	$objProductVarSub = new WC_Product_Variable_Subscription();
	$objProductVarSub->set_name( 'Premium Membership / Subscription' );
	$objProductVarSub->set_sku( 'premium-membership-subscription' );
	// $objProductVarSub->set_tax_class('digital-goods');
	$objProductVarSub->set_catalog_visibility( 'hidden' );
	$objProductVarSub->set_sold_individually( true );

	// Create the attribute object
	$objProductVarAttr = new WC_Product_Attribute();
	// subscription_length tax id
	$objProductVarAttr->set_id( 0 );
	// subscription_length slug
	$objProductVarAttr->set_name( 'subscription_length' );
	// Set terms slugs
	$objProductVarAttr->set_options(
		array(
			'1 month',
			'3 months',
			'6 months',
			'12 months',
		)
	);
	$objProductVarAttr->set_position( 0 );

	// If enabled
	$objProductVarAttr->set_visible( 1 );

	// If we are going to use attribute in order to generate variations
	$objProductVarAttr->set_variation( 1 );

	$objProductVarSub->set_attributes( array( $objProductVarAttr ) );

	// Save main product to get its id
	$new_subscription_product_id = $objProductVarSub->save();

	$subscription_args = array(
		'ID' => $new_subscription_product_id,
		// 'post_author' => $user_id
	);
	wp_update_post( $subscription_args, true );

	add_action( 'admin_head', 'showhiddencustomfields' );

	function showhiddencustomfields() {
		echo "<style type='text/css'>#postcustom .hidden { display: table-row; }</style>";
	}

	/***********************************/
	/** SUBSCRIPTION VARIATION 1 MONTH */
	/***********************************/
	$sub_variation_1 = new WC_Product_Variation();
	$sub_variation_1->set_sku( 'premium-membership-1-month' );
	$sub_variation_1->set_manage_stock( false );
	$sub_variation_1->set_virtual( true );
	$sub_variation_1->set_downloadable( true );
	$sub_variation_1->set_price( 9 );
	$sub_variation_1->set_regular_price( 9 );
	$sub_variation_1->update_meta_data( '_subscription_period', 'month', true );
	$sub_variation_1->update_meta_data( '_subscription_period_interval', '1', true );
	$sub_variation_1->set_parent_id( $new_subscription_product_id );

	// Set attributes requires a key/value containing
	// tax and term slug
	$sub_variation_1->set_attributes(
		array(
			'subscription_length' => '1 month',
		)
	);
	// Save variation, returns variation id
	$sub_variation_1->save();

	/************************************/
	/** SUBSCRIPTION VARIATION 3 MONTHS */
	/************************************/
	$sub_variation_3 = new WC_Product_Variation();
	$sub_variation_3->set_sku( 'premium-membership-3-months' );
	$sub_variation_3->set_manage_stock( false );
	$sub_variation_3->set_virtual( true );
	$sub_variation_3->set_downloadable( true );
	$sub_variation_3->set_price( 19 );
	$sub_variation_3->set_regular_price( 19 );
	$sub_variation_3->update_meta_data( '_subscription_period', 'month', true );
	$sub_variation_3->update_meta_data( '_subscription_period_interval', '3', true );
	$sub_variation_3->set_parent_id( $new_subscription_product_id );

	// Set attributes requires a key/value containing
	// tax and term slug
	$sub_variation_3->set_attributes(
		array(
			'subscription_length' => '3 months',
		)
	);
	// Save variation, returns variation id
	$sub_variation_3->save();

	/************************************/
	/** SUBSCRIPTION VARIATION 6 MONTHS */
	/************************************/
	$sub_variation_6 = new WC_Product_Variation();
	$sub_variation_6->set_sku( 'premium-membership-6-months' );
	$sub_variation_6->set_manage_stock( false );
	$sub_variation_6->set_virtual( true );
	$sub_variation_6->set_downloadable( true );
	$sub_variation_6->set_price( 29 );
	$sub_variation_6->set_regular_price( 29 );
	$sub_variation_6->update_meta_data( '_subscription_period', 'month', true );
	$sub_variation_6->update_meta_data( '_subscription_period_interval', '6', true );
	$sub_variation_6->set_parent_id( $new_subscription_product_id );

	// Set attributes requires a key/value containing
	// tax and term slug
	$sub_variation_6->set_attributes(
		array(
			'subscription_length' => '6 months',
		)
	);
	// Save variation, returns variation id
	$sub_variation_6->save();

	/**************************************/
	/** SUBSCRIPTION VARIATION 12 MONTHS */
	/**************************************/
	$sub_variation_12 = new WC_Product_Variation();
	$sub_variation_12->set_sku( 'premium-membership-12-months' );
	$sub_variation_12->set_manage_stock( false );
	$sub_variation_12->set_virtual( 'yes' );
	$sub_variation_12->set_downloadable( 'yes' );
	$sub_variation_12->set_price( 49 );
	$sub_variation_12->set_regular_price( 49 );
	$sub_variation_12->update_meta_data( '_subscription_period', 'year', true );
	$sub_variation_12->update_meta_data( '_subscription_period_interval', '1', true );
	$sub_variation_12->set_parent_id( $new_subscription_product_id );

	// Set attributes requires a key/value containing
	// tax and term slug
	$sub_variation_12->set_attributes(
		array(
			'subscription_length' => '12 months',
		)
	);
	// Save variation, returns variation id
	$sub_variation_12->save();

	// Set the premium category
	wp_set_object_terms( $new_subscription_product_id, 'Premium Membership', 'product_cat' );
}
