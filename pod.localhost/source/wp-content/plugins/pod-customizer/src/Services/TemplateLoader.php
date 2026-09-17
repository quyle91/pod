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
        $dir = self::get_templates_dir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '*.json');
        $templates = [];

        foreach ($files as $file) {
            $json = file_get_contents($file);
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['id'])) {
                $templates[$data['id']] = self::normalize_template_assets($data);
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
        $templates = self::get_all_templates();
        if (empty($templates)) {
            return null;
        }

        // 1. Query parameter override (e.g. ?pod_tpl=tpl_03)
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

    /**
     * Convert relative template asset URLs into fully qualified absolute URLs.
     *
     * @param array $template
     * @return array
     */
    private static function normalize_template_assets(array $template): array {
        $base_url = rtrim(POD_CUSTOMIZER_URL, '/') . '/';

        // Mockup URL
        if (!empty($template['mockup']['url']) && !preg_match('#^https?://#i', $template['mockup']['url'])) {
            $template['mockup']['url'] = $base_url . ltrim($template['mockup']['url'], '/');
        }

        // Field Assets
        if (!empty($template['fields']) && is_array($template['fields'])) {
            foreach ($template['fields'] as &$field) {
                // Repeater sub-image
                if (!empty($field['sub_image']['url']) && !preg_match('#^https?://#i', $field['sub_image']['url'])) {
                    $field['sub_image']['url'] = $base_url . ltrim($field['sub_image']['url'], '/');
                }
                // Preset options
                if (!empty($field['options']) && is_array($field['options'])) {
                    foreach ($field['options'] as &$opt) {
                        if (!empty($opt['url']) && !preg_match('#^https?://#i', $opt['url'])) {
                            $opt['url'] = $base_url . ltrim($opt['url'], '/');
                        }
                    }
                }
            }
        }

        return $template;
    }
}
