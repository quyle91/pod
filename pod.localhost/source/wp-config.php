<?php
/**
 * The base configuration for WordPress in Docker
 */

define('WP_ACCESSIBLE_HOSTS', 'localhost,*.localhost');
define('CONCATENATE_SCRIPTS', false);

if (!function_exists('getenv_docker')) {
    function getenv_docker($env, $default) {
        if ($fileEnv = getenv($env . '_FILE')) {
            return rtrim(file_get_contents($fileEnv), "\r\n");
        } else if (($val = getenv($env)) !== false) {
            return $val;
        } else {
            return $default;
        }
    }
}

// ** Database settings - Sourced from Docker environment ** //
define('DB_NAME', getenv_docker('WORDPRESS_DB_NAME', 'pod_wp'));
define('DB_USER', getenv_docker('WORDPRESS_DB_USER', 'pod_user'));
define('DB_PASSWORD', getenv_docker('WORDPRESS_DB_PASSWORD', 'pod_secret'));
define('DB_HOST', getenv_docker('WORDPRESS_DB_HOST', 'pod_db'));
define('DB_CHARSET', getenv_docker('WORDPRESS_DB_CHARSET', 'utf8mb4'));
define('DB_COLLATE', getenv_docker('WORDPRESS_DB_COLLATE', ''));

/**#@+
 * Authentication unique keys and salts.
 */
define('AUTH_KEY',         getenv_docker('WORDPRESS_AUTH_KEY',         'b396525120025637d62b09c2bcd1000d0f096566'));
define('SECURE_AUTH_KEY',  getenv_docker('WORDPRESS_SECURE_AUTH_KEY',  '4197a2e94153f6e4aac105aad34dcdc632346d10'));
define('LOGGED_IN_KEY',    getenv_docker('WORDPRESS_LOGGED_IN_KEY',    'f1886551ded477e2a7e437191aa5c9798aaec772'));
define('NONCE_KEY',        getenv_docker('WORDPRESS_NONCE_KEY',        '1fff106bc03b3c879e88e2e3f6f10054f2c5b793'));
define('AUTH_SALT',        getenv_docker('WORDPRESS_AUTH_SALT',        'd5b87f06090c516951eac0bcdf904d2e7e8a419f'));
define('SECURE_AUTH_SALT', getenv_docker('WORDPRESS_SECURE_AUTH_SALT', '10ff651c4d7924049905628a5cb8e8357cb540ee'));
define('LOGGED_IN_SALT',   getenv_docker('WORDPRESS_LOGGED_IN_SALT',   '7a5563d8ee772e990b057273b9aab63204fa84cd'));
define('NONCE_SALT',       getenv_docker('WORDPRESS_NONCE_SALT',       '3a23240e2f2a3a82ae26c6750b3eed5aaf20af41'));
/**#@-*/

$table_prefix = getenv_docker('WORDPRESS_TABLE_PREFIX', 'wp_');

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
