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

        register_rest_route(self::NAMESPACE, '/download-production', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_secure_download'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/order-state/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_get_order_state'],
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
        $zip_url   = isset($params['zip_url']) ? esc_url_raw($params['zip_url']) : '';
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

        // Download and persist production files on the WordPress client (wp-content/uploads/pod-prints/)
        if (!empty($print_url)) {
            $local_print_url = \PodCustomizer\Services\PrintStorageManager::store_production_file($order_id, $item_id, $print_url, 'print');
            if ($local_print_url) {
                $print_url = $local_print_url;
            }
        }

        if (!empty($zip_url)) {
            $local_zip_url = \PodCustomizer\Services\PrintStorageManager::store_production_file($order_id, $item_id, $zip_url, 'zip');
            if ($local_zip_url) {
                $zip_url = $local_zip_url;
            }
        }

        // Update item metadata with local client URLs
        $item->update_meta_data(OrderHandler::ORDER_ITEM_META_PRINT_URL, $print_url);
        if (!empty($zip_url)) {
            $item->update_meta_data(OrderHandler::ORDER_ITEM_META_ZIP_URL, $zip_url);
        }
        $item->update_meta_data(OrderHandler::ORDER_ITEM_META_PRINT_STATUS, $status);
        $item->save();

        // Update dedicated custom database table
        \PodCustomizer\Database\Repositories\RenderJobRepository::update_status($item_id, $status, $print_url);

        $order->add_order_note(
            sprintf(__('POD Customizer: Production files stored locally on client (Item #%d). Status: %s', 'pod-customizer'), $item_id, $status)
        );

        return new WP_REST_Response([
            'success'        => true,
            'message'        => 'Production files saved locally on client and order item updated successfully',
            'stored_locally' => true,
            'print_url'      => $print_url,
            'zip_url'        => $zip_url,
        ], 200);
    }

    /**
     * Handle secure signed download for factory production files.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|void
     */
    public function handle_secure_download(WP_REST_Request $request) {
        $order_id = (int) $request->get_param('order_id');
        $item_id  = (int) $request->get_param('item_id');
        $file_type = sanitize_key($request->get_param('type') ?: 'zip');
        $expires  = (int) $request->get_param('expires');
        $sig      = sanitize_text_field($request->get_param('sig'));

        if (!$order_id || !$item_id || !$expires || !$sig) {
            return new WP_REST_Response(['error' => 'Missing required parameters'], 400);
        }

        // Verify HMAC cryptographic signature and expiration
        $is_valid = \PodCustomizer\Services\PrintStorageManager::verify_download_signature(
            $order_id,
            $item_id,
            $file_type,
            $expires,
            $sig
        );

        if (!$is_valid) {
            if ($expires < time()) {
                return new WP_REST_Response(['error' => 'Download link has expired. Please contact merchant for a new work order link.'], 410);
            }
            return new WP_REST_Response(['error' => 'Invalid or forged download signature'], 403);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_REST_Response(['error' => 'Order not found'], 404);
        }

        $item = $order->get_item($item_id);
        if (!$item) {
            return new WP_REST_Response(['error' => 'Order item not found'], 404);
        }

        $meta_key = ($file_type === 'zip') 
            ? \PodCustomizer\WooCommerce\OrderHandler::ORDER_ITEM_META_ZIP_URL 
            : \PodCustomizer\WooCommerce\OrderHandler::ORDER_ITEM_META_PRINT_URL;

        $file_url = $item->get_meta($meta_key);
        if (empty($file_url)) {
            return new WP_REST_Response(['error' => 'File has not been generated yet'], 404);
        }

        // Convert URL to local physical file path
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return new WP_REST_Response(['error' => 'File not found on server storage'], 404);
        }

        // Log audit trail in WooCommerce Order Notes
        $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'Unknown IP';
        $order->add_order_note(
            sprintf(__('POD Customizer: Factory downloaded %s file (Item #%d) via signed link from IP: %s.', 'pod-customizer'), strtoupper($file_type), $item_id, $client_ip)
        );

        // Stream file directly to browser/client with download headers
        $filename = basename($file_path);
        $mime_type = ($file_type === 'zip') ? 'application/zip' : 'image/png';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));

        readfile($file_path);
        exit;
    }

    /**
     * Retrieve order canvas state and line items for test harnesses or external renderers.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_get_order_state(WP_REST_Request $request): WP_REST_Response {
        $order_id = (int) $request->get_param('id');
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => "Order #{$order_id} not found"
            ], 404);
        }

        $items_data = [];
        $recipient_name = trim($order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name());

        foreach ($order->get_items() as $item_id => $item) {
            $raw_state = $item->get_meta(OrderHandler::ORDER_ITEM_META_STATE);
            if (empty($raw_state)) {
                continue;
            }

            $state = json_decode($raw_state, true);
            $preview_raw = $item->get_meta(OrderHandler::ORDER_ITEM_META_PREVIEW_URL);
            $preview_url = !empty($preview_raw) ? OrderHandler::resolve_file_url($preview_raw) : '';
            $print_url = $item->get_meta(OrderHandler::ORDER_ITEM_META_PRINT_URL);
            $zip_url = $item->get_meta(OrderHandler::ORDER_ITEM_META_ZIP_URL);

            $items_data[] = [
                'order_id'       => $order_id,
                'item_id'        => (int) $item_id,
                'product_name'   => $item->get_name(),
                'recipient_name' => $recipient_name,
                'preview_url'    => $preview_url,
                'print_url'      => $print_url,
                'zip_url'        => $zip_url,
                'canvas_state'   => $state,
                'raw_state'      => $raw_state,
            ];
        }

        if (empty($items_data)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => "No custom POD items found in Order #{$order_id}"
            ], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $items_data
        ], 200);
    }
}

