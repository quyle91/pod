<?php

namespace PodCustomizer\Frontend;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class CustomizerAssets
 * Responsible for conditional script and stylesheet enqueueing on single product pages.
 * Follows Single Responsibility Principle (SRP).
 */
class CustomizerAssets implements HandlerInterface {

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Enqueue stylesheets, Google fonts, and client-side canvas scripts.
     *
     * @return void
     */
    public function enqueue_assets(): void {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        global $post;
        $product = wc_get_product($post->ID);
        if (!$product || !apply_filters('pod_is_product_customizable', true, $product)) {
            return;
        }

        // 1. Google Fonts for typography live preview
        wp_enqueue_style(
            'pod-google-fonts',
            'https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Montserrat:wght@400;600;700&family=Oswald:wght@500;700&family=Pacifico&family=Roboto:wght@400;500;700&display=swap',
            [],
            null
        );

        // 2. Customizer UI Stylesheet
        wp_enqueue_style(
            'pod-customizer-css',
            POD_CUSTOMIZER_URL . 'assets/css/pod-customizer.css',
            [],
            POD_CUSTOMIZER_VERSION
        );

        // 3. Canvas Engine (Fabric.js)
        wp_enqueue_script(
            'pod-fabric-js',
            POD_CUSTOMIZER_URL . 'assets/js/vendor/fabric.min.js',
            [],
            '5.3.0',
            true
        );

        // 4. Main Customizer Orchestrator Script
        wp_enqueue_script(
            'pod-customizer-js',
            POD_CUSTOMIZER_URL . 'assets/js/pod-customizer.js',
            ['pod-fabric-js'],
            POD_CUSTOMIZER_VERSION,
            true
        );

        // 5. Localize Configuration & Visual Catalogs
        $config = [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'pluginUrl'  => POD_CUSTOMIZER_URL,
            'productId'  => $product->get_id(),
            'canvas'     => [
                'width'  => 1200,
                'height' => 1200,
                'dpi'    => 300,
                'unit'   => 'px',
            ],
            'mockups'    => [
                [
                    'id'    => 'tshirt_white',
                    'name'  => __('White T-Shirt', 'pod-customizer'),
                    'url'   => POD_CUSTOMIZER_URL . 'assets/mockups/tshirt-white.svg',
                    'color' => '#ffffff',
                ],
                [
                    'id'    => 'tshirt_black',
                    'name'  => __('Black T-Shirt', 'pod-customizer'),
                    'url'   => POD_CUSTOMIZER_URL . 'assets/mockups/tshirt-black.svg',
                    'color' => '#18181b',
                ],
                [
                    'id'    => 'mug_white',
                    'name'  => __('Ceramic Mug', 'pod-customizer'),
                    'url'   => POD_CUSTOMIZER_URL . 'assets/mockups/mug-white.svg',
                    'color' => '#f8fafc',
                ],
            ],
            'cliparts'   => [
                [
                    'id'       => 'clipart_cat',
                    'name'     => __('Playful Cat', 'pod-customizer'),
                    'category' => __('Animals', 'pod-customizer'),
                    'url'      => POD_CUSTOMIZER_URL . 'assets/cliparts/cat.svg',
                ],
                [
                    'id'       => 'clipart_dog',
                    'name'     => __('Happy Dog', 'pod-customizer'),
                    'category' => __('Animals', 'pod-customizer'),
                    'url'      => POD_CUSTOMIZER_URL . 'assets/cliparts/dog.svg',
                ],
                [
                    'id'       => 'clipart_star',
                    'name'     => __('Golden Star', 'pod-customizer'),
                    'category' => __('Shapes', 'pod-customizer'),
                    'url'      => POD_CUSTOMIZER_URL . 'assets/cliparts/star.svg',
                ],
                [
                    'id'       => 'clipart_heart',
                    'name'     => __('Red Heart', 'pod-customizer'),
                    'category' => __('Shapes', 'pod-customizer'),
                    'url'      => POD_CUSTOMIZER_URL . 'assets/cliparts/heart.svg',
                ],
            ],
            'fonts'      => [
                'Roboto',
                'Montserrat',
                'Pacifico',
                'Oswald',
                'Dancing Script',
            ],
            'i18n'       => [
                'defaultText'     => __('Your Custom Text', 'pod-customizer'),
                'emptyTextAlert'  => __('Please enter custom text for your design.', 'pod-customizer'),
                'renderingState'  => __('Packaging design...', 'pod-customizer'),
                'photoUploaded'   => __('Photo loaded successfully', 'pod-customizer'),
                'invalidPhoto'    => __('Please select a valid image file (JPG, PNG, WebP).', 'pod-customizer'),
            ],
        ];

        wp_localize_script('pod-customizer-js', 'podCustomizerConfig', $config);
    }
}
