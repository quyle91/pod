<?php

namespace PodCustomizer\WooCommerce;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class CartHandler
 * Responsible strictly for WooCommerce Cart session and item customizations.
 * Follows Single Responsibility Principle (SRP).
 */
class CartHandler implements HandlerInterface {

    public const META_KEY_STATE = '_pod_canvas_state';
    public const META_KEY_PREVIEW = '_pod_preview_url';

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_custom_data'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'display_custom_data_in_cart'], 10, 2);
    }

    /**
     * Intercept and store customizer state when item is added to cart.
     *
     * @param array $cart_item_data
     * @param int   $product_id
     * @param int   $variation_id
     * @return array
     */
    public function add_cart_item_custom_data(array $cart_item_data, int $product_id, int $variation_id): array {
        if (!isset($_POST['pod_canvas_state']) || empty($_POST['pod_canvas_state'])) {
            return $cart_item_data;
        }

        // Strict validation & sanitization
        $raw_json = wp_unslash($_POST['pod_canvas_state']);
        $decoded = json_decode($raw_json, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $cart_item_data[self::META_KEY_STATE] = $decoded;

            // Generate unique key to prevent merging different personalizations
            $cart_item_data['unique_key'] = md5(microtime() . wp_json_encode($decoded));
        }

        if (isset($_POST['pod_preview_image']) && !empty($_POST['pod_preview_image'])) {
            $cart_item_data[self::META_KEY_PREVIEW] = esc_url_raw(wp_unslash($_POST['pod_preview_image']));
        }

        return $cart_item_data;
    }

    /**
     * Restore customizer data from session into the cart item.
     *
     * @param array $cart_item
     * @param array $values
     * @return array
     */
    public function get_cart_item_from_session(array $cart_item, array $values): array {
        if (isset($values[self::META_KEY_STATE])) {
            $cart_item[self::META_KEY_STATE] = $values[self::META_KEY_STATE];
        }
        if (isset($values[self::META_KEY_PREVIEW])) {
            $cart_item[self::META_KEY_PREVIEW] = $values[self::META_KEY_PREVIEW];
        }
        return $cart_item;
    }

    /**
     * Display customization summary in cart & checkout tables.
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function display_custom_data_in_cart(array $item_data, array $cart_item): array {
        if (!empty($cart_item[self::META_KEY_STATE])) {
            $item_data[] = [
                'key'     => __('Customization', 'pod-customizer'),
                'value'   => __('Custom design configured', 'pod-customizer'),
                'display' => '<span class="badge pod-badge-customized">' . esc_html__('Personalized Design', 'pod-customizer') . '</span>',
            ];
        }
        return $item_data;
    }
}
