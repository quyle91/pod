<?php

namespace PodCustomizer\WooCommerce;

use PodCustomizer\Contracts\HandlerInterface;
use PodCustomizer\Services\PreviewStorageManager;

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
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_backend_availability'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_custom_data'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'display_custom_data_in_cart'], 10, 2);
        add_action('wp_head', [$this, 'inject_cart_customization_styles']);
        add_action('wp_footer', [$this, 'inject_cart_refresh_script']);
    }

    /**
     * Validate backend service availability before adding personalized product to cart.
     * Prevents orders when the render engine is offline or unreachable.
     *
     * @param bool $passed
     * @param int  $product_id
     * @param int  $quantity
     * @return bool
     */
    public function validate_backend_availability(bool $passed, int $product_id, int $quantity): bool {
        // If non-customized product or already failed prior validation, pass through
        if (!$passed || !isset($_POST['pod_canvas_state']) || empty($_POST['pod_canvas_state'])) {
            return $passed;
        }

        $backend_url = rtrim(get_option('pod_backend_url', ''), '/');
        if (empty($backend_url)) {
            wc_add_notice(
                __('Dịch vụ tùy biến sản phẩm chưa được cấu hình máy chủ kết nối. Vui lòng liên hệ quản trị viên.', 'pod-customizer'),
                'error'
            );
            return false;
        }

        // Fast health check ping (timeout 2 seconds)
        $health_endpoint = $backend_url . '/health';
        $response = wp_remote_get($health_endpoint, [
            'timeout'   => 2,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            error_log('[POD Customizer] Health check failed on Add to Cart: ' . $response->get_error_message());
            wc_add_notice(
                __('Dịch vụ tùy biến in ấn tạm thời gián đoạn (máy chủ render đang bảo trì hoặc mất kết nối). Vui lòng thử lại sau ít phút.', 'pod-customizer'),
                'error'
            );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('[POD Customizer] Health check returned non-200 code: ' . $status_code);
            wc_add_notice(
                __('Dịch vụ tùy biến in ấn tạm thời không sẵn sàng. Vui lòng thử lại sau.', 'pod-customizer'),
                'error'
            );
            return false;
        }

        return true;
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

        // Handle Preview Image (decode Base64 to physical file or sanitize URL)
        if (!empty($_POST['pod_preview_image'])) {
            $raw_preview = wp_unslash($_POST['pod_preview_image']);
            if (strpos($raw_preview, 'data:image/') === 0) {
                $saved_url = PreviewStorageManager::save_base64_image($raw_preview);
                if ($saved_url) {
                    $cart_item_data[self::META_KEY_PREVIEW] = $saved_url;
                }
            } else {
                $cart_item_data[self::META_KEY_PREVIEW] = esc_url_raw($raw_preview);
            }
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
     * Replace product thumbnail in Cart table and Mini-Cart with customized canvas preview.
     *
     * @param string $thumbnail
     * @param array  $cart_item
     * @param string $cart_item_key
     * @return string
     */
    public function customize_cart_item_thumbnail(string $thumbnail, array $cart_item, string $cart_item_key): string {
        if (!empty($cart_item[self::META_KEY_PREVIEW])) {
            return sprintf(
                '<img src="%s" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail pod-cart-preview-thumb" alt="%s" style="object-fit:contain; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0; width:80px; height:80px;" />',
                esc_url($cart_item[self::META_KEY_PREVIEW]),
                esc_attr__('Customized Design', 'pod-customizer')
            );
        }
        return $thumbnail;
    }

    /**
     * Display customization summary in cart & checkout tables and mini-cart.
     * Shows a single clean row: Customization: [preview thumbnail]
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function display_custom_data_in_cart(array $item_data, array $cart_item): array {
        if (!empty($cart_item[self::META_KEY_PREVIEW])) {
            $preview_url = esc_url($cart_item[self::META_KEY_PREVIEW]);
            $item_data[] = [
                'key'     => __('Customization', 'pod-customizer'),
                'value'   => '[preview]',
                'display' => sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer" class="pod-preview-link" style="color: #2563eb; text-decoration: underline; font-weight: 500;">%s</a>',
                    $preview_url,
                    esc_html__('[preview]', 'pod-customizer')
                ),
            ];
        } elseif (!empty($cart_item[self::META_KEY_STATE])) {
            $item_data[] = [
                'key'     => __('Customization', 'pod-customizer'),
                'value'   => '[preview]',
                'display' => '<span class="badge pod-badge-customized" style="font-size: 11px; padding: 2px 6px; background: #e0e7ff; color: #4338ca; border-radius: 4px; font-weight: 600;">' . esc_html__('[customized]', 'pod-customizer') . '</span>',
            ];
        }

        return $item_data;
    }

    /**
     * Inject inline CSS to ensure cart and mini-cart variations render on a single clean line.
     * Prevents Flatsome theme styles from breaking layout in mini-cart dropdown.
     */
    public function inject_cart_customization_styles(): void {
        ?>
        <style id="pod-cart-customization-style">
            .woocommerce-mini-cart-item dl.variation,
            .widget_shopping_cart dl.variation {
                display: block !important;
                margin: 2px 0 4px 0 !important;
                font-size: 12px !important;
                line-height: 1.4 !important;
            }
            .woocommerce-mini-cart-item dl.variation dt,
            .widget_shopping_cart dl.variation dt {
                float: none !important;
                clear: none !important;
                display: inline !important;
                font-weight: 600 !important;
                margin-right: 4px !important;
            }
            .woocommerce-mini-cart-item dl.variation dd,
            .widget_shopping_cart dl.variation dd {
                display: inline !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .woocommerce-mini-cart-item dl.variation dd p,
            .widget_shopping_cart dl.variation dd p {
                display: inline !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .pod-preview-link {
                color: #2563eb !important;
                text-decoration: underline !important;
                font-weight: 500 !important;
                display: inline !important;
            }
            .pod-preview-link:hover {
                color: #1d4ed8 !important;
            }
        </style>
        <?php
    }

    /**
     * Invalidate stale WooCommerce mini-cart sessionStorage fragments on client-side.
     */
    public function inject_cart_refresh_script(): void {
        ?>
        <script type="text/javascript" id="pod-cart-refresh-script">
        (function() {
            if (typeof window.sessionStorage !== 'undefined') {
                try {
                    var flag = 'pod_mini_cart_refreshed_v4';
                    if (!sessionStorage.getItem(flag)) {
                        sessionStorage.setItem(flag, '1');
                        if (typeof wc_cart_fragments_params !== 'undefined') {
                            sessionStorage.removeItem(wc_cart_fragments_params.fragment_name);
                            sessionStorage.removeItem(wc_cart_fragments_params.cart_hash_key);
                        }
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document).ready(function($) {
                                $(document.body).trigger('wc_fragment_refresh');
                            });
                        }
                    }
                } catch(e) {}
            }
        })();
        </script>
        <?php
    }
}
