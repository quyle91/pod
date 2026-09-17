<?php

namespace PodCustomizer\WooCommerce;

use PodCustomizer\Contracts\HandlerInterface;
use PodCustomizer\Contracts\DispatcherInterface;

/**
 * Class OrderWebhookDispatcher
 * Listens for order status changes and dispatches render tasks to backend worker.
 */
class OrderWebhookDispatcher implements HandlerInterface, DispatcherInterface {

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('woocommerce_order_status_processing', [$this, 'on_order_processing'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'on_order_processing'], 10, 1);
        add_action('woocommerce_order_status_on-hold', [$this, 'on_order_processing'], 10, 1);
    }

    /**
     * Triggered when an order status changes to processing.
     *
     * @param int $order_id
     * @return void
     */
    public function on_order_processing(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $raw_state = $item->get_meta(OrderHandler::ORDER_ITEM_META_STATE);
            $print_status = $item->get_meta(OrderHandler::ORDER_ITEM_META_PRINT_STATUS);

            // Only dispatch if customized and not yet processed
            if (!empty($raw_state) && $print_status !== 'completed') {
                $this->dispatch_item($order_id, (int)$item_id);
            }
        }
    }

    /**
     * Dispatch a single line item render task to backend worker.
     *
     * @param int $order_id
     * @param int $item_id
     * @return array
     */
    public function dispatch_item(int $order_id, int $item_id): array {
        $order = wc_get_order($order_id);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        $item = $order->get_item($item_id);
        if (!$item) {
            return ['success' => false, 'error' => 'Order item not found'];
        }

        $raw_state = $item->get_meta(OrderHandler::ORDER_ITEM_META_STATE);
        if (empty($raw_state)) {
            return ['success' => false, 'error' => 'No customization state found on line item'];
        }

        $canvas_state = json_decode($raw_state, true);
        if (!is_array($canvas_state)) {
            return ['success' => false, 'error' => 'Invalid JSON in canvas state'];
        }

        // Update status to processing in DB
        $item->update_meta_data(OrderHandler::ORDER_ITEM_META_PRINT_STATUS, 'processing');
        $item->save();
        \PodCustomizer\Database\Repositories\RenderJobRepository::update_status($item_id, 'processing');

        return $this->dispatch($order_id, $item_id, $canvas_state);
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(int $order_id, int $item_id, array $canvas_state): array {
        $backend_url = rtrim(get_option('pod_backend_url', ''), '/');
        $shared_secret = get_option('pod_shared_secret', 'pod_secret_token_123456');

        if (empty($backend_url)) {
            error_log('[POD Customizer] Webhook dispatch aborted: Backend URL is not configured in settings.');
            \PodCustomizer\Database\Repositories\RenderJobRepository::update_status($item_id, 'failed', null, 'Backend URL not configured in settings');
            return [
                'success' => false,
                'error'   => __('Render Backend URL is not configured in settings.', 'pod-customizer'),
            ];
        }

        $endpoint = $backend_url . '/api/v1/render';
        $callback_url = rest_url('pod-customizer/v1/render-callback');

        $order = wc_get_order($order_id);
        $item = $order ? $order->get_item($item_id) : null;
        $product_name = $item ? $item->get_name() : '';
        $recipient_name = $order ? trim($order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name()) : '';
        $preview_raw = $item ? $item->get_meta(OrderHandler::ORDER_ITEM_META_PREVIEW_URL) : '';
        $preview_url = !empty($preview_raw) ? OrderHandler::resolve_file_url($preview_raw) : '';

        $site_host = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'pod.localhost';
        $domain_slug = str_replace('.', '_', $site_host);

        $payload = [
            'order_id'       => $order_id,
            'item_id'        => $item_id,
            'domain_name'    => $domain_slug,
            'recipient_name' => $recipient_name,
            'product_name'   => $product_name,
            'preview_url'    => $preview_url,
            'canvas'         => $canvas_state['canvas'] ?? ['width' => 600, 'height' => 600],
            'layers'         => $canvas_state['layers'] ?? [],
            'callback_url'   => $callback_url,
        ];

        // Allow filters on outgoing payload (Open/Closed Principle)
        $payload = apply_filters('pod_customizer_outgoing_render_payload', $payload, $order_id, $item_id);

        $response = wp_remote_post($endpoint, [
            'method'      => 'POST',
            'timeout'     => 15,
            'headers'     => [
                'Content-Type'  => 'application/json',
                'X-POD-SECRET'  => $shared_secret,
            ],
            'body'        => wp_json_encode($payload),
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            error_log('[POD Customizer] Webhook dispatch failed: ' . $response->get_error_message());
            \PodCustomizer\Database\Repositories\RenderJobRepository::update_status($item_id, 'failed', null, $response->get_error_message());
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['success' => true, 'raw' => $body];
    }
}
