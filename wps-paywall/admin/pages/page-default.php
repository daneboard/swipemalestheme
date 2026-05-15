<?php
/**
 * Default page
 *
 * @package PWLL\Admin\Pages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default page callback function
 *
 * @return void
 */
function pwll_default_page() {
	echo '<script>window.location.replace("admin.php?page=pwll-options");</script>';
}
