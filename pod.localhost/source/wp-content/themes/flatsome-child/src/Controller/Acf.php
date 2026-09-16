<?php

namespace FlatsomeChild\Controller;

class Acf {
    private static $instance = null;
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        $this->setup_json();
        $this->override_license();
    }

    function setup_json() {
        // save file
        add_filter('acf/settings/save_json', function ($path) {
            $path = get_stylesheet_directory() . '/acf-json';
            return $path;
        });

        // load file
        add_filter('acf/settings/load_json', function ($paths) {
            unset($paths[0]);
            $paths[] = get_stylesheet_directory() . '/acf-json';
            return $paths;
        });
    }

    function override_license() {
        add_action('init', function () {
            if (!current_user_can('administrator')) {
                return;
            }

            if (!isset($_GET['active_acf'])) {
                return;
            }

            $license = [
                'key' => 'xxxxxx',
                'url' => get_site_url(),
            ];

            $acf_pro_license = base64_encode(serialize($license));
            update_option(
                'acf_pro_license',
                $acf_pro_license
            );
            update_option(
                'acf_pro_license_status',
                [
                    'status' => 'active',
                    'created' => time(),
                    'expiry' => strtotime("+10 years", time()),
                    'name' => 'XXX',
                    'lifetime' => '',
                    'refunded' => '',
                    'view_licenses_url' => 'https://www.advancedcustomfields.com/my-account/view-licenses/',
                    'manage_subscription_url' => 'https://www.advancedcustomfields.com/my-account/view-subscription/515214/',
                    'error_msg' => '',
                    'next_check' => strtotime("+10 years", time()),
                    'legacy_multisite' => '',
                ]
            );
        });
    }
}
