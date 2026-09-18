<?php

namespace PodCustomizer\Admin;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class IconPostType
 * Registers standard WordPress Custom Post Type 'pod_icon' and Taxonomy 'pod_icon_category'
 * providing native WordPress admin menus, list tables, categories, and bulk media upload.
 */
class IconPostType implements HandlerInterface {

    public const POST_TYPE = 'pod_icon';
    public const TAXONOMY  = 'pod_icon_category';

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('init', [$this, 'register_post_type_and_taxonomy']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'register_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_column_content'], 10, 2);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta_boxes'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('restrict_manage_posts', [$this, 'add_category_filter']);
        add_action('wp_ajax_pod_bulk_upload_icons', [$this, 'handle_ajax_bulk_upload']);
        add_filter('upload_mimes', [$this, 'allow_svg_upload']);
        add_filter('wp_check_filetype_and_ext', [$this, 'fix_svg_mime_type'], 10, 4);
    }

    /**
     * Enable SVG upload in WordPress Media Library.
     */
    public function allow_svg_upload(array $mimes): array {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Fix WordPress mime check for SVG files.
     */
    public function fix_svg_mime_type(array $data, string $file, string $filename, ?array $mimes): array {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $data['ext']  = 'svg';
            $data['type'] = 'image/svg+xml';
        }
        return $data;
    }

    /**
     * Register Custom Post Type and Taxonomy.
     */
    public function register_post_type_and_taxonomy(): void {
        // 1. Taxonomy: Icon Categories
        $tax_labels = [
            'name'              => _x('Icon Categories', 'taxonomy general name', 'pod-customizer'),
            'singular_name'     => _x('Icon Category', 'taxonomy singular name', 'pod-customizer'),
            'search_items'      => __('Search Categories', 'pod-customizer'),
            'all_items'         => __('All Categories', 'pod-customizer'),
            'parent_item'       => __('Parent Category', 'pod-customizer'),
            'parent_item_colon' => __('Parent Category:', 'pod-customizer'),
            'edit_item'         => __('Edit Category', 'pod-customizer'),
            'update_item'       => __('Update Category', 'pod-customizer'),
            'add_new_item'      => __('Add New Category', 'pod-customizer'),
            'new_item_name'     => __('New Category Name', 'pod-customizer'),
            'menu_name'         => __('Categories', 'pod-customizer'),
        ];

        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], [
            'hierarchical'      => true,
            'labels'            => $tax_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'icon-category'],
            'show_in_rest'      => true,
        ]);

        // 2. Post Type: POD Icons
        $pt_labels = [
            'name'               => _x('POD Icons', 'post type general name', 'pod-customizer'),
            'singular_name'      => _x('POD Icon', 'post type singular name', 'pod-customizer'),
            'menu_name'          => _x('POD Icons', 'admin menu', 'pod-customizer'),
            'name_admin_bar'     => _x('POD Icon', 'add new on admin bar', 'pod-customizer'),
            'add_new'            => __('Add New', 'pod-customizer'),
            'add_new_item'       => __('Add New Icon', 'pod-customizer'),
            'new_item'           => __('New Icon', 'pod-customizer'),
            'edit_item'          => __('Edit Icon', 'pod-customizer'),
            'view_item'          => __('View Icon', 'pod-customizer'),
            'all_items'          => __('All Icons', 'pod-customizer'),
            'search_items'       => __('Search Icons', 'pod-customizer'),
            'parent_item_colon'  => __('Parent Icons:', 'pod-customizer'),
            'not_found'          => __('No icons found.', 'pod-customizer'),
            'not_found_in_trash' => __('No icons found in Trash.', 'pod-customizer'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'             => $pt_labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 58,
            'menu_icon'          => 'dashicons-art',
            'supports'           => ['title', 'thumbnail'],
            'taxonomies'         => [self::TAXONOMY],
            'show_in_rest'       => true,
        ]);
    }

    /**
     * Define custom list table columns.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function register_columns(array $columns): array {
        $new_columns = [];
        foreach ($columns as $key => $title) {
            if ($key === 'title') {
                $new_columns['icon_preview'] = __('Preview', 'pod-customizer');
            }
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['icon_format'] = __('Format', 'pod-customizer');
            }
        }
        return $new_columns;
    }

    /**
     * Render custom column cell content.
     */
    public function render_column_content(string $column, int $post_id): void {
        if ($column === 'icon_preview') {
            $thumb_id = get_post_thumbnail_id($post_id);
            $thumb_url = $thumb_id ? wp_get_attachment_url($thumb_id) : get_post_meta($post_id, '_pod_thumbnail_url', true);
            if (!empty($thumb_url)) {
                echo '<div style="width: 44px; height: 44px; background: repeating-conic-gradient(#f1f5f9 0% 25%, #ffffff 0% 50%) 50% / 12px 12px; border: 1px solid #cbd5e1; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 2px;">';
                echo '<img src="' . esc_url($thumb_url) . '" alt="" style="max-width: 100%; max-height: 100%; object-fit: contain;" />';
                echo '</div>';
            } else {
                echo '<span style="color: #94a3b8; font-size: 11px;">' . esc_html__('No Image', 'pod-customizer') . '</span>';
            }
        } elseif ($column === 'icon_format') {
            $is_vector = get_post_meta($post_id, '_pod_is_vector', true);
            $thumb_id = get_post_thumbnail_id($post_id);
            $mime = $thumb_id ? get_post_mime_type($thumb_id) : '';
            if ($is_vector || $mime === 'image/svg+xml') {
                echo '<span style="background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 11px;">SVG</span>';
            } else {
                echo '<span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 11px;">PNG</span>';
            }
        }
    }

    /**
     * Add dropdown to filter icons by category on list table.
     */
    public function add_category_filter(): void {
        global $typenow;
        if ($typenow === self::POST_TYPE) {
            $selected = isset($_GET[self::TAXONOMY]) ? sanitize_text_field($_GET[self::TAXONOMY]) : '';
            $terms = get_terms([
                'taxonomy'   => self::TAXONOMY,
                'hide_empty' => false,
            ]);

            if (!empty($terms) && !is_wp_error($terms)) {
                echo '<select name="' . esc_attr(self::TAXONOMY) . '" id="' . esc_attr(self::TAXONOMY) . '">';
                echo '<option value="">' . esc_html__('All Icon Categories', 'pod-customizer') . '</option>';
                foreach ($terms as $term) {
                    echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . ' (' . esc_html((string)$term->count) . ')</option>';
                }
                echo '</select>';
            }
        }
    }

    /**
     * Register Icon Meta Boxes.
     */
    public function register_meta_boxes(): void {
        add_meta_box(
            'pod_icon_details_meta_box',
            __('POD Print & Render Asset Details', 'pod-customizer'),
            [$this, 'render_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render Meta Box.
     */
    public function render_meta_box(\WP_Post $post): void {
        wp_nonce_field('pod_icon_meta_action', 'pod_icon_meta_nonce');
        $print_url = get_post_meta($post->ID, '_pod_print_url', true);
        $is_vector = get_post_meta($post->ID, '_pod_is_vector', true);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="pod_print_url"><?php esc_html_e('Print-Ready URL (300 DPI)', 'pod-customizer'); ?></label></th>
                <td>
                    <input type="url" name="pod_print_url" id="pod_print_url" value="<?php echo esc_attr($print_url); ?>" class="large-text" placeholder="https://..." />
                    <p class="description"><?php esc_html_e('Optional separate high-resolution (300 DPI) graphic file for factory print rendering. If empty, the featured image/SVG will be used directly.', 'pod-customizer'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Vector Format', 'pod-customizer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pod_is_vector" value="1" <?php checked($is_vector, '1'); ?> />
                        <?php esc_html_e('Treat as Scalable Vector Graphic (SVG) for infinite resolution rendering', 'pod-customizer'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Meta Box data.
     */
    public function save_meta_boxes(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['pod_icon_meta_nonce']) || !wp_verify_nonce($_POST['pod_icon_meta_nonce'], 'pod_icon_meta_action')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['pod_print_url'])) {
            update_post_meta($post_id, '_pod_print_url', esc_url_raw($_POST['pod_print_url']));
        }
        $is_vector = !empty($_POST['pod_is_vector']) ? '1' : '0';
        update_post_meta($post_id, '_pod_is_vector', $is_vector);
    }

    /**
     * Enqueue Media Library and Bulk Upload assets on edit.php?post_type=pod_icon.
     */
    public function enqueue_admin_assets(string $hook): void {
        global $typenow;
        if ($typenow !== self::POST_TYPE) {
            return;
        }

        wp_enqueue_media();

        // Inject Bulk Upload button into page header and modal script
        add_action('admin_footer', [$this, 'render_bulk_upload_modal_and_script']);
    }

    /**
     * Render Bulk Upload Modal & JavaScript.
     */
    public function render_bulk_upload_modal_and_script(): void {
        global $pagenow, $typenow;
        if ($pagenow !== 'edit.php' || $typenow !== self::POST_TYPE) {
            return;
        }

        $terms = get_terms([
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
        ]);
        ?>
        <!-- Bulk Upload Modal -->
        <div id="pod-bulk-upload-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 100000; align-items: center; justify-content: center;">
            <div style="background: #ffffff; border-radius: 12px; width: 460px; max-width: 90vw; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0; font-size: 17px; color: #0f172a;">⚡ <?php esc_html_e('Bulk Upload Icons & Clipart', 'pod-customizer'); ?></h3>
                    <button type="button" class="pod-modal-close" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">&times;</button>
                </div>

                <p style="font-size: 13px; color: #475569; margin-bottom: 16px;">
                    <?php esc_html_e('Select an Icon Category, then choose multiple SVGs or 300 DPI PNGs from your WordPress Media Library.', 'pod-customizer'); ?>
                </p>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        <?php esc_html_e('Target Category:', 'pod-customizer'); ?>
                    </label>
                    <select id="pod-bulk-category-select" style="width: 100%; padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                        <option value=""><?php esc_html_e('-- Select a Category --', 'pod-customizer'); ?></option>
                        <?php if (!empty($terms) && !is_wp_error($terms)): ?>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?php echo esc_attr((string)$term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div id="pod-bulk-status-area" style="display: none; margin-bottom: 16px; padding: 10px; border-radius: 6px; font-size: 13px;"></div>

                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="button pod-modal-close"><?php esc_html_e('Cancel', 'pod-customizer'); ?></button>
                    <button type="button" id="pod-btn-open-media-modal" class="button button-primary" style="background: #4f46e5; border-color: #4338ca;">
                        🖼️ <?php esc_html_e('Select Media & Import', 'pod-customizer'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // 1. Inject "⚡ Bulk Upload" button next to "Add New"
            var $addNewBtn = $('.page-title-action').first();
            if ($addNewBtn.length) {
                $('<a href="#" id="pod-trigger-bulk-btn" class="page-title-action" style="background: #4f46e5; color: #ffffff; border-color: #4338ca; font-weight: 600; margin-left: 8px;">⚡ <?php echo esc_js(__('Bulk Upload Icons', 'pod-customizer')); ?></a>')
                    .insertAfter($addNewBtn);
            }

            var $modal = $('#pod-bulk-upload-modal');
            var $status = $('#pod-bulk-status-area');
            var mediaFrame = null;

            $('#pod-trigger-bulk-btn').on('click', function(e) {
                e.preventDefault();
                $status.hide().empty();
                $modal.css('display', 'flex');
            });

            $('.pod-modal-close').on('click', function() {
                $modal.hide();
            });

            $('#pod-btn-open-media-modal').on('click', function() {
                var termId = $('#pod-bulk-category-select').val();
                if (!termId) {
                    alert('<?php echo esc_js(__('Please select an Icon Category first.', 'pod-customizer')); ?>');
                    return;
                }

                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }

                mediaFrame = wp.media({
                    title: '<?php echo esc_js(__('Select or Upload Icons / Clipart (SVG, PNG)', 'pod-customizer')); ?>',
                    button: { text: '<?php echo esc_js(__('Import Selected to Category', 'pod-customizer')); ?>' },
                    multiple: true,
                    library: { type: 'image' }
                });

                mediaFrame.on('select', function() {
                    var selection = mediaFrame.state().get('selection');
                    var items = [];

                    selection.each(function(attachment) {
                        var att = attachment.toJSON();
                        items.push({
                            id: att.id,
                            title: att.title || att.filename,
                            url: att.url,
                            mime: att.mime
                        });
                    });

                    if (items.length === 0) return;

                    $status.css({ display: 'block', background: '#e0f2fe', color: '#0369a1', border: '1px solid #bae6fd' })
                        .text('<?php echo esc_js(__('Importing icons into WordPress...', 'pod-customizer')); ?> (' + items.length + ')');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'pod_bulk_upload_icons',
                            nonce: '<?php echo wp_create_nonce('pod_bulk_upload_icons_nonce'); ?>',
                            term_id: termId,
                            items: JSON.stringify(items)
                        },
                        success: function(res) {
                            if (res && res.success) {
                                $status.css({ background: '#ecfdf5', color: '#065f46', border: '1px solid #a7f3d0' })
                                    .text('✅ ' + (res.data.message || 'Import successful! Reloading...'));
                                setTimeout(function() { window.location.reload(); }, 1200);
                            } else {
                                $status.css({ background: '#fef2f2', color: '#991b1b', border: '1px solid #fecaca' })
                                    .text('❌ ' + ((res && res.data && res.data.message) || 'Error importing icons.'));
                            }
                        },
                        error: function() {
                            $status.css({ background: '#fef2f2', color: '#991b1b', border: '1px solid #fecaca' })
                                .text('❌ Server connection failed.');
                        }
                    });
                });

                mediaFrame.open();
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Bulk create pod_icon posts from Media selection.
     */
    public function handle_ajax_bulk_upload(): void {
        check_ajax_referer('pod_bulk_upload_icons_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $term_id = isset($_POST['term_id']) ? (int) $_POST['term_id'] : 0;
        $items_raw = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
        $items = json_decode($items_raw, true);

        if (!$term_id || empty($items) || !is_array($items)) {
            wp_send_json_error(['message' => 'Invalid parameters.'], 400);
        }

        $created = 0;
        foreach ($items as $item) {
            $attachment_id = isset($item['id']) ? (int) $item['id'] : 0;
            $title = !empty($item['title']) ? sanitize_text_field($item['title']) : 'Icon';
            $url = !empty($item['url']) ? esc_url_raw($item['url']) : '';
            $mime = !empty($item['mime']) ? sanitize_text_field($item['mime']) : '';

            $is_vector = ($mime === 'image/svg+xml' || substr($url, -4) === '.svg') ? '1' : '0';

            // Create pod_icon post
            $post_id = wp_insert_post([
                'post_title'   => $title,
                'post_status'  => 'publish',
                'post_type'    => self::POST_TYPE,
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                // Assign category term
                wp_set_object_terms($post_id, [$term_id], self::TAXONOMY);

                // Set thumbnail / print metadata
                if ($attachment_id) {
                    set_post_thumbnail($post_id, $attachment_id);
                }
                update_post_meta($post_id, '_pod_thumbnail_url', $url);
                update_post_meta($post_id, '_pod_print_url', $url);
                update_post_meta($post_id, '_pod_is_vector', $is_vector);

                $created++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('Successfully imported %d icons.', 'pod-customizer'), $created),
            'count'   => $created,
        ]);
    }
}
