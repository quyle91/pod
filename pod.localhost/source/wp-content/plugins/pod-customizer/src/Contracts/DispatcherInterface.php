<?php

namespace PodCustomizer\Contracts;

/**
 * Interface DispatcherInterface
 * Contract for dispatching render requests to the backend worker.
 * Follows Dependency Inversion Principle (DIP).
 */
interface DispatcherInterface {
    /**
     * Dispatch an asynchronous render task for an order line item.
     *
     * @param int   $order_id
     * @param int   $item_id
     * @param array $canvas_state
     * @return array Response payload with task id and initial status.
     */
    public function dispatch(int $order_id, int $item_id, array $canvas_state): array;
}
