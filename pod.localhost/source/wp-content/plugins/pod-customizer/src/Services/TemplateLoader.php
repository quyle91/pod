<?php

namespace PodCustomizer\Services;

/**
 * Class TemplateLoader
 * Discovers, parses, and resolves template-driven POD product configurations.
 */
class TemplateLoader {

    /**
     * Directory containing JSON template definitions.
     *
     * @return string
     */
    public static function get_templates_dir(): string {
        return POD_CUSTOMIZER_PATH . 'templates/';
    }

    /**
     * Get all available template definitions.
     *
     * @return array<string, array>
     */
    public static function get_all_templates(): array {
        $templates = [];

        // 1. Built-in plugin templates
        $dir = self::get_templates_dir();
        if (is_dir($dir)) {
            $files = glob($dir . '*.json');
            foreach ($files as $file) {
                $json = file_get_contents($file);
                $data = json_decode($json, true);
                if (is_array($data)) {
                    $id = $data['id'] ?? $data['template_id'] ?? null;
                    if ($id) {
                        $templates[$id] = self::normalize_template_assets($data);
                    }
                }
            }
        }

        // 2. Two-Tier PSD Imported templates in wp-content/uploads/pod-templates/
        $upload_dir = wp_upload_dir();
        $imported_dir = trailingslashit($upload_dir['basedir']) . 'pod-templates/';
        if (is_dir($imported_dir)) {
            $config_files = glob($imported_dir . '*/template_config.json');
            if ($config_files) {
                foreach ($config_files as $cfg_file) {
                    $json = file_get_contents($cfg_file);
                    $data = json_decode($json, true);
                    if (is_array($data)) {
                        $id = $data['template_id'] ?? $data['id'] ?? null;
                        if ($id) {
                            $templates[$id] = self::normalize_template_assets($data);
                        }
                    }
                }
            }
        }

        return $templates;
    }

    /**
     * Resolve the active template for a given product or request context.
     * Checks query string '?pod_tpl=' first (for rapid testing), then product postmeta.
     *
     * @param int|null $product_id
     * @return array|null
     */
    public static function get_active_template(?int $product_id = null): ?array {
        // 1. Direct Product Configuration (Highest Priority for Product-Centric Flow)
        if ($product_id) {
            $direct_config = get_post_meta($product_id, '_pod_template_config', true);
            if (!empty($direct_config)) {
                if (is_string($direct_config)) {
                    $direct_config = json_decode($direct_config, true);
                }
                if (is_array($direct_config) && (!empty($direct_config['layers']) || !empty($direct_config['fields']))) {
                    return self::normalize_template_assets($direct_config);
                }
            }
        }

        $templates = self::get_all_templates();
        if (empty($templates)) {
            return null;
        }

        // 2. Query parameter override (e.g. ?pod_tpl=tpl_03)
        if (isset($_GET['pod_tpl'])) {
            $requested_id = sanitize_key($_GET['pod_tpl']);
            if (isset($templates[$requested_id])) {
                return $templates[$requested_id];
            }
            // Check by prefix or filename match
            foreach ($templates as $id => $tpl) {
                if (strpos($id, $requested_id) !== false) {
                    return $tpl;
                }
            }
        }

        // 2. Product postmeta configuration
        if ($product_id) {
            $tpl_id = get_post_meta($product_id, '_pod_template_id', true);
            if (!empty($tpl_id) && isset($templates[$tpl_id])) {
                return $templates[$tpl_id];
            }
        }

        // 3. Fallback: Return first available template
        $first_tpl = reset($templates);
        return $first_tpl ?: null;
    }

    private static function normalize_url(?string $url): string {
        if (empty($url)) return '';
        if (preg_match('#^https?://#i', $url)) return $url;
        if (strpos($url, '/wp-content/') === 0) {
            return home_url($url);
        }
        return rtrim(POD_CUSTOMIZER_URL, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Convert relative template asset URLs into fully qualified absolute URLs.
     *
     * @param array $template
     * @return array
     */
    private static function normalize_template_assets(array $template): array {
        // Mockup URL
        if (!empty($template['mockup'])) {
            if (!empty($template['mockup']['url'])) {
                $template['mockup']['url'] = self::normalize_url($template['mockup']['url']);
            }
            if (!empty($template['mockup']['base_url'])) {
                $template['mockup']['base_url'] = self::normalize_url($template['mockup']['base_url']);
            }
        }

        // Field Assets
        if (!empty($template['fields']) && is_array($template['fields'])) {
            foreach ($template['fields'] as &$field) {
                // Repeater sub-image
                if (!empty($field['sub_image']['url'])) {
                    $field['sub_image']['url'] = self::normalize_url($field['sub_image']['url']);
                }
                // Preset options
                if (!empty($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as &$opt) {
                        if (!empty($opt['url'])) {
                            $opt['url'] = self::normalize_url($opt['url']);
                        }
                        if (!empty($opt['thumbnail_url'])) {
                            $opt['thumbnail_url'] = self::normalize_url($opt['thumbnail_url']);
                        }
                    }
                }
            }
        }

        // Layer URLs
        if (!empty($template['layers']) && is_array($template['layers'])) {
            foreach ($template['layers'] as &$layer) {
                if (!empty($layer['url'])) {
                    $layer['url'] = self::normalize_url($layer['url']);
                }
                if (!empty($layer['placeholder_url'])) {
                    $layer['placeholder_url'] = self::normalize_url($layer['placeholder_url']);
                }
            }
        }

        return $template;
    }
}
