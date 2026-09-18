<?php

namespace PodCustomizer\Admin;

use PodCustomizer\Contracts\HandlerInterface;
use PodCustomizer\Services\PreviewStorageManager;

/**
 * Class SettingsPage
 * Provides administration interface for configuring POD Backend connection settings
 * and managing preview image storage.
 */
class SettingsPage implements HandlerInterface {

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_pod_manual_cleanup', [$this, 'handle_manual_cleanup']);
        add_action('wp_ajax_pod_test_backend_connection', [$this, 'handle_ajax_test_connection']);
    }

    /**
     * Register settings submenu under native WordPress Settings (options-general.php).
     */
    public function add_settings_menu(): void {
        add_options_page(
            __('POD Customizer Settings', 'pod-customizer'),
            __('POD Customizer', 'pod-customizer'),
            'manage_options',
            'pod-customizer-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings and fields.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting('pod_customizer_options', 'pod_backend_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);

        register_setting('pod_customizer_options', 'pod_shared_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'pod_secret_token_123456',
        ]);

        register_setting('pod_customizer_options', 'pod_default_printer_email', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default'           => '',
        ]);

        register_setting('pod_customizer_options', 'pod_primary_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#4f46e5',
        ]);

        register_setting('pod_customizer_options', 'pod_custom_css', [
            'type'              => 'string',
            'sanitize_callback' => 'wp_strip_all_tags',
            'default'           => '',
        ]);

        add_settings_section(
            'pod_customizer_main_section',
            __('Render Backend Connection', 'pod-customizer'),
            function () {
                echo '<p>' . esc_html__('Configure connection parameters to the external 300 DPI graphics render worker (Node.js + Sharp).', 'pod-customizer') . '</p>';
            },
            'pod-customizer-settings'
        );

        add_settings_field(
            'pod_backend_url',
            __('Backend Worker URL', 'pod-customizer'),
            [$this, 'render_backend_url_field'],
            'pod-customizer-settings',
            'pod_customizer_main_section'
        );

        add_settings_field(
            'pod_shared_secret',
            __('Shared Secret Token', 'pod-customizer'),
            [$this, 'render_shared_secret_field'],
            'pod-customizer-settings',
            'pod_customizer_main_section'
        );

        add_settings_field(
            'pod_default_printer_email',
            __('Default Printer Email', 'pod-customizer'),
            [$this, 'render_printer_email_field'],
            'pod-customizer-settings',
            'pod_customizer_main_section'
        );

        // Appearance & Customization Section
        add_settings_section(
            'pod_customizer_appearance_section',
            __('Appearance & Customization', 'pod-customizer'),
            function () {
                echo '<p>' . esc_html__('Customize colors and add custom CSS for the customizer studio on product pages.', 'pod-customizer') . '</p>';
            },
            'pod-customizer-settings'
        );

        add_settings_field(
            'pod_primary_color',
            __('Primary Theme Color', 'pod-customizer'),
            [$this, 'render_primary_color_field'],
            'pod-customizer-settings',
            'pod_customizer_appearance_section'
        );

        add_settings_field(
            'pod_custom_css',
            __('Custom CSS', 'pod-customizer'),
            [$this, 'render_custom_css_field'],
            'pod-customizer-settings',
            'pod_customizer_appearance_section'
        );
    }

    public function render_printer_email_field(): void {
        $value = get_option('pod_default_printer_email', '');
        echo '<input type="email" name="pod_default_printer_email" value="' . esc_attr($value) . '" class="regular-text" placeholder="fulfillment@factory.com" />';
        echo '<p class="description">' . esc_html__('Default recipient email for production print files and orders (can be overridden per order in Order Details).', 'pod-customizer') . '</p>';
    }

    public function render_backend_url_field(): void {
        $value = get_option('pod_backend_url', '');
        ?>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
            <input type="url" id="pod_backend_url" name="pod_backend_url" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="http://pod-backend.localhost" style="min-width: 320px;" />
            <button type="button" id="pod_btn_test_connection" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="dashicons dashicons-update" id="pod_test_spinner" style="display: none; animation: pod-spin 1.2s infinite linear; font-size: 16px; width: 16px; height: 16px;"></span>
                <span id="pod_test_btn_text">⚡ <?php esc_html_e('Test Connection', 'pod-customizer'); ?></span>
            </button>
        </div>
        <div id="pod_connection_result" style="margin-top: 10px; max-width: 650px; display: none;"></div>
        <p class="description" style="margin-top: 6px;">
            <?php esc_html_e('Base URL of the external 300 DPI graphics render service. Click "Test Connection" to check server reachability, or launch the Render Studio via the sidebar tool.', 'pod-customizer'); ?>
        </p>
        <?php
    }

    public function render_shared_secret_field(): void {
        $value = get_option('pod_shared_secret', 'pod_secret_token_123456');
        echo '<input type="password" name="pod_shared_secret" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Secret token used in X-POD-SECRET header for mutual API authorization.', 'pod-customizer') . '</p>';
    }

    public function render_primary_color_field(): void {
        $value = get_option('pod_primary_color', '#4f46e5');
        if (empty($value)) {
            $value = '#4f46e5';
        }
        ?>
        <div style="display: flex; align-items: center; gap: 10px;">
            <input type="color" id="pod_primary_color_picker" value="<?php echo esc_attr($value); ?>" style="width: 38px; height: 38px; padding: 2px; border: 1px solid #ccd0d4; border-radius: 4px; cursor: pointer;" oninput="document.getElementById('pod_primary_color').value = this.value;" />
            <input type="text" id="pod_primary_color" name="pod_primary_color" value="<?php echo esc_attr($value); ?>" class="regular-text" style="width: 110px;" oninput="document.getElementById('pod_primary_color_picker').value = this.value;" placeholder="#4f46e5" />
            <button type="button" class="button button-secondary" onclick="document.getElementById('pod_primary_color').value = '#4f46e5'; document.getElementById('pod_primary_color_picker').value = '#4f46e5';">
                <?php esc_html_e('Reset Default', 'pod-customizer'); ?>
            </button>
        </div>
        <p class="description"><?php esc_html_e('Primary accent color used for customizer buttons, active tabs, bounding box handles, and highlights (default: #4f46e5).', 'pod-customizer'); ?></p>
        <?php
    }

    public function render_custom_css_field(): void {
        $value = get_option('pod_custom_css', '');
        ?>
        <textarea name="pod_custom_css" id="pod_custom_css" rows="8" class="large-text code" style="font-family: monospace; font-size: 13px;" placeholder="<?php esc_attr_e('/* Enter custom CSS rules to override customizer styling on product pages */&#10;.pod-customizer-app {&#10;    /* custom styling */&#10;}', 'pod-customizer'); ?>"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Add custom CSS rules to fine-tune the customizer studio on product pages without modifying core plugin code.', 'pod-customizer'); ?></p>
        <?php
    }

    /**
     * Handle manual cleanup trigger from admin.
     *
     * @return void
     */
    public function handle_manual_cleanup(): void {
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('pod_manual_cleanup_action')) {
            wp_die(esc_html__('Unauthorized action.', 'pod-customizer'));
        }

        $storage = new PreviewStorageManager();
        $deleted_previews = $storage->cleanup_expired_previews(0); // Clean all previews
        $deleted_prints = \PodCustomizer\Services\PrintStorageManager::cleanup_expired_prints(0); // Clean all print archives

        wp_safe_redirect(add_query_arg([
            'page'             => 'pod-customizer-settings',
            'cleaned_previews' => $deleted_previews,
            'cleaned_prints'   => $deleted_prints,
        ], admin_url('options-general.php')));
        exit;
    }

    /**
     * Handle AJAX test connection to the external render backend.
     *
     * @return void
     */
    public function handle_ajax_test_connection(): void {
        check_ajax_referer('pod_test_connection_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'pod-customizer')], 403);
        }

        $raw_backend_url = isset($_POST['backend_url']) ? trim($_POST['backend_url']) : '';
        if (empty($raw_backend_url)) {
            $raw_backend_url = get_option('pod_backend_url', '');
        }

        if (empty($raw_backend_url)) {
            wp_send_json_error(['message' => __('Please enter a Backend Worker URL first.', 'pod-customizer')], 400);
        }

        $backend_url = rtrim($raw_backend_url, '/');
        $shared_secret = isset($_POST['shared_secret']) ? sanitize_text_field($_POST['shared_secret']) : get_option('pod_shared_secret', 'pod_secret_token_123456');
        $domain = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'pod.localhost';

        // Resolve target health URL (with intelligent local docker bridge fallback)
        $target_url = $backend_url . '/health';
        $headers = [
            'X-POD-SECRET' => $shared_secret,
            'X-POD-DOMAIN' => $domain,
            'Accept'       => 'application/json',
        ];

        if (strpos($backend_url, 'pod-backend.localhost') !== false || strpos($backend_url, 'pod-backend:3001') !== false) {
            $target_url = 'http://pod_backend:3001/health';
        }

        $start_time = microtime(true);
        $response = wp_remote_get($target_url, [
            'timeout'   => 5,
            'sslverify' => false,
            'headers'   => $headers,
        ]);

        $duration_ms = round((microtime(true) - $start_time) * 1000);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message'  => sprintf(__('Connection failed: %s (Time: %d ms)', 'pod-customizer'), $response->get_error_message(), $duration_ms),
                'duration' => $duration_ms,
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code >= 200 && $status_code < 300) {
            $service = is_array($data) && isset($data['service']) ? $data['service'] : 'pod-backend-render-engine';
            $license = is_array($data) && isset($data['license']) ? $data['license'] : null;
            wp_send_json_success([
                'message'  => sprintf(
                    __('Connection successful! Service "%s" responded with HTTP %d in %d ms.', 'pod-customizer'),
                    $service,
                    $status_code,
                    $duration_ms
                ),
                'service'  => $service,
                'duration' => $duration_ms,
                'status'   => $status_code,
                'license'  => $license,
                'data'     => $data,
            ]);
        } else {
            wp_send_json_error([
                'message'  => sprintf(
                    __('Backend responded with HTTP %d (Time: %d ms): %s', 'pod-customizer'),
                    $status_code,
                    $duration_ms,
                    esc_html(substr($body, 0, 200))
                ),
                'duration' => $duration_ms,
                'status'   => $status_code,
            ]);
        }
    }

    /**
     * Render the admin page HTML.
     *
     * @return void
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            return;
        }

        $stats = PreviewStorageManager::get_storage_stats();
        $print_stats = \PodCustomizer\Services\PrintStorageManager::get_storage_stats();
        $backend_url = get_option('pod_backend_url', '');
        $shared_secret = get_option('pod_shared_secret', 'pod_secret_token_123456');
        $base_tool_url = !empty($backend_url) ? rtrim($backend_url, '/') . '/test-render' : 'http://pod-backend.localhost/test-render';
        $site_domain = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'pod.localhost';
        $test_tool_url = add_query_arg([
            'domain' => $site_domain,
            'secret' => $shared_secret,
        ], $base_tool_url);
        ?>
        <div class="wrap">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1 style="margin: 0;">🎨 <?php echo esc_html__('POD Customizer & Print Ready Engine', 'pod-customizer'); ?></h1>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=pod_icon')); ?>" class="button button-primary" style="background: #4f46e5; border-color: #4338ca; font-weight: 600;">
                    🖼️ <?php echo esc_html__('Manage POD Icons & Categories', 'pod-customizer'); ?> ➔
                </a>
            </div>
            <hr class="wp-header-end">

            <?php if (isset($_GET['cleaned_previews']) || isset($_GET['cleaned_prints'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php 
                        echo esc_html(sprintf(
                            __('Storage cleanup completed: Removed %d preview files and %d print/ZIP packages successfully.', 'pod-customizer'), 
                            (int) ($_GET['cleaned_previews'] ?? 0),
                            (int) ($_GET['cleaned_prints'] ?? 0)
                        )); 
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 20px;">
                <!-- Main Settings Form -->
                <div style="background: #ffffff; padding: 20px 24px; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('pod_customizer_options');
                        do_settings_sections('pod-customizer-settings');
                        submit_button(__('Save Connection Settings', 'pod-customizer'));
                        ?>
                    </form>
                </div>

                <!-- Right Sidebar Column -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <!-- Visual QA & Test Tool Card -->
                    <div style="background: #ffffff; padding: 20px; border: 1px solid #c7d2fe; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); height: fit-content; background: linear-gradient(to bottom, #f8faff, #ffffff);">
                        <h2 style="margin-top: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; color: #3730a3;">
                            🧪 <?php esc_html_e('Render Studio & QA Tool', 'pod-customizer'); ?>
                        </h2>
                        <p style="font-size: 13px; color: #4b5563; line-height: 1.5;">
                            <?php esc_html_e('Interactive testing studio for compositing 300 DPI graphics, previewing factory ZIP packages, and testing order render payloads directly.', 'pod-customizer'); ?>
                        </p>
                        <a href="<?php echo esc_url($test_tool_url); ?>" id="pod_sidebar_test_tool_btn" target="_blank" rel="noopener noreferrer" class="button button-primary" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 6px 12px; font-weight: 600; background: #4f46e5; border-color: #4338ca; text-decoration: none;">
                            🛠️ <?php esc_html_e('Launch Render Studio Tool', 'pod-customizer'); ?> ↗
                        </a>
                        <p style="font-size: 11px; color: #6b7280; margin: 8px 0 0 0; text-align: center;">
                            🔒 <?php esc_html_e('Secured with configured Shared Secret Token', 'pod-customizer'); ?>
                        </p>
                    </div>

                    <!-- Storage & Media Management Sidebar Card -->
                    <div style="background: #ffffff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); height: fit-content;">
                        <h2 style="margin-top: 0; font-size: 1.1rem;">🗂️ <?php esc_html_e('Storage & Retention Manager', 'pod-customizer'); ?></h2>
                        <p style="font-size: 13px; color: #64748b;">
                            <?php esc_html_e('Files are managed and automatically purged via WP-Cron daily retention schedules to prevent disk overflow.', 'pod-customizer'); ?>
                        </p>

                        <table class="widefat striped" style="margin-bottom: 16px;">
                            <tbody>
                                <tr>
                                    <td><strong><?php esc_html_e('Canvas Previews:', 'pod-customizer'); ?></strong></td>
                                    <td><?php echo esc_html($stats['total_files']); ?> files (<?php echo esc_html($stats['total_size_mb']); ?> MB)</td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Print & ZIP Files:', 'pod-customizer'); ?></strong></td>
                                    <td><?php echo esc_html($print_stats['total_files']); ?> files (<?php echo esc_html($print_stats['total_size_mb']); ?> MB)</td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Total Disk Usage:', 'pod-customizer'); ?></strong></td>
                                    <td><strong><?php echo esc_html(round($stats['total_size_mb'] + $print_stats['total_size_mb'], 2)); ?> MB</strong></td>
                                </tr>
                                <tr>
                                    <td><strong><?php esc_html_e('Retention Policies:', 'pod-customizer'); ?></strong></td>
                                    <td>Previews: 7d | Prints: 30d (Daily auto-purge)</td>
                                </tr>
                            </tbody>
                        </table>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('pod_manual_cleanup_action'); ?>
                            <input type="hidden" name="action" value="pod_manual_cleanup">
                            <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clean up all old preview and print files?', 'pod-customizer'); ?>');">
                                🗑️ <?php esc_html_e('Run Storage Cleanup Now', 'pod-customizer'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <style>
            @keyframes pod-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Dynamic URL synchronization for test tool links with secret
            $('#pod_backend_url, input[name="pod_shared_secret"]').on('input', function() {
                var url = $('#pod_backend_url').val().trim().replace(/\/+$/, '');
                var secret = $('input[name="pod_shared_secret"]').val().trim();
                var base = url ? (url + '/test-render') : 'http://pod-backend.localhost/test-render';
                var siteDomain = '<?php echo esc_js(wp_parse_url(home_url(), PHP_URL_HOST) ?: 'pod.localhost'); ?>';
                var testToolUrl = base + '?domain=' + encodeURIComponent(siteDomain) + (secret ? ('&secret=' + encodeURIComponent(secret)) : '');
                $('#pod_sidebar_test_tool_btn').attr('href', testToolUrl);
            });

            // Live Test Connection Handler
            $('#pod_btn_test_connection').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $spinner = $('#pod_test_spinner');
                var $btnText = $('#pod_test_btn_text');
                var $result = $('#pod_connection_result');

                var backendUrl = $('#pod_backend_url').val().trim();
                var sharedSecret = $('input[name="pod_shared_secret"]').val().trim();

                $btn.prop('disabled', true);
                $spinner.show();
                $btnText.text('<?php echo esc_js(__('Connecting...', 'pod-customizer')); ?>');
                $result.hide().empty();

                $.post(ajaxurl, {
                    action: 'pod_test_backend_connection',
                    nonce: '<?php echo wp_create_nonce('pod_test_connection_nonce'); ?>',
                    backend_url: backendUrl,
                    shared_secret: sharedSecret
                }, function(res) {
                    $btn.prop('disabled', false);
                    $spinner.hide();
                    $btnText.text('⚡ <?php echo esc_js(__('Test Connection', 'pod-customizer')); ?>');
                    $result.show();

                    if (res && res.success) {
                        var lic = res.data && res.data.license;
                        var licHtml = '';

                        if (lic && lic.found) {
                            var statusBadge = lic.is_expired
                                ? '<span style="background: #ef4444; color: #fff; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase;">' + '<?php echo esc_js(__('EXPIRED', 'pod-customizer')); ?>' + '</span>'
                                : '<span style="background: #10b981; color: #fff; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase;">' + '<?php echo esc_js(__('ACTIVE', 'pod-customizer')); ?>' + '</span>';

                            var startsAt = lic.starts_at ? new Date(lic.starts_at).toLocaleDateString() : 'N/A';
                            var expiresAt = lic.expires_at ? new Date(lic.expires_at).toLocaleDateString() : 'N/A';
                            var daysColor = lic.is_expired ? '#ef4444' : (lic.days_remaining <= 30 ? '#f59e0b' : '#059669');

                            licHtml = '<div style="margin-top: 10px; padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px;">' +
                                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">' +
                                    '<div><strong><?php echo esc_js(__('Domain License:', 'pod-customizer')); ?></strong> <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">' + (lic.domain || 'unknown') + '</code></div>' +
                                    '<div>' + statusBadge + '</div>' +
                                '</div>' +
                                '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; color: #475569;">' +
                                    '<div><span style="color: #64748b;"><?php echo esc_js(__('License Key:', 'pod-customizer')); ?></span><br><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600;">' + (lic.license_key || sharedSecret || 'N/A') + '</code></div>' +
                                    '<div><span style="color: #64748b;"><?php echo esc_js(__('Start Date:', 'pod-customizer')); ?></span><br><strong>' + startsAt + '</strong></div>' +
                                    '<div><span style="color: #64748b;"><?php echo esc_js(__('Expiration Date:', 'pod-customizer')); ?></span><br><strong>' + expiresAt + '</strong></div>' +
                                    '<div><span style="color: #64748b;"><?php echo esc_js(__('Remaining Time:', 'pod-customizer')); ?></span><br><strong style="color: ' + daysColor + ';">' + lic.days_remaining + ' <?php echo esc_js(__('days', 'pod-customizer')); ?>' + (lic.is_expired ? ' (Expired)' : '') + '</strong></div>' +
                                '</div>';

                            if (lic.is_expired) {
                                licHtml += '<div style="margin-top: 10px; padding: 8px 10px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px; color: #9f1239; font-size: 12px; font-weight: 500;">' +
                                    '⚠️ <?php echo esc_js(__('Notice: Your domain license has expired. Test connection succeeded, but production print rendering is suspended until renewal.', 'pod-customizer')); ?>' +
                                '</div>';
                            } else if (!lic.secret_valid) {
                                licHtml += '<div style="margin-top: 10px; padding: 8px 10px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 4px; color: #92400e; font-size: 12px; font-weight: 500;">' +
                                    '⚠️ <?php echo esc_js(__('Warning: Server reachable, but secret token does not match registered domain license.', 'pod-customizer')); ?>' +
                                '</div>';
                            }

                            licHtml += '</div>';
                        } else if (lic && !lic.found) {
                            licHtml = '<div style="margin-top: 10px; padding: 10px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; color: #92400e; font-size: 13px;">' +
                                '⚠️ <strong><?php echo esc_js(__('Domain Unregistered:', 'pod-customizer')); ?></strong> ' + (lic.message || '<?php echo esc_js(__('Domain is not registered in the backend license database.', 'pod-customizer')); ?>') +
                            '</div>';
                        }

                        $result.html(
                            '<div class="notice notice-success inline" style="padding: 12px 14px; margin: 0; border-left: 4px solid #10b981; background: #ecfdf5; border-radius: 4px;">' +
                            '<p style="margin: 0 0 6px 0; color: #065f46; font-size: 13px; font-weight: 600;">✅ ' + res.data.message + '</p>' +
                            licHtml +
                            '</div>'
                        );
                    } else {
                        var err = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js(__('Connection failed.', 'pod-customizer')); ?>';
                        $result.html(
                            '<div class="notice notice-error inline" style="padding: 10px 14px; margin: 0; border-left: 4px solid #ef4444; background: #fef2f2; border-radius: 4px;">' +
                            '<p style="margin: 0; color: #991b1b; font-size: 13px; font-weight: 600;">❌ ' + err + '</p>' +
                            '</div>'
                        );
                    }
                }).fail(function(xhr) {
                    $btn.prop('disabled', false);
                    $spinner.hide();
                    $btnText.text('⚡ <?php echo esc_js(__('Test Connection', 'pod-customizer')); ?>');
                    $result.show();
                    var errMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : xhr.statusText;
                    $result.html(
                        '<div class="notice notice-error inline" style="padding: 10px 14px; margin: 0; border-left: 4px solid #ef4444; background: #fef2f2; border-radius: 4px;">' +
                        '<p style="margin: 0; color: #991b1b; font-size: 13px; font-weight: 600;">❌ <?php echo esc_js(__('Request error: ', 'pod-customizer')); ?>' + errMsg + '</p>' +
                        '</div>'
                    );
                });
            });
        });
        </script>
        <?php
    }
}
