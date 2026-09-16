<?php

namespace PodCustomizer\WooCommerce;

use PodCustomizer\Contracts\HandlerInterface;
use WC_Order_Item_Product;

/**
 * Class OrderHandler
 * Responsible strictly for persisting customization data into WooCommerce Orders.
 */
class OrderHandler implements HandlerInterface {

    public const ORDER_ITEM_META_STATE = '_pod_canvas_state';
    public const ORDER_ITEM_META_PRINT_URL = '_pod_print_ready_url';
    public const ORDER_ITEM_META_PRINT_STATUS = '_pod_print_status';

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_cart_data_to_order_line_item'], 10, 4);
        add_action('woocommerce_after_order_itemmeta', [$this, 'display_print_ready_file_in_admin'], 10, 3);
    }

    /**
     * Copy custom data from cart session to order item meta upon order creation.
     *
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     * @param \WC_Order             $order
     * @return void
     */
    public function save_cart_data_to_order_line_item(WC_Order_Item_Product $item, string $cart_item_key, array $values, $order): void {
        if (!empty($values[CartHandler::META_KEY_STATE])) {
            $item->add_meta_data(self::ORDER_ITEM_META_STATE, wp_json_encode($values[CartHandler::META_KEY_STATE]), true);
            $item->add_meta_data(self::ORDER_ITEM_META_PRINT_STATUS, 'pending', true);
        }
    }

    /**
     * Render high-resolution print file download button in Admin Order edit view.
     *
     * @param int                   $item_id
     * @param WC_Order_Item_Product $item
     * @param \WC_Product|bool      $product
     * @return void
     */
    public function display_print_ready_file_in_admin(int $item_id, $item, $product): void {
        $print_url = $item->get_meta(self::ORDER_ITEM_META_PRINT_URL);
        $status = $item->get_meta(self::ORDER_ITEM_META_PRINT_STATUS);

        if (!$status && !$print_url) {
            return;
        }

        echo '<div style="margin-top: 8px; padding: 6px 10px; background: #f0f6fc; border-left: 3px solid #2271b1; border-radius: 4px;">';
        echo '<strong>' . esc_html__('POD Print Engine:', 'pod-customizer') . '</strong> ';

        if ($print_url) {
            echo '<a href="' . esc_url($print_url) . '" target="_blank" class="button button-small button-primary" style="margin-left: 8px;">' .
                 esc_html__('Download 300 DPI Print File', 'pod-customizer') . '</a>';
        } else {
            echo '<span style="color: #d63638; font-weight: 600;">' . esc_html(sprintf(__('Status: %s', 'pod-customizer'), ucfirst($status ?: 'pending'))) . '</span>';
        }
        echo '</div>';
    }
}
