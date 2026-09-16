<?php

namespace FlatsomeChild\Controller;

class Wpcf7 {
    private static $instance = null;
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        $this->form_tags();
    }

    function form_tags() {
        add_filter('wpcf7_form_tag', function ($tag, $replace) {
            if (in_array($tag['name'], ['chi-nhanh', 'dich-vu']) && in_array($tag['type'], ['select', 'select*'])) {
                $list = [
                    [
                        'name' => 'xxx1',
                        'value' => 'xxx1value',
                    ],
                    [
                        'name' => 'xxx2',
                        'value' => 'xxx2value',
                    ],
                    [
                        'name' => 'xxx3',
                        'value' => 'xxx3value',
                    ],
                ];
                if (!is_wp_error($list) && !empty($list)) {
                    foreach ($list as $item) {
                        $tag['raw_values'][] = $item['name'];
                        $tag['values'][] = $item['name'];
                        $tag['labels'][] = $item['name'];
                    }
                }
            }
            return $tag;
        }, 10, 2);
    }
}
