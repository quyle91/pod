<?php

namespace PodCustomizer\Services;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class PreviewStorageManager
 * Manages the lifecycle, file storage, and cleanup of customer canvas preview images.
 * Follows Single Responsibility Principle (SRP).
 */
class PreviewStorageManager implements HandlerInterface {

    public const UPLOAD_SUBDIR = 'pod-previews';
    public const CRON_HOOK = 'pod_daily_preview_cleanup';
    public const RETENTION_DAYS = 7;

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action(self::CRON_HOOK, [$this, 'cleanup_expired_previews']);

        // Schedule daily cleanup if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Save a Base64 data URL to a physical JPEG/PNG image on disk.
     *
     * @param string $data_url Base64 Data URL (e.g. data:image/jpeg;base64,...).
     * @return string|null Absolute public URL of the saved image, or null on failure.
     */
    public static function save_base64_image(string $data_url): ?string {
        if (empty($data_url) || !preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $data_url, $matches)) {
            return null;
        }

        $extension = strtolower($matches[1]);
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $decoded = base64_decode($matches[2], true);
        if ($decoded === false || strlen($decoded) < 100) {
            return null;
        }

        // Magic bytes verification
        if (!self::validate_image_bytes($decoded, $extension)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $year_month = date('Y/m');
        $target_dir = trailingslashit($upload_dir['basedir']) . self::UPLOAD_SUBDIR . '/' . $year_month;
        $target_url = trailingslashit($upload_dir['baseurl']) . self::UPLOAD_SUBDIR . '/' . $year_month;

        if (!wp_mkdir_p($target_dir)) {
            return null;
        }

        // Generate secure unique hash filename
        $filename = 'preview_' . md5(microtime(true) . wp_rand()) . '.' . $extension;
        $file_path = trailingslashit($target_dir) . $filename;
        $file_url = trailingslashit($target_url) . $filename;

        $written = file_put_contents($file_path, $decoded);
        if ($written === false) {
            return null;
        }

        // Set secure file permissions
        chmod($file_path, 0644);

        // Record into database preview files index
        $hash = md5($decoded);
        $file_size = strlen($decoded);
        $mime = ($extension === 'jpg') ? 'image/jpeg' : 'image/' . $extension;
        \PodCustomizer\Database\Repositories\PreviewFileRepository::record_file(
            $hash,
            $file_path,
            $file_url,
            $file_size,
            $mime,
            (int)get_option('pod_preview_retention_days', self::RETENTION_DAYS)
        );

        return $file_url;
    }

    /**
     * Validate image binary magic bytes.
     *
     * @param string $bytes
     * @param string $ext
     * @return bool
     */
    private static function validate_image_bytes(string $bytes, string $ext): bool {
        $header = substr($bytes, 0, 8);

        switch ($ext) {
            case 'jpg':
                return strncmp($header, "\xFF\xD8\xFF", 3) === 0;
            case 'png':
                return strncmp($header, "\x89PNG\r\n\x1a\n", 8) === 0;
            case 'webp':
                return strncmp(substr($bytes, 0, 4), 'RIFF', 4) === 0 && strncmp(substr($bytes, 8, 4), 'WEBP', 4) === 0;
            default:
                return false;
        }
    }

    /**
     * Delete a preview file from disk using its public URL.
     *
     * @param string $file_url
     * @return bool
     */
    public static function delete_preview_file(string $file_url): bool {
        if (empty($file_url)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        if (strpos($file_url, self::UPLOAD_SUBDIR) === false) {
            return false; // Security check: Only delete within pod-previews
        }

        $relative = str_replace($upload_dir['baseurl'], '', $file_url);
        $file_path = realpath($upload_dir['basedir'] . $relative);

        if ($file_path && file_exists($file_path) && is_file($file_path)) {
            return unlink($file_path);
        }

        return false;
    }

    /**
     * Purge preview files older than specified days.
     *
     * @param int $days Number of retention days.
     * @return int Number of deleted files.
     */
    public function cleanup_expired_previews(int $days = self::RETENTION_DAYS): int {
        $upload_dir = wp_upload_dir();
        $base_path = trailingslashit($upload_dir['basedir']) . self::UPLOAD_SUBDIR;

        if (!is_dir($base_path)) {
            return 0;
        }

        $expiry_timestamp = time() - ($days * DAY_IN_SECONDS);
        $deleted_count = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base_path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                if ($file->getMTime() < $expiry_timestamp) {
                    if (@unlink($file->getRealPath())) {
                        $deleted_count++;
                    }
                }
            } elseif ($file->isDir()) {
                // Remove empty year/month directory
                @rmdir($file->getRealPath());
            }
        }

        return $deleted_count;
    }

    /**
     * Get storage statistics for admin dashboard.
     *
     * @return array
     */
    public static function get_storage_stats(): array {
        $upload_dir = wp_upload_dir();
        $base_path = trailingslashit($upload_dir['basedir']) . self::UPLOAD_SUBDIR;

        $total_files = 0;
        $total_size = 0;

        if (is_dir($base_path)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base_path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total_files++;
                    $total_size += $file->getSize();
                }
            }
        }

        return [
            'total_files' => $total_files,
            'total_size_mb' => round($total_size / (1024 * 1024), 2),
            'retention_days' => self::RETENTION_DAYS,
            'upload_path' => $base_path,
        ];
    }
}
