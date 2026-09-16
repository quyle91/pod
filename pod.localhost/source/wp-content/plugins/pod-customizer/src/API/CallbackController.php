<?php

namespace PodCustomizer\API;

use PodCustomizer\Contracts\HandlerInterface;
use PodCustomizer\WooCommerce\OrderHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class CallbackController
 * Exposes REST API endpoint to receive render status updates from the backend worker.
 */
class CallbackController implements HandlerInterface {

    public const NAMESPACE = 'pod-customizer/v1';
    public const ROUTE = '/render-callback';

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * Authenticate incoming callback via shared secret.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_permission(WP_REST_Request $request) {
        $secret = $request->get_header('x-pod-secret');
        $expected = get_option('pod_shared_secret', 'pod_secret_token_123456');

        if (!$secret || $secret !== $expected) {
            return new WP_Error('rest_forbidden', __('Invalid security token', 'pod-customizer'), ['status' => 401]);
        }

        return true;
    }

    /**
     * Handle incoming callback payload and update order item meta.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_callback(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();

        $order_id = isset($params['order_id']) ? (int)$params['order_id'] : 0;
        $item_id  = isset($params['item_id']) ? (int)$params['item_id'] : 0;
        $print_url = isset($params['print_url']) ? esc_url_raw($params['print_url']) : '';
        $status    = isset($params['status']) ? sanitize_text_field($params['status']) : 'completed';

        if (!$order_id || !$item_id) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Missing order_id or item_id'
            ], 422);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Order not found'
            ], 404);
        }

        $item = $order->get_item($item_id);
        if (!$item) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Order line item not found'
            ], 404);
        }

        // Update item metadata
        $item->update_meta_data(OrderHandler::ORDER_ITEM_META_PRINT_URL, $print_url);
        $item->update_meta_data(OrderHandler::ORDER_ITEM_META_PRINT_STATUS, $status);
        $item->save();

        $order->add_order_note(
            sprintf(__('POD Customizer: Print-ready file rendered (Item #%d). Status: %s', 'pod-customizer'), $item_id, $status)
        );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Order item updated successfully'
        ], 200);
    }
}
