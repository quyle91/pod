<?php

namespace PodCustomizer\Database;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class MigrationManager
 * Manages custom database tables creation, updates, and foreign key integrity.
 * Adheres to WordPress dbDelta standards and safe DDL migration procedures.
 */
class MigrationManager implements HandlerInterface {

    public const DB_VERSION = '1.1.0';
    public const OPTION_DB_VERSION = 'pod_db_version';

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('admin_init', [$this, 'check_and_run_migrations']);
    }

    /**
     * Check current installed schema version and run migrations if needed.
     *
     * @return void
     */
    public function check_and_run_migrations(): void {
        $installed_ver = get_option(self::OPTION_DB_VERSION);
        if ($installed_ver !== self::DB_VERSION) {
            $this->migrate();
        }
    }

    /**
     * Execute migrations: create tables via dbDelta and establish foreign keys.
     *
     * @return void
     */
    public function migrate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_jobs = $wpdb->prefix . 'pod_render_jobs';
        $table_previews = $wpdb->prefix . 'pod_preview_files';
        $table_categories = $wpdb->prefix . 'pod_icon_categories';
        $table_icons = $wpdb->prefix . 'pod_icons';
        $table_order_items = $wpdb->prefix . 'woocommerce_order_items';

        // 1. Table: pod_render_jobs
        $sql_jobs = "CREATE TABLE {$table_jobs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            preview_url varchar(500) DEFAULT NULL,
            print_ready_url varchar(500) DEFAULT NULL,
            canvas_payload longtext NOT NULL,
            attempts tinyint(3) unsigned NOT NULL DEFAULT '0',
            error_message text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uk_order_item_id (order_item_id),
            KEY idx_order_id (order_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        // 2. Table: pod_preview_files
        $sql_previews = "CREATE TABLE {$table_previews} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            file_hash varchar(64) NOT NULL,
            order_item_id bigint(20) unsigned DEFAULT NULL,
            file_path varchar(255) NOT NULL,
            url varchar(500) NOT NULL,
            file_size_bytes bigint(20) unsigned NOT NULL DEFAULT '0',
            mime_type varchar(30) NOT NULL DEFAULT 'image/jpeg',
            status varchar(30) NOT NULL DEFAULT 'active',
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uk_file_hash (file_hash),
            KEY idx_order_item_id (order_item_id),
            KEY idx_status_expires (status, expires_at)
        ) {$charset_collate};";

        // 3. Table: pod_icon_categories
        $sql_categories = "CREATE TABLE {$table_categories} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(100) NOT NULL,
            description text DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uk_pod_category_slug (slug),
            KEY idx_pod_category_status (is_active, sort_order)
        ) {$charset_collate};";

        // 4. Table: pod_icons
        $sql_icons = "CREATE TABLE {$table_icons} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            category_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            slug varchar(100) NOT NULL,
            thumbnail_url varchar(500) NOT NULL,
            print_url varchar(500) NOT NULL,
            is_vector tinyint(1) NOT NULL DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_pod_icon_cat (category_id),
            KEY idx_pod_icon_status (status, sort_order)
        ) {$charset_collate};";

        dbDelta($sql_jobs);
        dbDelta($sql_previews);
        dbDelta($sql_categories);
        dbDelta($sql_icons);

        // 5. Establish Foreign Key Constraints safely
        $this->add_foreign_key_if_missing(
            $table_jobs,
            'fk_pod_jobs_order_item',
            'order_item_id',
            $table_order_items,
            'order_item_id',
            'CASCADE'
        );

        $this->add_foreign_key_if_missing(
            $table_previews,
            'fk_pod_previews_order_item',
            'order_item_id',
            $table_order_items,
            'order_item_id',
            'SET NULL'
        );

        $this->add_foreign_key_if_missing(
            $table_icons,
            'fk_pod_icon_category',
            'category_id',
            $table_categories,
            'id',
            'CASCADE'
        );

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
    }

    /**
     * Add foreign key constraint idempotently if not already present.
     *
     * @param string $table
     * @param string $fk_name
     * @param string $column
     * @param string $referenced_table
     * @param string $referenced_column
     * @param string $on_delete
     * @return void
     */
    private function add_foreign_key_if_missing(
        string $table,
        string $fk_name,
        string $column,
        string $referenced_table,
        string $referenced_column,
        string $on_delete = 'CASCADE'
    ): void {
        global $wpdb;

        // Check if referenced table exists
        $ref_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $referenced_table));
        if ($ref_exists !== $referenced_table) {
            return;
        }

        // Check if constraint already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME 
             FROM information_schema.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = %s 
               AND TABLE_NAME = %s 
               AND CONSTRAINT_NAME = %s 
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            DB_NAME,
            $table,
            $fk_name
        ));

        if (!$existing) {
            $sql = "ALTER TABLE `{$table}` 
                    ADD CONSTRAINT `{$fk_name}` 
                    FOREIGN KEY (`{$column}`) 
                    REFERENCES `{$referenced_table}` (`{$referenced_column}`) 
                    ON DELETE {$on_delete} 
                    ON UPDATE CASCADE";
            $wpdb->query($sql);
        }
    }
}
