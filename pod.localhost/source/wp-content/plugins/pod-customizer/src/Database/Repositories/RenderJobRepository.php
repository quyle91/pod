<?php

namespace PodCustomizer\Database\Repositories;

/**
 * Class RenderJobRepository
 * Encapsulates database operations for {$wpdb->prefix}pod_render_jobs.
 */
class RenderJobRepository {

    /**
     * Get table name with dynamic WordPress prefix.
     *
     * @return string
     */
    public static function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'pod_render_jobs';
    }

    /**
     * Create or update a render job row.
     *
     * @param array $data
     * @return int Inserted/Updated ID, or 0 on failure.
     */
    public static function create_or_update(array $data): int {
        global $wpdb;
        $table = self::get_table();

        $order_item_id = isset($data['order_item_id']) ? (int)$data['order_item_id'] : 0;
        if (!$order_item_id) {
            return 0;
        }

        $existing = self::get_by_order_item_id($order_item_id);

        $payload = [
            'order_id'       => (int)($data['order_id'] ?? 0),
            'order_item_id'  => $order_item_id,
            'status'         => sanitize_text_field($data['status'] ?? 'pending'),
            'preview_url'    => !empty($data['preview_url']) ? esc_url_raw($data['preview_url']) : null,
            'print_ready_url'=> !empty($data['print_ready_url']) ? esc_url_raw($data['print_ready_url']) : null,
            'canvas_payload' => is_array($data['canvas_payload']) ? wp_json_encode($data['canvas_payload']) : (string)($data['canvas_payload'] ?? ''),
            'attempts'       => (int)($data['attempts'] ?? 0),
            'error_message'  => !empty($data['error_message']) ? sanitize_text_field($data['error_message']) : null,
        ];

        if ($existing) {
            $wpdb->update($table, $payload, ['order_item_id' => $order_item_id]);
            return (int)$existing->id;
        }

        $inserted = $wpdb->insert($table, $payload);
        return $inserted ? (int)$wpdb->insert_id : 0;
    }

    /**
     * Find render job by order item ID.
     *
     * @param int $order_item_id
     * @return object|null
     */
    public static function get_by_order_item_id(int $order_item_id): ?object {
        global $wpdb;
        $table = self::get_table();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE order_item_id = %d LIMIT 1",
            $order_item_id
        ));

        return $row ?: null;
    }

    /**
     * Update status and URLs of a job.
     *
     * @param int         $order_item_id
     * @param string      $status
     * @param string|null $print_url
     * @param string|null $error_message
     * @return bool
     */
    public static function update_status(int $order_item_id, string $status, ?string $print_url = null, ?string $error_message = null): bool {
        global $wpdb;
        $table = self::get_table();

        $update_data = [
            'status' => sanitize_text_field($status)
        ];

        if ($print_url !== null) {
            $update_data['print_ready_url'] = esc_url_raw($print_url);
        }
        if ($error_message !== null) {
            $update_data['error_message'] = sanitize_text_field($error_message);
        }

        $result = $wpdb->update($table, $update_data, ['order_item_id' => $order_item_id]);
        return $result !== false;
    }

    /**
     * Increment processing attempt count.
     *
     * @param int $order_item_id
     * @return bool
     */
    public static function increment_attempts(int $order_item_id): bool {
        global $wpdb;
        $table = self::get_table();
        $res = $wpdb->query($wpdb->prepare(
            "UPDATE `{$table}` SET attempts = attempts + 1 WHERE order_item_id = %d",
            $order_item_id
        ));
        return $res !== false;
    }
}
