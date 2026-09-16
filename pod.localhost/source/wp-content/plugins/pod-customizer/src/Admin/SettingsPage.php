<?php

namespace PodCustomizer\Admin;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class SettingsPage
 * Provides administration interface for configuring POD Backend connection settings.
 */
class SettingsPage implements HandlerInterface {

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add sub-menu under WooCommerce in WP Admin.
     *
     * @return void
     */
    public function add_settings_menu(): void {
        add_submenu_page(
            'woocommerce',
            __('POD Customizer Settings', 'pod-customizer'),
            __('POD Customizer', 'pod-customizer'),
            'manage_woocommerce',
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
            'default'           => 'http://pod-backend.localhost',
        ]);

        register_setting('pod_customizer_options', 'pod_shared_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'pod_secret_token_123456',
        ]);

        add_settings_section(
            'pod_customizer_main_section',
            __('Render Backend Connection', 'pod-customizer'),
            function () {
                echo '<p>' . esc_html__('Configure connection parameters to the external 300 DPI graphics render worker.', 'pod-customizer') . '</p>';
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
    }

    public function render_backend_url_field(): void {
        $value = get_option('pod_backend_url', 'http://pod-backend.localhost');
        echo '<input type="url" name="pod_backend_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="http://pod-backend.localhost" />';
        echo '<p class="description">' . esc_html__('Internal or external URL of the Node.js Sharp render engine.', 'pod-customizer') . '</p>';
    }

    public function render_shared_secret_field(): void {
        $value = get_option('pod_shared_secret', 'pod_secret_token_123456');
        echo '<input type="password" name="pod_shared_secret" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Secret token used in X-POD-SECRET header for mutual API authorization.', 'pod-customizer') . '</p>';
    }

    /**
     * Render the admin page HTML.
     *
     * @return void
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('POD Customizer & Print Ready Engine', 'pod-customizer'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('pod_customizer_options');
                do_settings_sections('pod-customizer-settings');
                submit_button(__('Save Settings', 'pod-customizer'));
                ?>
            </form>
        </div>
        <?php
    }
}
