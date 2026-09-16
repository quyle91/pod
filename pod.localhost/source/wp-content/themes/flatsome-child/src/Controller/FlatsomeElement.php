<?php
namespace FlatsomeChild\Controller;

class FlatsomeElement {
    private static $instance = null;
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        $this->shortcode_example();
    }

    function shortcode_example() {
        
        if (!class_exists('\Adminz\Helper\FlatsomeELement')) {
            return;
        }

        $reflection = new \ReflectionClass(get_class($this));
        $name = $reflection->getShortName();
        $___ = new \Adminz\Helper\FlatsomeELement;
        $___->shortcode_title = ucfirst(str_replace('_', ' ', $name));
        $___->shortcode_name = $name;
        $___->shortcode_icon = 'text';
        $___->options = [
            // 'post_parent' => [
            // 'type'=> 'textfield',
            // 'heading' => 'Parent Id',
            // ],
            'advanced_options' => require(get_template_directory() . '/inc/builder/shortcodes/commons/advanced.php'),
        ];
        $___->shortcode_callback = function ($atts, $content = null) use ($name) {
            $atts = apply_filters($name . '_atts', $atts);
            $atts = shortcode_atts(array(
                'class' => '',
                'visibility' => '',
                // 'post_parent'=> get_the_ID(),
            ), $atts);
            extract($atts);

            $classes = [$name];
            if (!empty($class)) $classes[] = $class;
            if (!empty($visibility)) $classes[] = $visibility;

            ob_start();
            echo "<div class='" . implode(' ', $classes) . "'>";

            // code here 





            

            echo '</div>';
            // return ob_get_clean();
            return do_shortcode(ob_get_clean());
        };
        $___->general_element();
    }
}