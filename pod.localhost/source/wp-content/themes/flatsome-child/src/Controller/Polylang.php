<?php
namespace FlatsomeChild\Controller;

class Polylang {
	private static $instance = null;
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct() {

		// use folder flatsome-child/polylang
        // save polylang setup again: /wp-admin/admin.php?page=mlang_settings
	}
}