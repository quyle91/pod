<?php

define('FLATSOME_CHILD_DIR_URL', get_stylesheet_directory_uri());
define('FLATSOME_CHILD_DIR', get_stylesheet_directory());
define('FLATSOME_CHILD_VER', wp_get_theme()->get('Version'));

require __DIR__ . "/vendor/autoload.php";

// if (!defined('ADMINZ')) {
//     return;
// }
$GLOBALS['FlatsomeChild'] = [
    'Wordpress' => \FlatsomeChild\Controller\Wordpress::get_instance(),
    'Ajax' => \FlatsomeChild\Controller\Ajax::get_instance(),
    'Restapi' => \FlatsomeChild\Controller\Restapi::get_instance(),
    'Enqueue' => \FlatsomeChild\Controller\Enqueue::get_instance(),
    'Adminz' => \FlatsomeChild\Controller\Adminz::get_instance(),
    'Wpcf7' => \FlatsomeChild\Controller\Wpcf7::get_instance(),
    'Woocommerce' => \FlatsomeChild\Controller\Woocommerce::get_instance(),
    'Flatsome' => \FlatsomeChild\Controller\Flatsome::get_instance(),
    'FlatsomeElement' => \FlatsomeChild\Controller\FlatsomeElement::get_instance(),
    'Acf' => \FlatsomeChild\Controller\Acf::get_instance(),
    'Polylang' => \FlatsomeChild\Controller\Polylang::get_instance(),
    'WpRocket' => \FlatsomeChild\Controller\WpRocket::get_instance(),
];
