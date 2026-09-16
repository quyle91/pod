<?php

namespace FlatsomeChild\Controller;

class Enqueue {
    private static $instance = null;
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'wp_enqueue_scripts']);
    }

    function wp_enqueue_scripts() {

        // css inline
        wp_register_style('flatsome-child-css-inline', '',);
        wp_enqueue_style('flatsome-child-css-inline');
        wp_add_inline_style(
            'flatsome-child-css-inline',
            ':root{
				' . implode(
                ";",
                [
                    '--flatsome-child-test: "xxx"',
                ]
            ) . '
			}'
        );

        // js inline
        wp_register_script('flatsome-child-script-inline', '',);
        wp_enqueue_script('flatsome-child-script-inline');
        wp_add_inline_script(
            'flatsome-child-script-inline',
            'const flatsome_child_script_var = ' . json_encode(
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('flatsome-child-nonce'),
                )
            ),
            'before'
        );

        // js
        $js_folder = '/assets/js/';
        foreach (glob(FLATSOME_CHILD_DIR . $js_folder . '*.js') as $file) {
            $handle = sanitize_title(pathinfo($file)['filename']);
            $src = FLATSOME_CHILD_DIR_URL . $js_folder . basename($file);
            wp_enqueue_script(
                $handle,
                $src,
                [],
                FLATSOME_CHILD_VER,
                true
            );
        }

        // css
        $css_folder = '/assets/css/';
        foreach (glob(FLATSOME_CHILD_DIR . $css_folder . '*.css') as $file) {
            $handle = sanitize_title(pathinfo($file)['filename']);
            $src = FLATSOME_CHILD_DIR_URL . $css_folder . basename($file);
            wp_enqueue_style(
                $handle,
                $src,
                [],
                FLATSOME_CHILD_VER,
                'all'
            );
        }
    }
}
