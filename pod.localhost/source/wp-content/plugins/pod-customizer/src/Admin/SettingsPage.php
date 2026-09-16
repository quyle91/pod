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
    }

    /**
     * Register settings submenu under Settings (options-general.php).
     *
     * @return void
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
    }

    public function render_printer_email_field(): void {
        $value = get_option('pod_default_printer_email', '');
        echo '<input type="email" name="pod_default_printer_email" value="' . esc_attr($value) . '" class="regular-text" placeholder="fulfillment@factory.com" />';
        echo '<p class="description">' . esc_html__('Default recipient email for production print files and orders (can be overridden per order in Order Details).', 'pod-customizer') . '</p>';
    }

    public function render_backend_url_field(): void {
        $value = get_option('pod_backend_url', '');
        echo '<input type="url" name="pod_backend_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://render.yourdomain.com" />';
        echo '<p class="description">' . esc_html__('Base URL of the external 300 DPI graphics render service.', 'pod-customizer') . '</p>';
    }

    public function render_shared_secret_field(): void {
        $value = get_option('pod_shared_secret', 'pod_secret_token_123456');
        echo '<input type="password" name="pod_shared_secret" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Secret token used in X-POD-SECRET header for mutual API authorization.', 'pod-customizer') . '</p>';
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
        $deleted = $storage->cleanup_expired_previews(0); // Clean all or expired

        wp_safe_redirect(add_query_arg([
            'page'    => 'pod-customizer-settings',
            'cleaned' => $deleted,
        ], admin_url('options-general.php')));
        exit;
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
        ?>
        <div class="wrap">
            <h1>🎨 <?php echo esc_html__('POD Customizer & Print Ready Engine', 'pod-customizer'); ?></h1>
            <hr class="wp-header-end">

            <?php if (isset($_GET['cleaned'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(sprintf(__('Cleaned %d old preview files successfully.', 'pod-customizer'), (int) $_GET['cleaned'])); ?></p>
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

                <!-- Storage & Media Management Sidebar Card -->
                <div style="background: #ffffff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); height: fit-content;">
                    <h2 style="margin-top: 0; font-size: 1.1rem;">🗂️ <?php esc_html_e('Preview Storage Manager', 'pod-customizer'); ?></h2>
                    <p style="font-size: 13px; color: #64748b;">
                        <?php esc_html_e('Client Canvas preview images are stored as static JPEGs in the uploads folder and automatically purged to save disk space.', 'pod-customizer'); ?>
                    </p>

                    <table class="widefat striped" style="margin-bottom: 16px;">
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e('Total Previews:', 'pod-customizer'); ?></strong></td>
                                <td><mark style="padding: 2px 6px; border-radius: 4px;"><?php echo esc_html($stats['total_files']); ?> files</mark></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Disk Usage:', 'pod-customizer'); ?></strong></td>
                                <td><?php echo esc_html($stats['total_size_mb']); ?> MB</td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Retention Policy:', 'pod-customizer'); ?></strong></td>
                                <td><?php echo esc_html($stats['retention_days']); ?> days (Auto-purged daily)</td>
                            </tr>
                        </tbody>
                    </table>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('pod_manual_cleanup_action'); ?>
                        <input type="hidden" name="action" value="pod_manual_cleanup">
                        <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clean up all old preview images?', 'pod-customizer'); ?>');">
                            🗑️ <?php esc_html_e('Run Storage Cleanup Now', 'pod-customizer'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
