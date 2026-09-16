<?php

namespace PodCustomizer\WooCommerce;

use PodCustomizer\Contracts\HandlerInterface;
use WC_Order_Item_Product;

/**
 * Class OrderHandler
 * Responsible strictly for persisting customization data into WooCommerce Orders
 * and providing admin fulfillment actions (Download, Re-render, Email to Factory, Bulk CSV Export).
 */
class OrderHandler implements HandlerInterface {

    public const ORDER_ITEM_META_STATE = '_pod_canvas_state';
    public const ORDER_ITEM_META_PREVIEW_URL = '_pod_preview_url';
    public const ORDER_ITEM_META_PRINT_URL = '_pod_print_ready_url';
    public const ORDER_ITEM_META_ZIP_URL = '_pod_production_zip_url';
    public const ORDER_ITEM_META_PRINT_STATUS = '_pod_print_status';


    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_cart_data_to_order_line_item'], 10, 4);
        add_action('woocommerce_checkout_order_processed', [$this, 'sync_order_items_to_render_jobs'], 10, 3);
        add_action('woocommerce_after_order_itemmeta', [$this, 'display_print_ready_file_in_admin'], 10, 3);
        add_action('woocommerce_order_item_meta_end', [$this, 'display_preview_in_order_details_and_email'], 10, 4);

        // AJAX handlers for Admin Fulfillment
        add_action('wp_ajax_pod_rerender_item', [$this, 'handle_ajax_rerender_item']);
        add_action('wp_ajax_pod_send_printer_email', [$this, 'handle_ajax_send_printer_email']);

        // Bulk Actions in WooCommerce Orders List
        add_filter('bulk_actions-edit-shop_order', [$this, 'register_bulk_actions']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'register_bulk_actions']);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_export_csv'], 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_export_csv'], 10, 3);
    }

    /**
     * Copy custom data from cart session to order item meta upon order creation.
     */
    public function save_cart_data_to_order_line_item(WC_Order_Item_Product $item, string $cart_item_key, array $values, $order): void {
        if (!empty($values[CartHandler::META_KEY_STATE])) {
            $item->add_meta_data(self::ORDER_ITEM_META_STATE, wp_json_encode($values[CartHandler::META_KEY_STATE]), true);
            $item->add_meta_data(self::ORDER_ITEM_META_PRINT_STATUS, 'pending', true);
        }

        if (!empty($values[CartHandler::META_KEY_PREVIEW])) {
            $item->add_meta_data(self::ORDER_ITEM_META_PREVIEW_URL, esc_url_raw($values[CartHandler::META_KEY_PREVIEW]), true);
        }
    }

    /**
     * Synchronize personalized order line items to dedicated pod_render_jobs table.
     */
    public function sync_order_items_to_render_jobs(int $order_id, array $posted_data, \WC_Order $order): void {
        foreach ($order->get_items() as $item_id => $item) {
            $raw_state = $item->get_meta(self::ORDER_ITEM_META_STATE);
            if (empty($raw_state)) {
                continue;
            }

            $preview_url = $item->get_meta(self::ORDER_ITEM_META_PREVIEW_URL);

            \PodCustomizer\Database\Repositories\RenderJobRepository::create_or_update([
                'order_id'       => $order_id,
                'order_item_id'  => $item_id,
                'status'         => 'pending',
                'preview_url'    => $preview_url,
                'canvas_payload' => $raw_state,
                'attempts'       => 0,
            ]);

            if (!empty($preview_url)) {
                \PodCustomizer\Database\Repositories\PreviewFileRepository::link_order_item($preview_url, $item_id);
            }
        }
    }

    /**
     * Resolve a file URL, prepending the configured Backend URL if the path is relative.
     *
     * @param string|null $url
     * @return string
     */
    public static function resolve_file_url(?string $url): string {
        if (empty($url)) {
            return '';
        }

        $backend_url = rtrim(get_option('pod_backend_url', ''), '/');

        // Rewrite any internal Docker container URLs (e.g. http://pod_backend:3001) to the public Backend URL
        if (strpos($url, 'http://pod_backend') !== false || strpos($url, 'http://pod-backend:3001') !== false) {
            $parsed_path = wp_parse_url($url, PHP_URL_PATH);
            return $backend_url ? $backend_url . $parsed_path : $url;
        }

        // If it's a local relative upload path, prepend site URL
        if (strpos($url, '/wp-content') === 0) {
            return site_url($url);
        }

        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }

        return $backend_url ? $backend_url . '/' . ltrim($url, '/') : $url;
    }

    /**
     * Display custom design preview on Order Received page and in customer/admin emails.
     * Rendered as: Customization: [preview thumbnail]
     */
    public function display_preview_in_order_details_and_email(int $item_id, $item, $order, bool $plain_text = false): void {
        if ($plain_text) {
            return;
        }

        $preview_url = $item->get_meta(self::ORDER_ITEM_META_PREVIEW_URL);
        $print_url = $item->get_meta(self::ORDER_ITEM_META_PRINT_URL);
        $target_url = self::resolve_file_url($preview_url ?: $print_url);
        if (!$target_url) {
            return;
        }

        echo '<div class="pod-order-preview-box" style="margin-top: 4px; margin-bottom: 4px; font-size: 13px; color: #475569;">';
        echo '<span style="font-weight: 600;">' . esc_html__('Customization:', 'pod-customizer') . ' </span>';
        echo '<a href="' . esc_url($target_url) . '" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline; font-weight: 500;">' . esc_html__('[preview]', 'pod-customizer') . '</a>';
        echo '</div>';
    }

    /**
     * Display visual preview, 300 DPI download, Re-render, and Email-to-Factory box in Admin Order View.
     */
    public function display_print_ready_file_in_admin(int $item_id, $item, $product): void {
        $preview_url = self::resolve_file_url($item->get_meta(self::ORDER_ITEM_META_PREVIEW_URL));
        $print_url = self::resolve_file_url($item->get_meta(self::ORDER_ITEM_META_PRINT_URL));
        $zip_url = self::resolve_file_url($item->get_meta(self::ORDER_ITEM_META_ZIP_URL));
        $status = $item->get_meta(self::ORDER_ITEM_META_PRINT_STATUS);
        $raw_state = $item->get_meta(self::ORDER_ITEM_META_STATE);

        if (!$status && !$print_url && !$preview_url && !$raw_state) {
            return;
        }

        $order = $item->get_order();
        $order_id = $order ? $order->get_id() : 0;
        $default_printer_email = get_option('pod_default_printer_email', '');
        $nonce = wp_create_nonce('pod_admin_action_' . $item_id);

        echo '<div id="pod-fulfillment-box-' . esc_attr($item_id) . '" style="margin-top: 12px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #4f46e5; border-radius: 6px;">';
        echo '<div style="display: flex; gap: 16px; align-items: flex-start;">';

        // 1. Visual Preview Thumbnail
        if ($preview_url) {
            echo '<div style="flex-shrink: 0;">';
            echo '<a href="' . esc_url($preview_url) . '" target="_blank" title="' . esc_attr__('Click to enlarge design preview', 'pod-customizer') . '">';
            echo '<img src="' . esc_url($preview_url) . '" style="width: 95px; height: 95px; object-fit: contain; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 3px;" alt="Custom Design Preview" />';
            echo '</a>';
            echo '</div>';
        }

        // 2. Details & Action Controls
        echo '<div style="flex-grow: 1;">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">';
        echo '<div style="font-weight: 700; font-size: 13px; color: #1e293b;">🎨 ' . esc_html__('POD Personalization & Fulfillment', 'pod-customizer') . '</div>';
        
        // Status Badge
        $status_color = ($status === 'completed' || $status === 'success') ? '#15803d' : (($status === 'processing') ? '#0284c7' : '#b45309');
        $status_bg = ($status === 'completed' || $status === 'success') ? '#dcfce7' : (($status === 'processing') ? '#e0f2fe' : '#fef3c7');
        echo '<span style="display: inline-block; padding: 3px 9px; border-radius: 9999px; font-weight: 600; font-size: 11px; background:' . $status_bg . '; color:' . $status_color . ';">' . esc_html(strtoupper($status ?: 'PENDING')) . '</span>';
        echo '</div>';

        // Actions Row: Download & Re-render
        echo '<div style="display: flex; gap: 8px; align-items: center; margin-bottom: 10px; flex-wrap: wrap;">';
        if ($zip_url) {
            echo '<a href="' . esc_url($zip_url) . '" target="_blank" class="button button-primary button-small" style="font-weight: 600; background: #059669; border-color: #047857;">';
            echo '📦 ' . esc_html__('Download Production ZIP', 'pod-customizer') . '</a>';
        } elseif ($print_url) {
            echo '<a href="' . esc_url($print_url) . '" target="_blank" class="button button-primary button-small" style="font-weight: 600;">';
            echo '⬇️ ' . esc_html__('Download 300 DPI Print File', 'pod-customizer') . '</a>';
        }

        echo '<button type="button" class="button button-secondary button-small pod-rerender-btn" data-order-id="' . esc_attr($order_id) . '" data-item-id="' . esc_attr($item_id) . '" data-nonce="' . esc_attr($nonce) . '">';
        echo '🔄 ' . esc_html__('Re-render', 'pod-customizer') . '</button>';
        echo '</div>';

        // 3. Email to Factory Section (Editable Input + Send Button)
        echo '<div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #cbd5e1;">';
        echo '<label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">' . esc_html__('Send to Printer / Factory:', 'pod-customizer') . '</label>';
        echo '<div style="display: flex; gap: 6px; align-items: center;">';
        echo '<input type="email" id="pod-printer-email-' . esc_attr($item_id) . '" value="' . esc_attr($default_printer_email) . '" placeholder="printer@factory.com" style="width: 220px; font-size: 12px; height: 30px;" />';
        echo '<button type="button" class="button button-secondary button-small pod-send-email-btn" data-order-id="' . esc_attr($order_id) . '" data-item-id="' . esc_attr($item_id) . '" data-nonce="' . esc_attr($nonce) . '">';
        echo '✉️ ' . esc_html__('Send Email', 'pod-customizer') . '</button>';
        echo '<span id="pod-action-feedback-' . esc_attr($item_id) . '" style="font-size: 11px; margin-left: 6px;"></span>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // end flex-grow
        echo '</div>'; // end flex
        echo '</div>'; // end container

        // Enqueue inline AJAX script once
        static $script_printed = false;
        if (!$script_printed) {
            $script_printed = true;
            $this->render_admin_inline_script();
        }
    }

    /**
     * Render inline JavaScript for AJAX Re-render and Factory Email dispatch.
     */
    private function render_admin_inline_script(): void {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Re-render button handler
            $(document).on('click', '.pod-rerender-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var orderId = $btn.data('order-id');
                var itemId = $btn.data('item-id');
                var nonce = $btn.data('nonce');
                var $feedback = $('#pod-action-feedback-' + itemId);

                $btn.prop('disabled', true).text('⏳ Rendering...');
                $feedback.text('').css('color', '#475569');

                $.post(ajaxurl, {
                    action: 'pod_rerender_item',
                    order_id: orderId,
                    item_id: itemId,
                    nonce: nonce
                }, function(res) {
                    $btn.prop('disabled', false).text('🔄 Re-render');
                    if (res.success) {
                        $feedback.text('✅ ' + res.data.message).css('color', '#15803d');
                        setTimeout(function() { window.location.reload(); }, 1200);
                    } else {
                        $feedback.text('❌ ' + (res.data.error || 'Failed')).css('color', '#b91c1c');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('🔄 Re-render');
                    $feedback.text('❌ Server error').css('color', '#b91c1c');
                });
            });

            // Send to Printer Email handler
            $(document).on('click', '.pod-send-email-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var orderId = $btn.data('order-id');
                var itemId = $btn.data('item-id');
                var nonce = $btn.data('nonce');
                var email = $('#pod-printer-email-' + itemId).val();
                var $feedback = $('#pod-action-feedback-' + itemId);

                if (!email) {
                    alert('Please enter a valid factory email address.');
                    return;
                }

                $btn.prop('disabled', true).text('⏳ Sending...');
                $feedback.text('').css('color', '#475569');

                $.post(ajaxurl, {
                    action: 'pod_send_printer_email',
                    order_id: orderId,
                    item_id: itemId,
                    printer_email: email,
                    nonce: nonce
                }, function(res) {
                    $btn.prop('disabled', false).text('✉️ Send Email');
                    if (res.success) {
                        $feedback.text('✅ ' + res.data.message).css('color', '#15803d');
                    } else {
                        $feedback.text('❌ ' + (res.data.error || 'Failed')).css('color', '#b91c1c');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('✉️ Send Email');
                    $feedback.text('❌ Network error').css('color', '#b91c1c');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to immediately re-dispatch a single item for rendering.
     */
    public function handle_ajax_rerender_item(): void {
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        if (!wp_verify_nonce($nonce, 'pod_admin_action_' . $item_id) || !current_user_can('edit_shop_orders')) {
            wp_send_json_error(['error' => 'Permission denied'], 403);
        }

        $dispatcher = new OrderWebhookDispatcher();
        $result = $dispatcher->dispatch_item($order_id, $item_id);

        if (!empty($result['success'])) {
            wp_send_json_success(['message' => 'Render task dispatched to backend worker!']);
        } else {
            wp_send_json_error(['error' => $result['error'] ?? 'Render request failed']);
        }
    }

    /**
     * AJAX handler to email print file & specs directly to factory/printer.
     */
    public function handle_ajax_send_printer_email(): void {
        $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
        $printer_email = isset($_POST['printer_email']) ? sanitize_email($_POST['printer_email']) : '';
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        if (!wp_verify_nonce($nonce, 'pod_admin_action_' . $item_id) || !current_user_can('edit_shop_orders')) {
            wp_send_json_error(['error' => 'Permission denied'], 403);
        }

        if (!is_email($printer_email)) {
            wp_send_json_error(['error' => 'Invalid email address']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['error' => 'Order not found']);
        }

        $item = $order->get_item($item_id);
        if (!$item) {
            wp_send_json_error(['error' => 'Line item not found']);
        }

        $print_url = self::resolve_file_url($item->get_meta(self::ORDER_ITEM_META_PRINT_URL));
        $zip_url = self::resolve_file_url($item->get_meta(self::ORDER_ITEM_META_ZIP_URL));
        $preview_url = self::resolve_file_url($item->get_meta(self::ORDER_ITEM_META_PREVIEW_URL));

        $product_name = $item->get_name();
        $qty = $item->get_quantity();
        /** @var \WC_Order_Item_Product $item */
        $product = ($item instanceof \WC_Order_Item_Product) ? $item->get_product() : null;
        $sku = $product ? $product->get_sku() : '';

        // Extract variations/attributes (Size, Color, etc.)
        $variations = [];
        foreach ($item->get_formatted_meta_data() as $meta) {
            if (strpos($meta->key, '_pod_') === 0 || strpos($meta->key, 'unique_key') === 0) {
                continue;
            }
            $variations[] = esc_html($meta->display_key) . ': <strong>' . wp_kses_post(strip_tags($meta->display_value)) . '</strong>';
        }
        $variation_html = !empty($variations) ? implode(' | ', $variations) : esc_html__('Standard / None', 'pod-customizer');

        // Shipping & Recipient Details
        $shipping_name = $order->get_formatted_shipping_full_name() ?: $order->get_formatted_billing_full_name();
        $shipping_address = $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address();
        $shipping_phone = $order->get_billing_phone();
        $customer_note = $order->get_customer_note();
        $order_date = $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')) : date('Y-m-d H:i:s');

        $subject = sprintf('[POD Production Order #%d] %s (Qty: %d)', $order_id, $product_name, $qty);

        // Build Professional Work Order HTML Body
        $message  = '<div style="font-family: Arial, Helvetica, sans-serif; max-width: 650px; margin: 0 auto; color: #1e293b; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">';
        
        // 1. Header Banner
        $message .= '<div style="background: #1e293b; color: #ffffff; padding: 20px 24px;">';
        $message .= '<h1 style="margin: 0 0 6px 0; font-size: 20px; font-weight: 700; color: #ffffff;">🏭 ' . esc_html__('POD Production Work Order', 'pod-customizer') . '</h1>';
        $message .= '<p style="margin: 0; font-size: 13px; color: #94a3b8;">' . esc_html__('Order ID:', 'pod-customizer') . ' <strong>#' . esc_html($order_id) . '</strong> &bull; ' . esc_html__('Date:', 'pod-customizer') . ' ' . esc_html($order_date) . '</p>';
        $message .= '</div>';

        $message .= '<div style="padding: 24px;">';

        // 2. Product & Production Specs Table
        $message .= '<h2 style="font-size: 15px; margin: 0 0 12px 0; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">📋 ' . esc_html__('1. Product & Production Specs', 'pod-customizer') . '</h2>';
        $message .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">';
        $message .= '<tr><td style="padding: 8px 12px; background: #f8fafc; font-weight: 600; width: 35%; border: 1px solid #e2e8f0;">' . esc_html__('Product Name', 'pod-customizer') . '</td><td style="padding: 8px 12px; border: 1px solid #e2e8f0;"><strong>' . esc_html($product_name) . '</strong></td></tr>';
        if ($sku) {
            $message .= '<tr><td style="padding: 8px 12px; background: #f8fafc; font-weight: 600; border: 1px solid #e2e8f0;">' . esc_html__('SKU / Phôi', 'pod-customizer') . '</td><td style="padding: 8px 12px; border: 1px solid #e2e8f0;"><code>' . esc_html($sku) . '</code></td></tr>';
        }
        $message .= '<tr><td style="padding: 8px 12px; background: #f8fafc; font-weight: 600; border: 1px solid #e2e8f0;">' . esc_html__('Quantity', 'pod-customizer') . '</td><td style="padding: 8px 12px; border: 1px solid #e2e8f0;"><span style="font-size: 16px; font-weight: 700; color: #dc2626;">' . esc_html($qty) . ' ' . esc_html__('pcs', 'pod-customizer') . '</span></td></tr>';
        $message .= '<tr><td style="padding: 8px 12px; background: #f8fafc; font-weight: 600; border: 1px solid #e2e8f0;">' . esc_html__('Attributes / Variations', 'pod-customizer') . '</td><td style="padding: 8px 12px; border: 1px solid #e2e8f0;">' . $variation_html . '</td></tr>';
        $message .= '<tr><td style="padding: 8px 12px; background: #f8fafc; font-weight: 600; border: 1px solid #e2e8f0;">' . esc_html__('Print Standard', 'pod-customizer') . '</td><td style="padding: 8px 12px; border: 1px solid #e2e8f0;">300 DPI Transparent PNG (sRGB)</td></tr>';
        $message .= '</table>';

        // 3. Production Files & Download Section
        $message .= '<h2 style="font-size: 15px; margin: 24px 0 12px 0; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">📦 ' . esc_html__('2. Production Files & Downloads', 'pod-customizer') . '</h2>';
        $message .= '<div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 18px; margin-bottom: 20px;">';
        
        if ($zip_url) {
            $message .= '<div style="margin-bottom: 14px;">';
            $message .= '<a href="' . esc_url($zip_url) . '" target="_blank" style="display: inline-block; padding: 12px 24px; background: #059669; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
            $message .= '📦 ' . esc_html__('DOWNLOAD COMPLETE PRODUCTION PACKAGE (ZIP)', 'pod-customizer') . '</a>';
            $message .= '<div style="font-size: 12px; color: #64748b; margin-top: 6px;">' . esc_html__('Includes: 300 DPI PNG, Mockup Preview, Raw Assets, and Production Specs.', 'pod-customizer') . '<br /><a href="' . esc_url($zip_url) . '" style="color:#059669; word-break:break-all;">' . esc_url($zip_url) . '</a></div>';
            $message .= '</div>';
        }

        if ($print_url) {
            $message .= '<div>';
            $message .= '<a href="' . esc_url($print_url) . '" target="_blank" style="display: inline-block; padding: 9px 18px; background: #4f46e5; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px;">';
            $message .= '⬇️ ' . esc_html__('Download Standalone 300 DPI Print File (PNG)', 'pod-customizer') . '</a>';
            $message .= '<div style="font-size: 11px; color: #64748b; margin-top: 4px;"><a href="' . esc_url($print_url) . '" style="color:#4f46e5; word-break:break-all;">' . esc_url($print_url) . '</a></div>';
            $message .= '</div>';
        }
        $message .= '</div>';

        // 4. Finished Mockup Preview
        if ($preview_url) {
            $message .= '<h2 style="font-size: 15px; margin: 24px 0 12px 0; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">👁️ ' . esc_html__('3. Finished Mockup Preview (Visual Check)', 'pod-customizer') . '</h2>';
            $message .= '<div style="text-align: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 20px;">';
            $message .= '<a href="' . esc_url($preview_url) . '" target="_blank" title="' . esc_attr__('Click to enlarge', 'pod-customizer') . '">';
            $message .= '<img src="' . esc_url($preview_url) . '" alt="Mockup Preview" style="max-width: 320px; width: 100%; height: auto; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; padding: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);" />';
            $message .= '</a>';
            $message .= '<div style="font-size: 12px; color: #64748b; margin-top: 6px;">' . esc_html__('Use this mockup to verify position, scaling, and alignment on blank product.', 'pod-customizer') . '</div>';
            $message .= '</div>';
        }

        // 5. Shipping & Recipient Details
        $message .= '<h2 style="font-size: 15px; margin: 24px 0 12px 0; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">🚚 ' . esc_html__('4. Delivery & Recipient Info', 'pod-customizer') . '</h2>';
        $message .= '<table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 10px;">';
        $message .= '<tr><td style="padding: 8px 12px; background: #f8fafc; font-weight: 600; width: 35%; border: 1px solid #e2e8f0;">' . esc_html__('Recipient Name', 'pod-customizer') . '</td><td style="padding: 8px 12px; border: 1px solid #e2e8f0;">' . esc_html($shipping_name ?: 'N/A') . '</td></tr>';
        if ($shipping_phone) {
            $message .= '<tr><td style="padding: 8px 12px; background: #f8fafc; font-weight: 600; border: 1px solid #e2e8f0;">' . esc_html__('Phone Number', 'pod-customizer') . '</td><td style="padding: 8px 12px; border: 1px solid #e2e8f0;"><strong>' . esc_html($shipping_phone) . '</strong></td></tr>';
        }
        $message .= '<tr><td style="padding: 8px 12px; background: #f8fafc; font-weight: 600; border: 1px solid #e2e8f0;">' . esc_html__('Shipping Address', 'pod-customizer') . '</td><td style="padding: 8px 12px; border: 1px solid #e2e8f0;">' . ($shipping_address ?: 'N/A') . '</td></tr>';
        if ($customer_note) {
            $message .= '<tr><td style="padding: 8px 12px; background: #fef3c7; font-weight: 600; color: #92400e; border: 1px solid #e2e8f0;">' . esc_html__('Customer Note', 'pod-customizer') . '</td><td style="padding: 8px 12px; background: #fffbeb; color: #92400e; border: 1px solid #e2e8f0;">' . esc_html($customer_note) . '</td></tr>';
        }
        $message .= '</table>';

        $message .= '</div>'; // End padding

        // 6. Footer
        $message .= '<div style="background: #f1f5f9; padding: 14px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0;">';
        $message .= esc_html__('Automated dispatch by POD Customizer Engine &bull; All rights reserved.', 'pod-customizer');
        $message .= '</div>';

        $message .= '</div>'; // End container

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($printer_email, $subject, $message, $headers);

        if ($sent) {
            $order->add_order_note(
                sprintf(__('POD Customizer: Production Work Order sent to factory email (%s) for Item #%d.', 'pod-customizer'), $printer_email, $item_id)
            );
            wp_send_json_success(['message' => 'Production Work Order email sent to factory successfully!']);
        } else {
            wp_send_json_error(['error' => 'wp_mail failed to send email']);
        }
    }

    /**
     * Register bulk action in WooCommerce orders list.
     */
    public function register_bulk_actions(array $actions): array {
        $actions['pod_export_print_csv'] = __('Export POD Print Files (CSV)', 'pod-customizer');
        return $actions;
    }

    /**
     * Handle bulk CSV export of 300 DPI print links.
     */
    public function handle_bulk_export_csv(string $redirect_to, string $action, array $post_ids): string {
        if ($action !== 'pod_export_print_csv' || empty($post_ids)) {
            return $redirect_to;
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=pod_print_export_' . date('Y-m-d_His') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // CSV Header
        fputcsv($output, ['Order ID', 'Item ID', 'Product Name', 'Quantity', 'Status', 'Print File 300 DPI URL', 'Preview Mockup URL']);

        foreach ($post_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }

            foreach ($order->get_items() as $item_id => $item) {
                $print_url = self::resolve_file_url($item->get_meta(self::ORDER_ITEM_META_PRINT_URL));
                if (empty($print_url)) {
                    continue; // Skip non-customized or pending items
                }

                $preview_url = self::resolve_file_url($item->get_meta(self::ORDER_ITEM_META_PREVIEW_URL));
                $status = $item->get_meta(self::ORDER_ITEM_META_PRINT_STATUS) ?: 'completed';

                fputcsv($output, [
                    $order_id,
                    $item_id,
                    $item->get_name(),
                    $item->get_quantity(),
                    $status,
                    $print_url,
                    $preview_url
                ]);
            }
        }

        fclose($output);
        exit;
    }
}
