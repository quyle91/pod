<?php

namespace PodCustomizer\Core;

use PodCustomizer\Contracts\HandlerInterface;
use PodCustomizer\WooCommerce\CartHandler;
use PodCustomizer\WooCommerce\OrderHandler;
use PodCustomizer\WooCommerce\OrderWebhookDispatcher;
use PodCustomizer\Admin\SettingsPage;
use PodCustomizer\API\CallbackController;

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
            new CartHandler(),
            new OrderHandler(),
            new OrderWebhookDispatcher(),
            new SettingsPage(),
            new CallbackController(),
        ];

        foreach ($this->handlers as $handler) {
            $handler->register_hooks();
        }

        // Action hook for external modules or add-ons to hook in (Open/Closed Principle)
        do_action('pod_customizer_loaded', $this);
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
