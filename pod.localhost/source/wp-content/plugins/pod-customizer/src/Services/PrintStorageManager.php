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

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        // Register any storage or retention maintenance hooks if needed
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
        $extension = pathinfo($parsed_path, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = ($file_type === 'zip') ? 'zip' : 'png';
        }

        $filename = sprintf('order_%d_item_%d_%s.%s', $order_id, $item_id, ($file_type === 'zip' ? 'production' : '300dpi'), $extension);
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
}
