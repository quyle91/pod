<?php

namespace PodCustomizer\Core;

use PodCustomizer\Contracts\HandlerInterface;
use PodCustomizer\WooCommerce\CartHandler;
use PodCustomizer\WooCommerce\OrderHandler;
use PodCustomizer\WooCommerce\OrderWebhookDispatcher;
use PodCustomizer\Admin\SettingsPage;
use PodCustomizer\Admin\IconPostType;
use PodCustomizer\Admin\ProductDesignerMetaBox;
use PodCustomizer\API\CallbackController;
use PodCustomizer\API\AssetLibraryController;
use PodCustomizer\Frontend\CustomizerAssets;
use PodCustomizer\Frontend\CustomizerRenderer;
use PodCustomizer\Services\PreviewStorageManager;

/**
 * Class Plugin
 * Main singleton orchestrator for POD Customizer plugin.
 */
final class Plugin {
    /**
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * @var array<HandlerInterface>
     */
    private array $handlers = [];

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct() {}

    /**
     * Get singleton instance.
     *
     * @return Plugin
     */
    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize all sub-modules and register hooks.
     *
     * @return void
     */
    public function init(): void {
        $this->handlers = [
            new \PodCustomizer\Database\MigrationManager(),
            new CartHandler(),
            new OrderHandler(),
            new OrderWebhookDispatcher(),
            new SettingsPage(),
            new IconPostType(),
            new ProductDesignerMetaBox(),
            new CallbackController(),
            new AssetLibraryController(),
            new CustomizerAssets(),
            new CustomizerRenderer(),
            new PreviewStorageManager(),
        ];

        foreach ($this->handlers as $handler) {
            $handler->register_hooks();
        }

        // Enable WordPress HTTP requests to route local development domains (e.g. *.localhost) via the docker gateway
        add_action('http_api_curl', [$this, 'resolve_local_domains_in_curl'], 10, 3);

        // Action hook for external modules or add-ons to hook in (Open/Closed Principle)
        do_action('pod_customizer_loaded', $this);
    }

    /**
     * Resolve local .localhost domains via Docker gateway when running in containerized environments.
     * In production (with real domains), this method is a no-op.
     */
    public function resolve_local_domains_in_curl($handle, array $r, string $url): void {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if ($host && (substr($host, -10) === '.localhost' || $host === 'localhost')) {
            $gateway_ip = getenv('DOCKER_GATEWAY_IP') ?: '172.18.0.1';
            $port = wp_parse_url($url, PHP_URL_PORT) ?: 80;
            curl_setopt($handle, CURLOPT_RESOLVE, ["{$host}:{$port}:{$gateway_ip}"]);
        }
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserializing.
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}
