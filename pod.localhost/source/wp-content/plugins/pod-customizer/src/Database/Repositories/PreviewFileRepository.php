<?php

namespace PodCustomizer\Database\Repositories;

/**
 * Class PreviewFileRepository
 * Encapsulates database operations for {$wpdb->prefix}pod_preview_files.
 */
class PreviewFileRepository {

    /**
     * Get table name with dynamic WordPress prefix.
     *
     * @return string
     */
    public static function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'pod_preview_files';
    }

    /**
     * Record newly generated preview file into tracking index.
     *
     * @param string $file_hash
     * @param string $file_path
     * @param string $url
     * @param int    $file_size
     * @param string $mime_type
     * @param int    $retention_days
     * @return int
     */
    public static function record_file(
        string $file_hash,
        string $file_path,
        string $url,
        int $file_size,
        string $mime_type = 'image/jpeg',
        int $retention_days = 7
    ): int {
        global $wpdb;
        $table = self::get_table();

        $expires_at = gmdate('Y-m-d H:i:s', time() + ($retention_days * DAY_IN_SECONDS));

        $data = [
            'file_hash'       => sanitize_text_field($file_hash),
            'file_path'       => sanitize_text_field($file_path),
            'url'             => esc_url_raw($url),
            'file_size_bytes' => max(0, $file_size),
            'mime_type'       => sanitize_text_field($mime_type),
            'status'          => 'active',
            'expires_at'      => $expires_at,
        ];

        // Insert or update on duplicate hash
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE file_hash = %s LIMIT 1",
            $file_hash
        ));

        if ($existing) {
            $wpdb->update($table, $data, ['id' => (int)$existing]);
            return (int)$existing;
        }

        $inserted = $wpdb->insert($table, $data);
        return $inserted ? (int)$wpdb->insert_id : 0;
    }

    /**
     * Associate a preview file with an order item ID.
     *
     * @param string $url
     * @param int    $order_item_id
     * @return bool
     */
    public static function link_order_item(string $url, int $order_item_id): bool {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->update(
            $table,
            ['order_item_id' => $order_item_id],
            ['url' => esc_url_raw($url)]
        );

        return $result !== false;
    }

    /**
     * Query expired preview files for retention cleanup.
     *
     * @param int $limit
     * @return array
     */
    public static function get_expired_files(int $limit = 100): array {
        global $wpdb;
        $table = self::get_table();
        $now = gmdate('Y-m-d H:i:s');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` 
             WHERE status = 'active' AND expires_at <= %s 
             ORDER BY expires_at ASC 
             LIMIT %d",
            $now,
            $limit
        ));

        return is_array($rows) ? $rows : [];
    }

    /**
     * Mark file as deleted in database.
     *
     * @param int $id
     * @return bool
     */
    public static function mark_deleted(int $id): bool {
        global $wpdb;
        $table = self::get_table();

        $result = $wpdb->update(
            $table,
            ['status' => 'deleted'],
            ['id' => $id]
        );

        return $result !== false;
    }
}
