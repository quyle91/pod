<?php
namespace FlatsomeChild\Controller;

class Adminz {
	private static $instance = null;
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct() {
		if ( !defined( 'ADMINZ' ) ) {
			return;
		}
	}
}