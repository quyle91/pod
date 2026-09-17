<?php

namespace PodCustomizer\Services;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class PrintStorageManager
 * Manages client-side persistent storage and lifecycle for 300 DPI print files and factory ZIP packages.
 * Downloads generated files from the render worker into WordPress wp-content/uploads/pod-prints/.
 */
class PrintStorageManager implements HandlerInterface {

    public const UPLOAD_SUBDIR = 'pod-prints';
    public const CRON_HOOK = 'pod_daily_print_cleanup';
    public const RETENTION_DAYS = 30;

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action(self::CRON_HOOK, [__CLASS__, 'cleanup_expired_prints']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Download a production file from the render backend worker and store it locally on the client.
     *
     * @param int $order_id WooCommerce Order ID
     * @param int $item_id WooCommerce Line Item ID
     * @param string $remote_url Remote URL from the render worker
     * @param string $file_type 'print' for 300 DPI PNG, 'zip' for factory package
     * @return string|null Local client URL of the saved file, or null on failure
     */
    public static function store_production_file(int $order_id, int $item_id, string $remote_url, string $file_type = 'print'): ?string {
        if (empty($remote_url)) {
            return null;
        }

        // If the URL is already a local WordPress URL, return as-is
        $upload_info = wp_upload_dir();
        if (strpos($remote_url, $upload_info['baseurl']) !== false) {
            return $remote_url;
        }

        // Prepare target directory: wp-content/uploads/pod-prints/{year}/{month}/order_{order_id}/
        $year_month = date('Y/m');
        $target_subpath = self::UPLOAD_SUBDIR . '/' . $year_month . '/order_' . $order_id;
        $target_dir = trailingslashit($upload_info['basedir']) . $target_subpath;
        $target_url = trailingslashit($upload_info['baseurl']) . $target_subpath;

        if (!wp_mkdir_p($target_dir)) {
            error_log('[POD Customizer] Failed to create target directory: ' . $target_dir);
            return null;
        }

        // Determine destination file extension and filename
        $parsed_path = wp_parse_url($remote_url, PHP_URL_PATH);
        $remote_filename = basename($parsed_path);

        if ($file_type === 'zip') {
            if (!empty($remote_filename) && str_ends_with($remote_filename, '.zip')) {
                $filename = $remote_filename;
            } else {
                $domain_slug = str_replace('.', '_', wp_parse_url(home_url(), PHP_URL_HOST) ?: 'pod_localhost');
                $filename = sprintf('%s_order_%d_item_%d.zip', $domain_slug, $order_id, $item_id);
            }
        } else {
            $extension = pathinfo($parsed_path, PATHINFO_EXTENSION) ?: 'png';
            $filename = sprintf('order_%d_item_%d_300dpi.%s', $order_id, $item_id, $extension);
        }

        $target_filepath = trailingslashit($target_dir) . $filename;
        $final_file_url  = trailingslashit($target_url) . $filename;

        // Stream download directly to destination file with reject_unsafe_urls set to false
        $response = wp_remote_get($remote_url, [
            'timeout'            => 300,
            'stream'             => true,
            'filename'           => $target_filepath,
            'reject_unsafe_urls' => false,
        ]);

        if (is_wp_error($response)) {
            error_log(sprintf('[POD Customizer] File download failed for %s: %s', $remote_url, $response->get_error_message()));
            if (file_exists($target_filepath)) {
                @unlink($target_filepath);
            }
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200 || !file_exists($target_filepath) || filesize($target_filepath) === 0) {
            error_log(sprintf('[POD Customizer] Remote server returned HTTP %d for %s', $status_code, $remote_url));
            if (file_exists($target_filepath)) {
                @unlink($target_filepath);
            }
            return null;
        }

        // Set proper file permissions
        @chmod($target_filepath, 0644);

        return $final_file_url;
    }

    /**
     * Get storage statistics for client-side print files.
     *
     * @return array{total_files: int, total_size_mb: float}
     */
    public static function get_storage_stats(): array {
        $upload_info = wp_upload_dir();
        $target_dir = trailingslashit($upload_info['basedir']) . self::UPLOAD_SUBDIR;

        if (!is_dir($target_dir)) {
            return ['total_files' => 0, 'total_size_mb' => 0.0];
        }

        $total_files = 0;
        $total_bytes = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target_dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total_files++;
                $total_bytes += $file->getSize();
            }
        }

        return [
            'total_files'   => $total_files,
            'total_size_mb' => round($total_bytes / (1024 * 1024), 2),
        ];
    }

    /**
     * Clean up production files older than X days.
     *
     * @param int $days Number of retention days. 0 = clean all files.
     * @return int Number of files deleted.
     */
    public static function cleanup_expired_prints(int $days = self::RETENTION_DAYS): int {
        $upload_info = wp_upload_dir();
        $target_dir = trailingslashit($upload_info['basedir']) . self::UPLOAD_SUBDIR;

        if (!is_dir($target_dir)) {
            return 0;
        }

        $now = time();
        $cutoff_time = $days > 0 ? ($now - ($days * DAY_IN_SECONDS)) : $now + 1;
        $deleted_count = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target_dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                if ($item->getMTime() <= $cutoff_time) {
                    @unlink($item->getPathname());
                    $deleted_count++;
                }
            } elseif ($item->isDir()) {
                // Delete empty directories
                @rmdir($item->getPathname());
            }
        }

        return $deleted_count;
    }

    /**
     * Generate a tamper-proof, time-limited signed URL for factory downloads.
     *
     * @param int $order_id
     * @param int $item_id
     * @param string $file_type 'zip' or 'print'
     * @param int $expires_in_seconds Default 7 days (604800s)
     * @return string Signed REST download URL
     */
    public static function generate_signed_download_url(int $order_id, int $item_id, string $file_type = 'zip', int $expires_in_seconds = 604800): string {
        $expires = time() + $expires_in_seconds;
        $salt = wp_salt('auth') . get_option('pod_shared_secret', 'pod_secret_token_123456');
        $payload = sprintf('order:%d:item:%d:type:%s:expires:%d', $order_id, $item_id, $file_type, $expires);
        $sig = hash_hmac('sha256', $payload, $salt);

        return add_query_arg([
            'order_id' => $order_id,
            'item_id'  => $item_id,
            'type'     => $file_type,
            'expires'  => $expires,
            'sig'      => $sig,
        ], rest_url('pod-customizer/v1/download-production'));
    }

    /**
     * Verify the HMAC cryptographic signature of a download request.
     *
     * @param int $order_id
     * @param int $item_id
     * @param string $file_type
     * @param int $expires
     * @param string $signature
     * @return bool True if valid and not expired
     */
    public static function verify_download_signature(int $order_id, int $item_id, string $file_type, int $expires, string $signature): bool {
        if ($expires < time()) {
            return false;
        }

        $salt = wp_salt('auth') . get_option('pod_shared_secret', 'pod_secret_token_123456');
        $payload = sprintf('order:%d:item:%d:type:%s:expires:%d', $order_id, $item_id, $file_type, $expires);
        $expected_sig = hash_hmac('sha256', $payload, $salt);

        return hash_equals($expected_sig, $signature);
    }
}
