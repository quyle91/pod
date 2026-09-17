<?php
/**
 * Plugin Name:       POD Customizer & Print Ready Engine
 * Plugin URI:        https://pod.localhost
 * Description:       Personalization and live product preview with offloaded 300 DPI print-ready rendering.
 * Version:           1.0.0
 * Author:            quyle91 & Antigravity
 * Author URI:        https://pod.localhost
 * Text Domain:       pod-customizer
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define('POD_CUSTOMIZER_VERSION', '1.0.0');
define('POD_CUSTOMIZER_FILE', __FILE__);
define('POD_CUSTOMIZER_PATH', plugin_dir_path(__FILE__));
define('POD_CUSTOMIZER_URL', plugin_dir_url(__FILE__));

// Simple PSR-4 Autoloader fallback (ensures classes load without requiring composer dump-autoload during dev)
spl_autoload_register(function ($class) {
    $prefix = 'PodCustomizer\\';
    $base_dir = POD_CUSTOMIZER_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Bootstrap Plugin
function pod_customizer_init() {
    // Load plugin textdomain for i18n
    load_plugin_textdomain('pod-customizer', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Ensure WooCommerce is active before initializing handlers
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('POD Customizer requires WooCommerce to be installed and active.', 'pod-customizer') . '</p></div>';
        });
        return;
    }

    \PodCustomizer\Core\Plugin::instance()->init();
}
add_action('plugins_loaded', 'pod_customizer_init');
