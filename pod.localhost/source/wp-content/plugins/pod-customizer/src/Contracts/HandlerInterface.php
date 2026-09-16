<?php

namespace PodCustomizer\Contracts;

/**
 * Interface HandlerInterface
 * Standard contract for modular hook handlers.
 * Follows Interface Segregation & Single Responsibility Principles.
 */
interface HandlerInterface {
    /**
     * Register relevant WordPress actions and filters.
     *
     * @return void
     */
    public function register_hooks(): void;
}
