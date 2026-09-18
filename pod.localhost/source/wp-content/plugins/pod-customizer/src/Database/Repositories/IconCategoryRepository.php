<?php

namespace PodCustomizer\Database\Repositories;

/**
 * Class IconCategoryRepository
 * Encapsulates database operations for {$wpdb->prefix}pod_icon_categories.
 */
class IconCategoryRepository {

    /**
     * Get table name with dynamic WordPress prefix.
     */
    public static function get_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'pod_icon_categories';
    }

    /**
     * Create a new icon category.
     *
     * @param array $data
     * @return int Inserted ID, or 0 on failure.
     */
    public static function create(array $data): int {
        global $wpdb;
        $table = self::get_table();

        $name = sanitize_text_field($data['name'] ?? '');
        $slug = !empty($data['slug']) ? sanitize_title($data['slug']) : sanitize_title($name);
        if (empty($name) || empty($slug)) {
            return 0;
        }

        // Avoid duplicate slugs
        $existing = self::get_by_slug($slug);
        if ($existing) {
            $slug .= '-' . time();
        }

        $payload = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => !empty($data['description']) ? sanitize_textarea_field($data['description']) : null,
            'sort_order'  => (int)($data['sort_order'] ?? 0),
            'is_active'   => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $payload);
        return $inserted ? (int)$wpdb->insert_id : 0;
    }

    /**
     * Update an existing icon category.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool {
        global $wpdb;
        $table = self::get_table();

        $payload = [];
        if (isset($data['name'])) {
            $payload['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['slug'])) {
            $payload['slug'] = sanitize_title($data['slug']);
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = !empty($data['description']) ? sanitize_textarea_field($data['description']) : null;
        }
        if (isset($data['sort_order'])) {
            $payload['sort_order'] = (int)$data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $payload['is_active'] = (int)(bool)$data['is_active'];
        }

        if (empty($payload)) {
            return false;
        }

        $payload['updated_at'] = current_time('mysql');

        $updated = $wpdb->update($table, $payload, ['id' => $id]);
        return $updated !== false;
    }

    /**
     * Delete an icon category. Foreign key constraint CASCADE will delete child icons.
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
     * Find category by ID.
     */
    public static function get_by_id(int $id): ?object {
        global $wpdb;
        $table = self::get_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        return $row ?: null;
    }

    /**
     * Find category by slug.
     */
    public static function get_by_slug(string $slug): ?object {
        global $wpdb;
        $table = self::get_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", sanitize_title($slug)));
        return $row ?: null;
    }

    /**
     * List all icon categories with icon counts.
     *
     * @param bool $active_only
     * @return array
     */
    public static function get_all(bool $active_only = false): array {
        global $wpdb;
        $table_cats = self::get_table();
        $table_icons = $wpdb->prefix . 'pod_icons';

        $where = $active_only ? "WHERE c.is_active = 1" : "";

        $sql = "SELECT c.*, COUNT(i.id) AS icon_count
                FROM {$table_cats} c
                LEFT JOIN {$table_icons} i ON c.id = i.category_id AND i.status = 'active'
                {$where}
                GROUP BY c.id
                ORDER BY c.sort_order ASC, c.id ASC";

        $results = $wpdb->get_results($sql, ARRAY_A);
        return is_array($results) ? $results : [];
    }
}
