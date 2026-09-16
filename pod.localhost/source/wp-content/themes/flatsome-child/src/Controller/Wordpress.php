<?php

namespace FlatsomeChild\Controller;

class Wordpress {
    private static $instance = null;
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        $this->update_with_git();
        $this->disable_xmlprc();
        // $this->load_language();
    }

    // function load_language() {
    //     add_action('after_setup_theme', function () {
    //         load_theme_textdomain('flatsome-child', FLATSOME_CHILD_DIR . '/languages');
    //     });
    // }

    function update_with_git() {
        add_filter('automatic_updates_is_vcs_checkout', '__return_false', 1); // bỏ qua quản lý của git ko cho update tự động
    }

    function disable_xmlprc() {
        // Disable XML-RPC
        add_filter('xmlrpc_enabled', '__return_false');

        // Remove Pingback Header
        remove_action('wp_head', 'rsd_link');

        // Block XML-RPC Requests
        add_filter('xmlrpc_methods', function ($methods) {
            return [];
            // unset($methods['pingback.ping']);
            // return $methods;
        });
    }
}
