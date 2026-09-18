<?php

namespace PodCustomizer\Database\Repositories;

/**
 * Class IconRepository
 * Encapsulates database operations for {$wpdb->prefix}pod_icons.
 */
class IconRepository {

    /**
     * Get table name with dynamic WordPress prefix.
     */
    public static function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'pod_icons';
    }

    /**
     * Create a new icon record.
     *
     * @param array $data
     * @return int Inserted ID, or 0 on failure.
     */
    public static function create(array $data): int {
        global $wpdb;
        $table = self::get_table();

        $category_id = isset($data['category_id']) ? (int)$data['category_id'] : 0;
        $title = sanitize_text_field($data['title'] ?? '');
        $thumbnail_url = esc_url_raw($data['thumbnail_url'] ?? '');
        $print_url = esc_url_raw($data['print_url'] ?? $thumbnail_url);

        if (!$category_id || empty($title) || empty($thumbnail_url)) {
            return 0;
        }

        $slug = !empty($data['slug']) ? sanitize_title($data['slug']) : sanitize_title($title);
        $is_vector = (int)(bool)($data['is_vector'] ?? (str_ends_with(strtolower($thumbnail_url), '.svg')));

        $status = (!empty($data['status']) && in_array($data['status'], ['active', 'inactive'], true)) ? $data['status'] : 'active';

        $payload = [
            'category_id'   => $category_id,
            'title'         => $title,
            'slug'          => $slug,
            'thumbnail_url' => $thumbnail_url,
            'print_url'     => $print_url,
            'is_vector'     => $is_vector,
            'sort_order'    => (int)($data['sort_order'] ?? 0),
            'status'        => $status,
            'created_at'    => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $payload);
        return $inserted ? (int)$wpdb->insert_id : 0;
    }

    /**
     * Bulk insert multiple icons.
     *
     * @param int $category_id
     * @param array $items Array of icon data dictionaries
     * @return int Count of successfully inserted icons.
     */
    public static function bulk_create(int $category_id, array $items): int {
        $count = 0;
        foreach ($items as $item) {
            $item['category_id'] = $category_id;
            if (self::create($item) > 0) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Update an icon record.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool {
        global $wpdb;
        $table = self::get_table();

        $payload = [];
        if (isset($data['category_id'])) {
            $payload['category_id'] = (int)$data['category_id'];
        }
        if (isset($data['title'])) {
            $payload['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['slug'])) {
            $payload['slug'] = sanitize_title($data['slug']);
        }
        if (isset($data['thumbnail_url'])) {
            $payload['thumbnail_url'] = esc_url_raw($data['thumbnail_url']);
        }
        if (isset($data['print_url'])) {
            $payload['print_url'] = esc_url_raw($data['print_url']);
        }
        if (isset($data['is_vector'])) {
            $payload['is_vector'] = (int)(bool)$data['is_vector'];
        }
        if (isset($data['sort_order'])) {
            $payload['sort_order'] = (int)$data['sort_order'];
        }
        if (isset($data['status'])) {
            $payload['status'] = in_array($data['status'], ['active', 'inactive'], true) ? $data['status'] : 'active';
        }

        if (empty($payload)) {
            return false;
        }

        $updated = $wpdb->update($table, $payload, ['id' => $id]);
        return $updated !== false;
    }

    /**
     * Delete an icon record.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        global $wpdb;
        $deleted = $wpdb->delete(self::get_table(), ['id' => $id], ['%d']);
        return (bool)$deleted;
    }

    /**
     * Get icon by ID.
     */
    public static function get_by_id(int $id): ?object {
        global $wpdb;
        $table = self::get_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        return $row ?: null;
    }

    /**
     * Get all icons for a category ID.
     *
     * @param int $category_id
     * @param bool $active_only
     * @return array
     */
    public static function get_by_category(int $category_id, bool $active_only = false): array {
        global $wpdb;
        $table = self::get_table();

        $where = "WHERE category_id = %d";
        if ($active_only) {
            $where .= " AND status = 'active'";
        }

        $sql = $wpdb->prepare("SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, id ASC", $category_id);
        $results = $wpdb->get_results($sql, ARRAY_A);
        return is_array($results) ? $results : [];
    }

    /**
     * Get all icons for a category slug.
     *
     * @param string $category_slug
     * @param bool $active_only
     * @return array
     */
    public static function get_by_category_slug(string $category_slug, bool $active_only = false): array {
        global $wpdb;
        $table_icons = self::get_table();
        $table_cats = IconCategoryRepository::get_table();

        $where = "WHERE c.slug = %s";
        if ($active_only) {
            $where .= " AND i.status = 'active' AND c.is_active = 1";
        }

        $sql = $wpdb->prepare(
            "SELECT i.*, c.slug AS category_slug, c.name AS category_name
             FROM {$table_icons} i
             INNER JOIN {$table_cats} c ON i.category_id = c.id
             {$where}
             ORDER BY i.sort_order ASC, i.id ASC",
            sanitize_title($category_slug)
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        return is_array($results) ? $results : [];
    }

    /**
     * Count icons in a category.
     */
    public static function count_by_category(int $category_id): int {
        global $wpdb;
        $table = self::get_table();
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE category_id = %d", $category_id));
    }
}
