<?php

namespace PodCustomizer\Admin;

use PodCustomizer\Contracts\HandlerInterface;
use PodCustomizer\Services\TemplateLoader;

/**
 * Class TemplateImporterPage
 * Provides the WP-Admin interface for:
 * 1. Direct chunked PSD upload to Node.js backend.
 * 2. Visual Studio 2D Canvas Editor with Property Inspector & Asset Library Binding.
 * 3. WooCommerce Product Meta Box for seamless Template assignment.
 * 4. Designer Guidelines for PSD preparation.
 */
class TemplateImporterPage implements HandlerInterface {

    public const PAGE_SLUG = 'pod-import-psd';

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('admin_menu', [$this, 'register_submenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [$this, 'register_product_metabox']);
        add_action('save_post_product', [$this, 'save_product_metabox'], 10, 2);

        // AJAX handlers for Visual Studio
        add_action('wp_ajax_pod_save_template_config', [$this, 'ajax_save_template_config']);
        add_action('wp_ajax_pod_load_template_config', [$this, 'ajax_load_template_config']);
        add_action('wp_ajax_pod_assign_template_to_product', [$this, 'ajax_assign_template_to_product']);
    }

    /**
     * Register submenu under POD Icons menu.
     */
    public function register_submenu(): void {
        add_submenu_page(
            'edit.php?post_type=pod_icon',
            __('Import Template từ PSD', 'pod-customizer'),
            __('Import PSD & Studio', 'pod-customizer'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue Fabric.js and Admin styles on the importer page.
     */
    public function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        // Fabric.js for interactive Visual Studio Canvas
        wp_enqueue_script(
            'fabric-js',
            POD_CUSTOMIZER_URL . 'assets/js/vendor/fabric.min.js',
            [],
            '5.3.0',
            true
        );
    }

    /**
     * Register Meta Box on WooCommerce Product edit screen (TASK-919).
     */
    public function register_product_metabox(): void {
        add_meta_box(
            'pod_product_customizer_template',
            __('🎨 Cấu Hình Mẫu In POD (Customizer Template)', 'pod-customizer'),
            [$this, 'render_product_metabox_content'],
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render WooCommerce Product Meta Box content.
     */
    public function render_product_metabox_content(\WP_Post $post): void {
        wp_nonce_field('pod_product_metabox_action', 'pod_product_metabox_nonce');

        $current_tpl_id = get_post_meta($post->ID, '_pod_template_id', true);
        $all_templates = TemplateLoader::get_all_templates();
        $import_url = admin_url('admin.php?page=' . self::PAGE_SLUG . '&target_product_id=' . $post->ID);
        ?>
        <div style="padding: 12px 0;">
            <p style="margin-top: 0; color: #64748b; font-size: 13px;">
                <?php esc_html_e('Chọn mẫu thiết kế (Template) cá nhân hóa cho sản phẩm này. Khách hàng ngoài cửa hàng sẽ thấy trình Customizer tương ứng.', 'pod-customizer'); ?>
            </p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row" style="width: 220px;">
                        <label for="pod_template_id"><?php esc_html_e('Mẫu Template Hiện Tại', 'pod-customizer'); ?></label>
                    </th>
                    <td>
                        <select name="pod_template_id" id="pod_template_id" style="min-width: 320px; font-weight: 600;">
                            <option value=""><?php esc_html_e('-- Không sử dụng POD Customizer --', 'pod-customizer'); ?></option>
                            <?php foreach ($all_templates as $tpl_id => $tpl): ?>
                                <?php
                                $source = !empty($tpl['source_file']) ? ' (' . esc_html($tpl['source_file']) . ')' : '';
                                $layer_cnt = count($tpl['layers'] ?? []);
                                $field_cnt = count($tpl['fields'] ?? []);
                                $label = $tpl_id . $source . " - {$layer_cnt} layers, {$field_cnt} fields";
                                ?>
                                <option value="<?php echo esc_attr($tpl_id); ?>" <?php selected($current_tpl_id, $tpl_id); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <div style="margin-top: 16px; display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <a href="<?php echo esc_url($import_url); ?>" class="button button-primary" style="background: #4f46e5; border-color: #4338ca;">
                    🚀 <?php esc_html_e('Import Template Mới từ Photoshop (.PSD)', 'pod-customizer'); ?>
                </a>

                <?php if (!empty($current_tpl_id)): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&template_id=' . urlencode($current_tpl_id) . '&tab=studio')); ?>" class="button button-secondary">
                        🖌️ <?php esc_html_e('Mở trong Visual Studio để chỉnh sửa', 'pod-customizer'); ?>
                    </a>
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank" class="button button-link">
                        👁️ <?php esc_html_e('Xem Storefront ↗', 'pod-customizer'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save WooCommerce Product Meta Box selection.
     */
    public function save_product_metabox(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['pod_product_metabox_nonce']) || !wp_verify_nonce($_POST['pod_product_metabox_nonce'], 'pod_product_metabox_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['pod_template_id'])) {
            $tpl_id = sanitize_text_field($_POST['pod_template_id']);
            if (empty($tpl_id)) {
                delete_post_meta($post_id, '_pod_template_id');
                delete_post_meta($post_id, '_pod_template_config');
            } else {
                update_post_meta($post_id, '_pod_template_id', $tpl_id);

                // If template exists on disk, cache configuration into _pod_template_config
                $all_templates = TemplateLoader::get_all_templates();
                if (isset($all_templates[$tpl_id])) {
                    update_post_meta($post_id, '_pod_template_config', $all_templates[$tpl_id]);
                }
            }
        }
    }

    /**
     * AJAX handler: Save edited template configuration back to disk (and optional product).
     */
    public function ajax_save_template_config(): void {
        check_ajax_referer('pod_studio_nonce', 'security');

        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Không có quyền thực hiện.', 'pod-customizer')]);
        }

        $template_id = sanitize_text_field($_POST['template_id'] ?? '');
        $config_raw = wp_unslash($_POST['config_json'] ?? '');
        $product_id = !empty($_POST['product_id']) ? intval($_POST['product_id']) : null;

        if (empty($template_id) || empty($config_raw)) {
            wp_send_json_error(['message' => __('Dữ liệu cấu hình không hợp lệ.', 'pod-customizer')]);
        }

        $config = json_decode($config_raw, true);
        if (!is_array($config)) {
            wp_send_json_error(['message' => __('JSON không hợp lệ.', 'pod-customizer')]);
        }

        $upload_dir = wp_upload_dir();
        $template_dir = trailingslashit($upload_dir['basedir']) . 'pod-templates/' . $template_id;
        $config_file = $template_dir . '/template_config.json';

        if (!is_dir($template_dir)) {
            wp_mkdir_p($template_dir);
        }

        $saved = file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if ($saved === false) {
            wp_send_json_error(['message' => __('Không thể ghi file cấu hình vào Two-Tier storage.', 'pod-customizer')]);
        }

        // If product ID passed, update product postmeta
        if ($product_id && get_post_type($product_id) === 'product') {
            update_post_meta($product_id, '_pod_template_id', $template_id);
            update_post_meta($product_id, '_pod_template_config', $config);
        }

        wp_send_json_success([
            'message' => __('Đã lưu cấu hình Template thành công!', 'pod-customizer'),
            'template_id' => $template_id,
            'product_id' => $product_id
        ]);
    }

    /**
     * AJAX handler: Load template configuration by ID.
     */
    public function ajax_load_template_config(): void {
        check_ajax_referer('pod_studio_nonce', 'security');

        $template_id = sanitize_text_field($_GET['template_id'] ?? '');
        if (empty($template_id)) {
            wp_send_json_error(['message' => __('Chưa truyền template_id.', 'pod-customizer')]);
        }

        $all_templates = TemplateLoader::get_all_templates();
        if (isset($all_templates[$template_id])) {
            wp_send_json_success(['template' => $all_templates[$template_id]]);
        }

        wp_send_json_error(['message' => __('Không tìm thấy template với ID: ' . $template_id, 'pod-customizer')]);
    }

    /**
     * AJAX handler: Assign template directly to WooCommerce product.
     */
    public function ajax_assign_template_to_product(): void {
        check_ajax_referer('pod_studio_nonce', 'security');

        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Không có quyền thực hiện.', 'pod-customizer')]);
        }

        $template_id = sanitize_text_field($_POST['template_id'] ?? '');
        $product_id = intval($_POST['product_id'] ?? 0);

        if (!$product_id || empty($template_id)) {
            wp_send_json_error(['message' => __('Vui lòng chọn sản phẩm và template.', 'pod-customizer')]);
        }

        $all_templates = TemplateLoader::get_all_templates();
        if (!isset($all_templates[$template_id])) {
            wp_send_json_error(['message' => __('Template không tồn tại.', 'pod-customizer')]);
        }

        update_post_meta($product_id, '_pod_template_id', $template_id);
        update_post_meta($product_id, '_pod_template_config', $all_templates[$template_id]);

        $product = get_post($product_id);

        wp_send_json_success([
            'message' => sprintf(__('Đã gán template %s vào sản phẩm "%s" thành công!', 'pod-customizer'), $template_id, $product->post_title),
            'product_permalink' => get_permalink($product_id)
        ]);
    }

    /**
     * Render the main Admin Page.
     */
    public function render_page(): void {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'pod-customizer'));
        }

        $backend_url = get_option('pod_backend_url', '');
        if (empty($backend_url)) {
            $backend_url = 'http://pod-backend.localhost';
        }
        $shared_secret = get_option('pod_shared_secret', 'pod_secret_token_123456');

        $active_tab = sanitize_key($_GET['tab'] ?? 'upload');
        $initial_template_id = sanitize_text_field($_GET['template_id'] ?? '');
        $target_product_id = intval($_GET['target_product_id'] ?? 0);

        // Fetch Icon Categories for Slot Binding (TASK-916)
        $icon_categories = get_terms([
            'taxonomy' => 'pod_icon_category',
            'hide_empty' => false,
        ]);

        // Fetch WooCommerce Products for 1-click binding
        $wc_products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 50,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        // Fetch all discovered templates
        $all_templates = TemplateLoader::get_all_templates();
        if (empty($initial_template_id) && !empty($all_templates)) {
            $keys = array_keys($all_templates);
            $initial_template_id = end($keys); // Pick latest template by default
        }

        $ajax_nonce = wp_create_nonce('pod_studio_nonce');
        ?>
        <div class="wrap pod-importer-wrap" style="max-width: 1400px; margin-top: 20px;">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px 0; display: flex; align-items: center; gap: 10px;">
                        <span style="background: linear-gradient(135deg, #4f46e5, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            PSD Direct Importer &amp; Visual Studio
                        </span>
                    </h1>
                    <p style="margin: 0; color: #64748b; font-size: 14px;">
                        <?php esc_html_e('Tải trực tiếp file Photoshop (.PSD), phân tích layer ngầm 300 DPI và trực quan hóa thuộc tính canvas.', 'pod-customizer'); ?>
                    </p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=pod_icon')); ?>" class="button button-secondary">
                        ← <?php esc_html_e('Kho Icon', 'pod-customizer'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=pod-customizer-settings')); ?>" class="button button-secondary">
                        ⚙️ <?php esc_html_e('Cài Đặt POD', 'pod-customizer'); ?>
                    </a>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <h2 class="nav-tab-wrapper" style="margin-bottom: 24px;">
                <a href="#tab-upload" class="nav-tab pod-nav-tab <?php echo ($active_tab === 'upload') ? 'nav-tab-active' : ''; ?>" data-tab="tab-upload">
                    📤 <?php esc_html_e('Tải Lên PSD Mới', 'pod-customizer'); ?>
                </a>
                <a href="#tab-studio" class="nav-tab pod-nav-tab <?php echo ($active_tab === 'studio') ? 'nav-tab-active' : ''; ?>" data-tab="tab-studio">
                    🎨 <?php esc_html_e('Visual Studio & Ánh Xạ Thư Viện', 'pod-customizer'); ?>
                </a>
                <a href="#tab-templates" class="nav-tab pod-nav-tab <?php echo ($active_tab === 'templates') ? 'nav-tab-active' : ''; ?>" data-tab="tab-templates">
                    📚 <?php esc_html_e('Thư Viện Mẫu Đã Import', 'pod-customizer'); ?> (<?php echo count($all_templates); ?>)
                </a>
                <a href="#tab-guidelines" class="nav-tab pod-nav-tab <?php echo ($active_tab === 'guidelines') ? 'nav-tab-active' : ''; ?>" data-tab="tab-guidelines">
                    📖 <?php esc_html_e('Hướng Dẫn Designer (Guidelines)', 'pod-customizer'); ?>
                </a>
            </h2>

            <!-- TAB 1: UPLOAD & CHUNKING -->
            <div id="tab-upload" class="pod-tab-content" style="<?php echo ($active_tab === 'upload') ? '' : 'display: none;'; ?>">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04);">
                    <div id="pod-psd-dropzone" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 54px 24px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s ease;">
                        <div style="font-size: 54px; margin-bottom: 12px;">📁</div>
                        <h3 style="margin: 0 0 8px 0; font-size: 18px; color: #0f172a; font-weight: 700;">
                            <?php esc_html_e('Kéo thả file .PSD vào đây hoặc bấm để chọn file từ máy tính', 'pod-customizer'); ?>
                        </h3>
                        <p style="margin: 0 0 20px 0; font-size: 13px; color: #64748b; max-width: 600px; margin-left: auto; margin-right: auto;">
                            <?php esc_html_e('Hỗ trợ file PSD 50MB - 500MB+. Tự động phân mảnh 5MB trực tiếp đến Node.js và bóc tách các layer 300 DPI.', 'pod-customizer'); ?>
                        </p>
                        <input type="file" id="pod-psd-file-input" accept=".psd" style="display: none;" />
                        <button type="button" class="button button-primary" style="background: #4f46e5; border-color: #4338ca; padding: 8px 24px; font-weight: 600; font-size: 14px;" onclick="document.getElementById('pod-psd-file-input').click();">
                            <?php esc_html_e('Chọn file Photoshop (.PSD)', 'pod-customizer'); ?>
                        </button>
                    </div>

                    <!-- Progress Area -->
                    <div id="pod-upload-progress-area" style="display: none; margin-top: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div>
                                <strong id="pod-file-name" style="font-size: 14px; color: #0f172a;">template.psd</strong>
                                <span id="pod-file-size" style="font-size: 12px; color: #64748b; margin-left: 8px;">(0 MB)</span>
                            </div>
                            <div id="pod-upload-percent" style="font-size: 14px; font-weight: 700; color: #4f46e5;">0%</div>
                        </div>
                        <div style="background: #e2e8f0; height: 10px; border-radius: 9999px; overflow: hidden; margin-bottom: 12px;">
                            <div id="pod-progress-bar-fill" style="background: linear-gradient(90deg, #4f46e5, #06b6d4); width: 0%; height: 100%; transition: width 0.2s ease;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b;">
                            <span id="pod-chunk-counter"><?php esc_html_e('Đang chuẩn bị tải lên...', 'pod-customizer'); ?></span>
                            <button type="button" id="pod-cancel-upload-btn" class="button button-link-delete" style="font-size: 12px;">
                                <?php esc_html_e('Hủy', 'pod-customizer'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Live Status Area -->
                    <div id="pod-job-status-area" style="display: none; margin-top: 24px; padding: 20px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div id="pod-status-spinner" class="spinner is-active" style="float: none; margin: 0; width: 24px; height: 24px;"></div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <h4 id="pod-job-title" style="margin: 0; font-size: 15px; color: #0f172a; font-weight: 700;">
                                        <?php esc_html_e('Đang bóc tách layer PSD ngầm...', 'pod-customizer'); ?>
                                    </h4>
                                    <span id="pod-job-badge" style="background: #e0f2fe; color: #0369a1; padding: 2px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700;">
                                        QUEUED
                                    </span>
                                </div>
                                <p id="pod-job-message" style="margin: 6px 0 0 0; font-size: 13px; color: #64748b;">
                                    <?php esc_html_e('Tiến trình Worker đang khởi tạo bộ nhớ...', 'pod-customizer'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: VISUAL STUDIO & BINDING -->
            <div id="tab-studio" class="pod-tab-content" style="<?php echo ($active_tab === 'studio') ? '' : 'display: none;'; ?>">
                
                <!-- Studio Control Bar -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label for="pod-studio-template-select" style="font-weight: 700; color: #0f172a; font-size: 14px;">
                            📂 <?php esc_html_e('Mẫu Đang Soạn Thảo:', 'pod-customizer'); ?>
                        </label>
                        <select id="pod-studio-template-select" style="min-width: 280px; font-weight: 600;">
                            <?php foreach ($all_templates as $tid => $tpl): ?>
                                <option value="<?php echo esc_attr($tid); ?>" <?php selected($initial_template_id, $tid); ?>>
                                    <?php echo esc_html($tid . (!empty($tpl['source_file']) ? ' (' . $tpl['source_file'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="pod-reload-template-btn" class="button button-secondary" title="<?php esc_attr_e('Nạp lại từ đĩa', 'pod-customizer'); ?>">
                            🔄
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <!-- WooCommerce Product 1-Click Assignment -->
                        <select id="pod-assign-product-id" style="max-width: 220px;">
                            <option value=""><?php esc_html_e('-- Gán vào Sản Phẩm --', 'pod-customizer'); ?></option>
                            <?php foreach ($wc_products as $p): ?>
                                <option value="<?php echo esc_attr($p->ID); ?>" <?php selected($target_product_id, $p->ID); ?>>
                                    <?php echo esc_html($p->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="pod-assign-product-btn" class="button button-secondary" style="color: #4f46e5; border-color: #4f46e5;">
                            📦 <?php esc_html_e('Gán vào SP', 'pod-customizer'); ?>
                        </button>

                        <!-- Save & Export -->
                        <button type="button" id="pod-save-template-btn" class="button button-primary" style="background: #10b981; border-color: #059669; font-weight: 700; padding: 4px 16px;">
                            💾 <?php esc_html_e('Lưu Template', 'pod-customizer'); ?>
                        </button>
                        <button type="button" id="pod-export-json-btn" class="button button-secondary">
                            📋 <?php esc_html_e('Xuất JSON', 'pod-customizer'); ?>
                        </button>
                    </div>
                </div>

                <!-- Studio Layout Grid (Canvas + Sidebar) -->
                <div style="display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start;">
                    
                    <!-- Left: Interactive Canvas Work Area -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                            <div style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #64748b;">
                                <span>📐 <strong id="pod-canvas-dim-label">1600 x 2000 px (300 DPI)</strong></span>
                                <span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px;">Tỉ lệ hiển thị: <strong id="pod-canvas-scale-label">35%</strong></span>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button type="button" class="button button-small" id="pod-zoom-out-btn">-</button>
                                <button type="button" class="button button-small" id="pod-zoom-reset-btn"><?php esc_html_e('Vừa Khung', 'pod-customizer'); ?></button>
                                <button type="button" class="button button-small" id="pod-zoom-in-btn">+</button>
                            </div>
                        </div>

                        <!-- Canvas Viewport Box with Checkered Backdrop -->
                        <div id="pod-canvas-viewport" style="background-color: #cbd5e1; background-image: linear-gradient(45deg, #e2e8f0 25%, transparent 25%), linear-gradient(-45deg, #e2e8f0 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e2e8f0 75%), linear-gradient(-45deg, transparent 75%, #e2e8f0 75%); background-size: 20px 20px; background-position: 0 0, 0 10px, 10px -10px, -10px 0px; border-radius: 8px; padding: 24px; min-height: 600px; display: flex; align-items: center; justify-content: center; overflow: auto;">
                            <div id="pod-canvas-wrapper" style="position: relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3); border-radius: 4px; background: #ffffff;">
                                <canvas id="pod-studio-canvas"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Layers Tree & Property Inspector -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        
                        <!-- Layer Tree Card -->
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a;">
                                    📑 <?php esc_html_e('Danh Sách Layer (Z-Index)', 'pod-customizer'); ?>
                                </h3>
                                <span id="pod-layer-count-badge" style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700;">
                                    0 layers
                                </span>
                            </div>

                            <p style="margin: 0 0 12px 0; font-size: 12px; color: #94a3b8;">
                                <?php esc_html_e('Bấm vào layer để chọn, kéo thả hoặc dùng nút mũi tên để đổi thứ tự Z-index.', 'pod-customizer'); ?>
                            </p>

                            <!-- Layers List Container -->
                            <div id="pod-layers-tree-list" style="max-height: 280px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px; padding-right: 4px;">
                                <!-- Dynamically populated by JS -->
                            </div>
                        </div>

                        <!-- Property Inspector Card -->
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                            <h3 style="margin: 0 0 14px 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; justify-content: space-between;">
                                <span>🛠️ <?php esc_html_e('Thuộc Tính Layer (Inspector)', 'pod-customizer'); ?></span>
                                <span id="pod-selected-layer-type" style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: #e2e8f0; color: #334155;">
                                    NONE
                                </span>
                            </h3>

                            <div id="pod-inspector-empty-state" style="padding: 24px 12px; text-align: center; color: #94a3b8; font-size: 13px;">
                                👆 <?php esc_html_e('Chọn một layer trên canvas hoặc danh sách bên trên để chỉnh sửa thuộc tính.', 'pod-customizer'); ?>
                            </div>

                            <!-- Inspector Form (Hidden until layer selected) -->
                            <div id="pod-inspector-form" style="display: none;">
                                <div style="margin-bottom: 12px;">
                                    <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">
                                        <?php esc_html_e('Tên Layer', 'pod-customizer'); ?>
                                    </label>
                                    <input type="text" id="insp-layer-name" class="widefat" style="font-size: 13px;" />
                                </div>

                                <!-- Coordinates X, Y, W, H -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b;">Tọa độ X (px)</label>
                                        <input type="number" id="insp-coord-x" class="widefat" />
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b;">Tọa độ Y (px)</label>
                                        <input type="number" id="insp-coord-y" class="widefat" />
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b;">Chiều rộng W (px)</label>
                                        <input type="number" id="insp-coord-w" class="widefat" />
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b;">Chiều cao H (px)</label>
                                        <input type="number" id="insp-coord-h" class="widefat" />
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px;">
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b;">Góc xoay (°)</label>
                                        <input type="number" id="insp-rotation" class="widefat" value="0" />
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b;">Z-Index</label>
                                        <input type="number" id="insp-zindex" class="widefat" min="1" />
                                    </div>
                                </div>

                                <!-- TASK-916: Data Binding for Icon Slots -->
                                <div id="insp-slot-binding-section" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px; margin-bottom: 14px;">
                                    <label style="display: block; font-size: 12px; font-weight: 700; color: #1e40af; margin-bottom: 6px;">
                                        🔗 <?php esc_html_e('Ánh Xạ Thư Viện Icon (Asset Binding)', 'pod-customizer'); ?>
                                    </label>
                                    <p style="margin: 0 0 8px 0; font-size: 11px; color: #1e3a8a;">
                                        <?php esc_html_e('Liên kết slot này với danh mục Clipart trong Thư viện:', 'pod-customizer'); ?>
                                    </p>
                                    <select id="insp-slot-category" class="widefat" style="font-size: 12px; font-weight: 600;">
                                        <?php foreach ($icon_categories as $cat): ?>
                                            <option value="<?php echo esc_attr($cat->slug); ?>">
                                                <?php echo esc_html($cat->name . " ({$cat->count} icons)"); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Text Specific Properties -->
                                <div id="insp-text-section" style="display: none; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 14px;">
                                    <label style="display: block; font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
                                        🔤 <?php esc_html_e('Thuộc Tính Văn Bản (Text Slot)', 'pod-customizer'); ?>
                                    </label>
                                    <div style="margin-bottom: 8px;">
                                        <label style="font-size: 11px; color: #64748b;">Nội dung mặc định</label>
                                        <input type="text" id="insp-text-default" class="widefat" />
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                        <div>
                                            <label style="font-size: 11px; color: #64748b;">Font Family</label>
                                            <input type="text" id="insp-text-font" class="widefat" />
                                        </div>
                                        <div>
                                            <label style="font-size: 11px; color: #64748b;">Cỡ chữ (pt)</label>
                                            <input type="number" id="insp-text-size" class="widefat" />
                                        </div>
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; color: #64748b;">Màu chữ</label>
                                        <div style="display: flex; gap: 6px;">
                                            <input type="color" id="insp-text-color-picker" style="height: 32px; width: 44px; padding: 0; border: 1px solid #cbd5e1; border-radius: 4px;" />
                                            <input type="text" id="insp-text-color-hex" class="widefat" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions for Layer -->
                                <div style="display: flex; gap: 8px; justify-content: space-between; border-top: 1px solid #e2e8f0; padding-top: 12px;">
                                    <button type="button" id="insp-toggle-lock-btn" class="button button-secondary" style="flex: 1; font-size: 12px;">
                                        🔓 <?php esc_html_e('Khóa', 'pod-customizer'); ?>
                                    </button>
                                    <button type="button" id="insp-toggle-vis-btn" class="button button-secondary" style="flex: 1; font-size: 12px;">
                                        👁️ <?php esc_html_e('Ẩn/Hiện', 'pod-customizer'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- TAB 3: IMPORTED TEMPLATES LIST -->
            <div id="tab-templates" class="pod-tab-content" style="<?php echo ($active_tab === 'templates') ? '' : 'display: none;'; ?>">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04);">
                    <h3 style="margin: 0 0 16px 0; font-size: 17px; font-weight: 700; color: #0f172a;">
                        📚 <?php esc_html_e('Danh Sách Mẫu Thiết Kế Đã Bóc Tách (Two-Tier Storage)', 'pod-customizer'); ?>
                    </h3>

                    <?php if (empty($all_templates)): ?>
                        <p style="color: #64748b; font-style: italic;"><?php esc_html_e('Chưa có template nào được import. Hãy tải lên file PSD đầu tiên ở Tab 1.', 'pod-customizer'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 280px;"><?php esc_html_e('Template ID / Tên File', 'pod-customizer'); ?></th>
                                    <th style="width: 140px;"><?php esc_html_e('Kích Thước In', 'pod-customizer'); ?></th>
                                    <th style="width: 100px;"><?php esc_html_e('Số Layer', 'pod-customizer'); ?></th>
                                    <th style="width: 120px;"><?php esc_html_e('Số Input Slot', 'pod-customizer'); ?></th>
                                    <th><?php esc_html_e('Đặc Điểm Cá Nhân Hóa (100% Layer-Driven)', 'pod-customizer'); ?></th>
                                    <th style="width: 160px; text-align: right;"><?php esc_html_e('Thao Tác', 'pod-customizer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_templates as $tid => $tpl): ?>
                                    <?php
                                    $w = $tpl['print_spec']['width_px'] ?? 0;
                                    $h = $tpl['print_spec']['height_px'] ?? 0;
                                    $layers_cnt = count($tpl['layers'] ?? []);
                                    $fields_cnt = count($tpl['fields'] ?? []);
                                    $source = $tpl['source_file'] ?? 'psd_source.psd';
                                    
                                    // Highlight layer selector groups (Skin tone, Hair style)
                                    $selector_badges = [];
                                    if (!empty($tpl['fields'])) {
                                        foreach ($tpl['fields'] as $f) {
                                            if (($f['type'] ?? '') === 'layer_selector') {
                                                $selector_badges[] = '<span style="background: #fdf2f8; color: #9d174d; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 4px;">🎨 ' . esc_html($f['label'] ?? $f['id']) . '</span>';
                                            } elseif (($f['type'] ?? '') === 'preset_picker') {
                                                $selector_badges[] = '<span style="background: #eff6ff; color: #1e40af; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 4px;">⭐ ' . esc_html($f['label'] ?? 'Icon Slot') . '</span>';
                                            }
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color: #4f46e5;"><?php echo esc_html($tid); ?></strong>
                                            <div style="font-size: 12px; color: #64748b;"><?php echo esc_html($source); ?></div>
                                        </td>
                                        <td><?php echo esc_html("{$w} × {$h} px @ 300 DPI"); ?></td>
                                        <td><strong><?php echo esc_html($layers_cnt); ?></strong></td>
                                        <td><strong><?php echo esc_html($fields_cnt); ?></strong></td>
                                        <td>
                                            <?php echo !empty($selector_badges) ? implode(' ', $selector_badges) : '<span style="color:#94a3b8;">Cơ bản</span>'; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&template_id=' . urlencode($tid) . '&tab=studio')); ?>" class="button button-primary" style="background: #4f46e5; border-color: #4338ca; font-size: 12px;">
                                                🎨 <?php esc_html_e('Mở Studio', 'pod-customizer'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 4: DESIGN GUIDELINES (TASK-918) -->
            <div id="tab-guidelines" class="pod-tab-content" style="<?php echo ($active_tab === 'guidelines') ? '' : 'display: none;'; ?>">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04); max-width: 900px;">
                    <h2 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #0f172a;">
                        📖 <?php esc_html_e('Tài Liệu Hướng Dẫn Thiết Kế File Photoshop (.PSD) Cho Designer', 'pod-customizer'); ?>
                    </h2>
                    <p style="color: #64748b; font-size: 14px; margin-bottom: 24px;">
                        <?php esc_html_e('Tuân thủ các quy chuẩn dưới đây giúp hệ thống tự động nhận diện layer chính xác 100%, giảm 70% dung lượng file và tăng 5x tốc độ bóc tách.', 'pod-customizer'); ?>
                    </p>

                    <!-- Section 1 -->
                    <div style="margin-bottom: 24px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #1e40af; margin: 0 0 10px 0;">
                            1. Quy Tắc Làm Sạch File (Clean-up Rules)
                        </h3>
                        <ul style="list-style: disc; margin-left: 20px; font-size: 13px; color: #334155; line-height: 1.8;">
                            <li><strong>Rasterize Smart Objects:</strong> Chuột phải vào Smart Object và chọn <em>Rasterize Layer</em> trước khi lưu. Loại bỏ các file gốc nhúng ẩn, giảm dung lượng PSD từ 400MB xuống 50MB - 80MB.</li>
                            <li><strong>Rasterize Layer Styles:</strong> Các hiệu ứng bóng đổ (Drop Shadow), viền (Stroke) cần được gộp thẳng vào pixel của layer để đảm bảo đồng nhất mỹ thuật khi in.</li>
                            <li><strong>Xóa Layer Nháp &amp; Layer Ẩn:</strong> Xóa các layer nháp không sử dụng để tránh tạo file PNG rác.</li>
                            <li><strong>Tắt Maximize Compatibility:</strong> Khi bấm <em>Save As</em> trong Photoshop, bỏ chọn <em>Maximize Compatibility</em> để tránh lưu thêm ảnh phẳng dự phòng.</li>
                        </ul>
                    </div>

                    <!-- Section 2: 100% Layer Driven Alert -->
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 18px 20px; margin-bottom: 24px;">
                        <h4 style="margin: 0 0 8px 0; color: #991b1b; font-size: 14px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                            <span>⚠️</span> NGUYÊN TẮC CỐT LÕI: 100% LAYER-DRIVEN CHO MÀU DA VÀ KIỂU TÓC
                        </h4>
                        <p style="margin: 0; font-size: 13px; color: #7f1d1d; line-height: 1.6;">
                            Hệ thống <strong>TUYỆT ĐỐI KHÔNG SỬ DỤNG MÃ MÀU HEX / COLOR PICKER</strong> cho Màu da và Kiểu tóc. Designer cần vẽ và tạo các layer ảnh độc lập (ví dụ: da trắng, da tự nhiên, da ngăm; tóc bob nâu, tóc bob vàng, tóc xoăn đen...) và đặt trong các Folder có tiền tố <code>[group:skin]</code> hoặc <code>[group:hair]</code>. Hệ thống sẽ xuất từng layer thành PNG 300 DPI trong suốt và hiển thị bộ chọn ngoài cửa hàng để khách bật/tắt layer trực quan.
                        </p>
                    </div>

                    <!-- Section 3: Naming Convention Table -->
                    <div style="margin-bottom: 28px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #1e40af; margin: 0 0 10px 0;">
                            2. Bảng Quy Ước Tiền Tố Đặt Tên Layer &amp; Folder
                        </h3>
                        <table class="wp-list-table widefat striped" style="font-size: 12px;">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">Tiền tố tên Layer / Folder</th>
                                    <th style="width: 140px;">Loại Layer</th>
                                    <th>Hành vi hệ thống &amp; Hiển thị Storefront</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>[fixed] Tên_Layer</code></td>
                                    <td><strong>fixed_image</strong></td>
                                    <td>Xuất thành PNG 300 DPI, cố định vị trí, khách không được sửa.</td>
                                </tr>
                                <tr>
                                    <td><code>[group:skin] Mau_Da</code></td>
                                    <td><strong>layer_selector</strong></td>
                                    <td>Folder chứa các biến thể da (fair, medium, dark). Tự động sinh swatch chọn màu da.</td>
                                </tr>
                                <tr>
                                    <td><code>[group:hair] Kieu_Toc</code></td>
                                    <td><strong>layer_selector</strong></td>
                                    <td>Folder chứa các kiểu tóc. Tự động sinh dropdown/swatch chọn kiểu tóc.</td>
                                </tr>
                                <tr>
                                    <td><code>[slot:icon] Pet_Icon</code></td>
                                    <td><strong>preset_picker</strong></td>
                                    <td>Vùng giữ chỗ cho icon thư viện. Admin có thể ánh xạ sang Category (Zodiac, Thú cưng...).</td>
                                </tr>
                                <tr>
                                    <td><code>[text] Recipient_Name</code></td>
                                    <td><strong>text</strong></td>
                                    <td>Ô nhập chữ cho khách. Tự trích xuất Font, Size, Color và tọa độ khung gõ chữ.</td>
                                </tr>
                                <tr>
                                    <td><code>[repeater] Pet_Paw</code></td>
                                    <td><strong>repeater_counter</strong></td>
                                    <td>Vùng nhân bản số lượng phần tử tự động (dấu chân, nến sinh nhật...).</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sample Download -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 18px 20px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h4 style="margin: 0 0 4px 0; color: #166534; font-size: 14px; font-weight: 700;">
                                📥 <?php esc_html_e('Tải File Photoshop Mẫu Chuẩn (Reference Sample PSD)', 'pod-customizer'); ?>
                            </h4>
                            <p style="margin: 0; font-size: 12px; color: #15803d;">
                                <?php esc_html_e('File mẫu portrait 1600x2000 px đầy đủ background, group màu da, kiểu tóc, icon slot và text layer.', 'pod-customizer'); ?>
                            </p>
                        </div>
                        <a href="<?php echo esc_url(home_url('/wp-content/uploads/sample_portrait_template.psd')); ?>" class="button button-primary" style="background: #10b981; border-color: #059669; font-weight: 700;" download>
                            <?php esc_html_e('Tải File .PSD Mẫu ⬇', 'pod-customizer'); ?>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Studio Client-side Engine Scripts -->
        <script>
        jQuery(document).ready(function($) {
            var backendUrl = '<?php echo esc_js(rtrim($backend_url, '/')); ?>';
            var sharedSecret = '<?php echo esc_js($shared_secret); ?>';
            var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var ajaxNonce = '<?php echo esc_js($ajax_nonce); ?>';
            var chunkSize = 5 * 1024 * 1024; // 5MB chunks
            var currentTemplateConfig = null;
            var fabricCanvas = null;
            var selectedLayerObject = null;
            var currentZoomScale = 0.35;

            // Tab Switching
            $('.pod-nav-tab').on('click', function(e) {
                e.preventDefault();
                var targetTab = $(this).data('tab');
                $('.pod-nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.pod-tab-content').hide();
                $('#' + targetTab).show();

                if (targetTab === 'tab-studio') {
                    if (!fabricCanvas) {
                        initFabricStudio();
                    }
                    var selTpl = $('#pod-studio-template-select').val();
                    if (selTpl && (!currentTemplateConfig || currentTemplateConfig.template_id !== selTpl)) {
                        loadTemplateIntoStudio(selTpl);
                    }
                }
            });

            // -------------------------------------------------------------
            // SECTION 1: CHUNKED UPLOAD LOGIC
            // -------------------------------------------------------------
            var $dropzone = $('#pod-psd-dropzone');
            var $fileInput = $('#pod-psd-file-input');
            var $progressArea = $('#pod-upload-progress-area');
            var $progressBar = $('#pod-progress-bar-fill');
            var $percentLabel = $('#pod-upload-percent');
            var $fileName = $('#pod-file-name');
            var $fileSize = $('#pod-file-size');
            var $chunkCounter = $('#pod-chunk-counter');
            var $cancelBtn = $('#pod-cancel-upload-btn');
            var $jobArea = $('#pod-job-status-area');
            var $jobTitle = $('#pod-job-title');
            var $jobBadge = $('#pod-job-badge');
            var $jobMessage = $('#pod-job-message');
            var $spinner = $('#pod-status-spinner');
            var pollInterval = null;

            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $dropzone.css({ borderColor: '#4f46e5', background: '#f5f3ff' });
            });

            $dropzone.on('dragleave dragend drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $dropzone.css({ borderColor: '#cbd5e1', background: '#f8fafc' });
            });

            $dropzone.on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files && files.length > 0) handleFileSelection(files[0]);
            });

            $fileInput.on('change', function() {
                if (this.files && this.files.length > 0) handleFileSelection(this.files[0]);
            });

            function formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                var k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            function handleFileSelection(file) {
                if (!file.name.toLowerCase().endsWith('.psd')) {
                    alert('<?php echo esc_js(__('Vui lòng chọn file Adobe Photoshop định dạng .psd', 'pod-customizer')); ?>');
                    return;
                }

                $fileName.text(file.name);
                $fileSize.text('(' + formatBytes(file.size) + ')');
                $progressArea.show();
                $jobArea.hide();
                $progressBar.css('width', '0%').css('background', 'linear-gradient(90deg, #4f46e5, #06b6d4)');
                $percentLabel.text('0%');

                startChunkedUpload(file);
            }

            function startChunkedUpload(file) {
                var totalSize = file.size;
                var totalChunks = Math.ceil(totalSize / chunkSize);
                var uploadId = 'up_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);
                var isAborted = false;

                $cancelBtn.off('click').on('click', function() {
                    isAborted = true;
                    $chunkCounter.text('<?php echo esc_js(__('Upload đã bị hủy.', 'pod-customizer')); ?>');
                    $progressBar.css('background', '#ef4444');
                });

                function uploadChunk(index) {
                    if (isAborted) return;
                    var start = index * chunkSize;
                    var end = Math.min(totalSize, start + chunkSize);
                    var chunkBlob = file.slice(start, end);

                    var formData = new FormData();
                    formData.append('upload_id', uploadId);
                    formData.append('chunk_index', index);
                    formData.append('total_chunks', totalChunks);
                    formData.append('total_size', totalSize);
                    formData.append('filename', file.name);
                    formData.append('file_chunk', chunkBlob);

                    var percent = Math.round(((index) / totalChunks) * 100);
                    $progressBar.css('width', percent + '%');
                    $percentLabel.text(percent + '%');
                    $chunkCounter.text('<?php echo esc_js(__('Đang gửi mảnh', 'pod-customizer')); ?> ' + (index + 1) + '/' + totalChunks + '...');

                    $.ajax({
                        url: backendUrl + '/api/templates/upload-chunk',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        headers: { 'X-POD-Secret': sharedSecret },
                        success: function(res) {
                            if (isAborted) return;
                            if (res && res.status === 'completed') {
                                $progressBar.css('width', '100%');
                                $percentLabel.text('100%');
                                $chunkCounter.text('✅ <?php echo esc_js(__('Ghép file PSD thành công! Đang bóc tách layer ngầm...', 'pod-customizer')); ?>');
                                showJobStatus(res.job_id);
                            } else if (index + 1 < totalChunks) {
                                uploadChunk(index + 1);
                            }
                        },
                        error: function(xhr) {
                            var errMsg = '<?php echo esc_js(__('Lỗi kết nối tải lên máy chủ.', 'pod-customizer')); ?>';
                            try {
                                var errRes = JSON.parse(xhr.responseText);
                                if (errRes && errRes.error) errMsg = errRes.error;
                            } catch (e) {}
                            $progressBar.css('background', '#ef4444');
                            $chunkCounter.html('<span style="color: #ef4444;">❌ ' + errMsg + '</span>');
                        }
                    });
                }

                uploadChunk(0);
            }

            function showJobStatus(jobId) {
                $jobArea.show();
                $jobBadge.text('QUEUED').css({ background: '#e0f2fe', color: '#0369a1' });
                $jobMessage.text('<?php echo esc_js(__('File PSD đã được đưa vào hàng đợi bóc tách layer ngầm.', 'pod-customizer')); ?> (Job ID: ' + jobId + ')');

                if (pollInterval) clearInterval(pollInterval);

                pollInterval = setInterval(function() {
                    $.ajax({
                        url: backendUrl + '/api/templates/jobs/' + encodeURIComponent(jobId) + '/status',
                        type: 'GET',
                        headers: { 'X-POD-Secret': sharedSecret },
                        success: function(res) {
                            if (res && res.data) {
                                var job = res.data;
                                $jobMessage.text(job.message || 'Đang xử lý...');

                                if (job.status === 'processing') {
                                    $jobBadge.text('PROCESSING ' + (job.progress || 0) + '%').css({ background: '#fef3c7', color: '#92400e' });
                                } else if (job.status === 'completed') {
                                    clearInterval(pollInterval);
                                    $spinner.removeClass('is-active');
                                    $jobBadge.text('COMPLETED').css({ background: '#dcfce7', color: '#166534' });
                                    $jobTitle.text('🎉 ' + '<?php echo esc_js(__('Hoàn tất bóc tách PSD!', 'pod-customizer')); ?>');
                                    
                                    var tplId = job.result ? job.result.template_id : '';
                                    var btnHtml = '<button type="button" class="button button-primary" id="pod-jump-to-studio-btn" style="background: #10b981; border-color: #059669; font-weight: 700; margin-top: 10px;">' +
                                        '🎨 <?php echo esc_js(__('Mở Visual Studio để xem & gán sản phẩm ➔', 'pod-customizer')); ?></button>';
                                    
                                    $jobMessage.html('<?php echo esc_js(__('File PSD đã được bóc tách layer 300 DPI thành công!', 'pod-customizer')); ?><br>' + btnHtml);

                                    $('#pod-jump-to-studio-btn').on('click', function() {
                                        if (tplId) {
                                            $('#pod-studio-template-select').append($('<option>', {
                                                value: tplId,
                                                text: tplId,
                                                selected: true
                                            }));
                                            loadTemplateIntoStudio(tplId);
                                        }
                                        $('[data-tab="tab-studio"]').click();
                                    });

                                } else if (job.status === 'failed') {
                                    clearInterval(pollInterval);
                                    $spinner.removeClass('is-active');
                                    $jobBadge.text('FAILED').css({ background: '#fee2e2', color: '#991b1b' });
                                    $jobTitle.text('❌ ' + '<?php echo esc_js(__('Xử lý thất bại', 'pod-customizer')); ?>');
                                    $jobMessage.text(job.error || 'Có lỗi xảy ra trong quá trình bóc tách layer.');
                                }
                            }
                        }
                    });
                }, 1500);
            }

            // -------------------------------------------------------------
            // SECTION 2: VISUAL STUDIO CANVAS & PROPERTY INSPECTOR
            // -------------------------------------------------------------
            function initFabricStudio() {
                fabricCanvas = new fabric.Canvas('pod-studio-canvas', {
                    selection: true,
                    preserveObjectStacking: true
                });

                // Selection listeners
                fabricCanvas.on('selection:created', function(e) {
                    if (e.selected && e.selected.length > 0) selectLayerInStudio(e.selected[0]);
                });
                fabricCanvas.on('selection:updated', function(e) {
                    if (e.selected && e.selected.length > 0) selectLayerInStudio(e.selected[0]);
                });
                fabricCanvas.on('selection:cleared', function() {
                    deselectLayerInStudio();
                });

                // Dragging / Resizing / Rotating live synchronization (TASK-915)
                fabricCanvas.on('object:moving', function(e) {
                    syncObjectToInspector(e.target);
                });
                fabricCanvas.on('object:scaling', function(e) {
                    syncObjectToInspector(e.target);
                });
                fabricCanvas.on('object:rotating', function(e) {
                    syncObjectToInspector(e.target);
                });
            }

            function loadTemplateIntoStudio(templateId) {
                if (!templateId) return;

                $.ajax({
                    url: ajaxUrl,
                    type: 'GET',
                    data: {
                        action: 'pod_load_template_config',
                        security: ajaxNonce,
                        template_id: templateId
                    },
                    success: function(res) {
                        if (res && res.success && res.data && res.data.template) {
                            currentTemplateConfig = res.data.template;
                            renderTemplateOnCanvas(currentTemplateConfig);
                            renderLayersTree(currentTemplateConfig);
                        } else {
                            alert('Không thể tải cấu hình template: ' + (res.data ? res.data.message : ''));
                        }
                    },
                    error: function() {
                        alert('Lỗi kết nối máy chủ WordPress.');
                    }
                });
            }

            function renderTemplateOnCanvas(cfg) {
                if (!fabricCanvas) initFabricStudio();
                fabricCanvas.clear();

                var printWidth = (cfg.print_spec && cfg.print_spec.width_px) ? cfg.print_spec.width_px : 1600;
                var printHeight = (cfg.print_spec && cfg.print_spec.height_px) ? cfg.print_spec.height_px : 2000;

                // Calibrate scale to fit maximum 500x650 viewport
                var maxCanvasW = 500;
                currentZoomScale = maxCanvasW / printWidth;
                var canvasDisplayW = Math.round(printWidth * currentZoomScale);
                var canvasDisplayH = Math.round(printHeight * currentZoomScale);

                fabricCanvas.setWidth(canvasDisplayW);
                fabricCanvas.setHeight(canvasDisplayH);

                $('#pod-canvas-dim-label').text(printWidth + ' × ' + printHeight + ' px @ 300 DPI');
                $('#pod-canvas-scale-label').text(Math.round(currentZoomScale * 100) + '%');

                // Draw safe zone margin guide (dashed red rectangle)
                var marginPx = (cfg.print_spec && cfg.print_spec.safe_zone_margin_px) ? cfg.print_spec.safe_zone_margin_px : 30;
                var guideRect = new fabric.Rect({
                    left: marginPx * currentZoomScale,
                    top: marginPx * currentZoomScale,
                    width: (printWidth - 2 * marginPx) * currentZoomScale,
                    height: (printHeight - 2 * marginPx) * currentZoomScale,
                    fill: 'rgba(0,0,0,0)',
                    stroke: 'rgba(239, 68, 68, 0.4)',
                    strokeDashArray: [4, 4],
                    selectable: false,
                    evented: false,
                    isGuide: true
                });
                fabricCanvas.add(guideRect);

                // Sort layers by Z-Index
                var layers = (cfg.layers || []).slice().sort(function(a, b) {
                    return (a.z_index || 1) - (b.z_index || 1);
                });

                // Render each layer onto Fabric canvas
                layers.forEach(function(layer) {
                    var x = (layer.x || 0) * currentZoomScale;
                    var y = (layer.y || 0) * currentZoomScale;
                    var w = (layer.width || 200) * currentZoomScale;
                    var h = (layer.height || 200) * currentZoomScale;
                    var rot = layer.rotation || 0;
                    var isVisible = layer.always_visible !== false;

                    if (layer.type === 'text') {
                        var textObj = new fabric.Text(layer.default_value || 'Sample Text', {
                            left: x,
                            top: y,
                            fontFamily: layer.font_family || 'Montserrat',
                            fontSize: Math.round((layer.font_size_pt || 48) * currentZoomScale * 1.33),
                            fill: layer.color || '#1e293b',
                            angle: rot,
                            visible: isVisible,
                            podLayerData: layer
                        });
                        fabricCanvas.add(textObj);

                    } else if (layer.type === 'preset_picker') {
                        // Icon Slot Placeholder
                        var placeholderUrl = layer.placeholder_url;
                        if (placeholderUrl) {
                            fabric.Image.fromURL(placeholderUrl, function(img) {
                                img.set({
                                    left: x,
                                    top: y,
                                    scaleX: (w / img.width),
                                    scaleY: (h / img.height),
                                    angle: rot,
                                    visible: isVisible,
                                    podLayerData: layer
                                });
                                fabricCanvas.add(img);
                                fabricCanvas.renderAll();
                            }, { crossOrigin: 'anonymous' });
                        } else {
                            var slotBox = new fabric.Rect({
                                left: x,
                                top: y,
                                width: w,
                                height: h,
                                fill: 'rgba(79, 70, 229, 0.15)',
                                stroke: '#4f46e5',
                                strokeDashArray: [6, 4],
                                angle: rot,
                                visible: isVisible,
                                podLayerData: layer
                            });
                            fabricCanvas.add(slotBox);
                        }

                    } else if (layer.url) {
                        // Image layer (Fixed, Group variant, Repeater)
                        fabric.Image.fromURL(layer.url, function(img) {
                            img.set({
                                left: x,
                                top: y,
                                scaleX: (w / img.width),
                                scaleY: (h / img.height),
                                angle: rot,
                                visible: isVisible,
                                podLayerData: layer
                            });
                            fabricCanvas.add(img);
                            fabricCanvas.renderAll();
                        }, { crossOrigin: 'anonymous' });
                    }
                });

                fabricCanvas.renderAll();
            }

            function renderLayersTree(cfg) {
                var $list = $('#pod-layers-tree-list');
                $list.empty();

                var layers = (cfg.layers || []).slice().sort(function(a, b) {
                    return (b.z_index || 1) - (a.z_index || 1); // Display top layers at top of list
                });

                $('#pod-layer-count-badge').text(layers.length + ' layers');

                layers.forEach(function(layer, idx) {
                    var badgeBg = '#f1f5f9', badgeCol = '#475569', badgeTxt = 'FIXED';
                    if (layer.group_id) { badgeBg = '#fdf2f8'; badgeCol = '#9d174d'; badgeTxt = layer.group_id.toUpperCase(); }
                    else if (layer.type === 'preset_picker') { badgeBg = '#eff6ff'; badgeCol = '#1e40af'; badgeTxt = 'ICON SLOT'; }
                    else if (layer.type === 'text') { badgeBg = '#fef3c7'; badgeCol = '#92400e'; badgeTxt = 'TEXT'; }

                    var isVisible = layer.always_visible !== false;
                    var isLocked = layer.locked === true;

                    var $item = $('<div class="pod-layer-tree-item" data-layer-id="' + layer.id + '" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; cursor: pointer; transition: all 0.15s ease;">' +
                        '<div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">' +
                            '<span style="background:' + badgeBg + '; color:' + badgeCol + '; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase;">' + badgeTxt + '</span>' +
                            '<span style="font-size: 12px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;" title="' + layer.name + '">' + layer.name + '</span>' +
                        '</div>' +
                        '<div style="display: flex; align-items: center; gap: 4px;">' +
                            '<button type="button" class="button button-small pod-tree-vis-btn" title="Ẩn/Hiện" style="padding: 0 4px; height: 22px; line-height: 20px; font-size: 11px;">' + (isVisible ? '👁️' : '🕶️') + '</button>' +
                            '<button type="button" class="button button-small pod-tree-lock-btn" title="Khóa" style="padding: 0 4px; height: 22px; line-height: 20px; font-size: 11px;">' + (isLocked ? '🔒' : '🔓') + '</button>' +
                        '</div>' +
                    '</div>');

                    $item.on('click', function(e) {
                        if ($(e.target).closest('button').length > 0) return;
                        selectLayerById(layer.id);
                    });

                    // Visibility toggle (TASK-917)
                    $item.find('.pod-tree-vis-btn').on('click', function(e) {
                        e.stopPropagation();
                        toggleLayerVisibility(layer.id);
                    });

                    // Lock toggle (TASK-917)
                    $item.find('.pod-tree-lock-btn').on('click', function(e) {
                        e.stopPropagation();
                        toggleLayerLock(layer.id);
                    });

                    $list.append($item);
                });
            }

            function selectLayerById(layerId) {
                var foundObj = null;
                fabricCanvas.getObjects().forEach(function(obj) {
                    if (obj.podLayerData && obj.podLayerData.id === layerId) {
                        foundObj = obj;
                    }
                });

                if (foundObj) {
                    fabricCanvas.setActiveObject(foundObj);
                    fabricCanvas.renderAll();
                    selectLayerInStudio(foundObj);
                }
            }

            function selectLayerInStudio(obj) {
                selectedLayerObject = obj;
                var layer = obj.podLayerData;
                if (!layer) return;

                $('.pod-layer-tree-item').css({ borderColor: '#e2e8f0', background: '#ffffff' });
                $('.pod-layer-tree-item[data-layer-id="' + layer.id + '"]').css({ borderColor: '#4f46e5', background: '#eef2ff' });

                $('#pod-inspector-empty-state').hide();
                $('#pod-inspector-form').show();
                $('#pod-selected-layer-type').text((layer.type || 'IMAGE').toUpperCase());

                $('#insp-layer-name').val(layer.name || '');
                $('#insp-coord-x').val(Math.round(layer.x || 0));
                $('#insp-coord-y').val(Math.round(layer.y || 0));
                $('#insp-coord-w').val(Math.round(layer.width || 0));
                $('#insp-coord-h').val(Math.round(layer.height || 0));
                $('#insp-rotation').val(Math.round(layer.rotation || 0));
                $('#insp-zindex').val(layer.z_index || 1);

                // Slot binding for preset_picker (TASK-916)
                if (layer.type === 'preset_picker') {
                    $('#insp-slot-binding-section').show();
                    var boundField = (currentTemplateConfig.fields || []).find(function(f) {
                        return f.target_layer === layer.id || f.id.indexOf(layer.id) !== -1;
                    });
                    if (boundField && boundField.category_slug) {
                        $('#insp-slot-category').val(boundField.category_slug);
                    }
                } else {
                    $('#insp-slot-binding-section').hide();
                }

                // Text section
                if (layer.type === 'text') {
                    $('#insp-text-section').show();
                    $('#insp-text-default').val(layer.default_value || '');
                    $('#insp-text-font').val(layer.font_family || 'Montserrat');
                    $('#insp-text-size').val(layer.font_size_pt || 48);
                    $('#insp-text-color-picker').val(layer.color || '#1e293b');
                    $('#insp-text-color-hex').val(layer.color || '#1e293b');
                } else {
                    $('#insp-text-section').hide();
                }

                // Lock button state
                $('#insp-toggle-lock-btn').text(layer.locked ? '🔒 Đang Khóa' : '🔓 Đang Mở');
                $('#insp-toggle-vis-btn').text(layer.always_visible === false ? '🕶️ Đang Ẩn' : '👁️ Đang Hiện');
            }

            function deselectLayerInStudio() {
                selectedLayerObject = null;
                $('.pod-layer-tree-item').css({ borderColor: '#e2e8f0', background: '#ffffff' });
                $('#pod-inspector-empty-state').show();
                $('#pod-inspector-form').hide();
                $('#pod-selected-layer-type').text('NONE');
            }

            function syncObjectToInspector(obj) {
                if (!obj || !obj.podLayerData) return;
                var layer = obj.podLayerData;

                var realX = Math.round(obj.left / currentZoomScale);
                var realY = Math.round(obj.top / currentZoomScale);
                var realW = Math.round((obj.width * (obj.scaleX || 1)) / currentZoomScale);
                var realH = Math.round((obj.height * (obj.scaleY || 1)) / currentZoomScale);
                var rot = Math.round(obj.angle || 0);

                layer.x = realX;
                layer.y = realY;
                layer.width = realW;
                layer.height = realH;
                layer.rotation = rot;

                $('#insp-coord-x').val(realX);
                $('#insp-coord-y').val(realY);
                $('#insp-coord-w').val(realW);
                $('#insp-coord-h').val(realH);
                $('#insp-rotation').val(rot);
            }

            // Real-time Property Inspector inputs listener (TASK-915)
            $('#insp-coord-x, #insp-coord-y, #insp-coord-w, #insp-coord-h, #insp-rotation, #insp-zindex').on('input change', function() {
                if (!selectedLayerObject || !selectedLayerObject.podLayerData) return;
                var layer = selectedLayerObject.podLayerData;

                var x = parseInt($('#insp-coord-x').val(), 10) || 0;
                var y = parseInt($('#insp-coord-y').val(), 10) || 0;
                var w = parseInt($('#insp-coord-w').val(), 10) || 10;
                var h = parseInt($('#insp-coord-h').val(), 10) || 10;
                var rot = parseInt($('#insp-rotation').val(), 10) || 0;
                var z = parseInt($('#insp-zindex').val(), 10) || 1;

                layer.x = x;
                layer.y = y;
                layer.width = w;
                layer.height = h;
                layer.rotation = rot;
                layer.z_index = z;

                selectedLayerObject.set({
                    left: x * currentZoomScale,
                    top: y * currentZoomScale,
                    scaleX: (w * currentZoomScale) / selectedLayerObject.width,
                    scaleY: (h * currentZoomScale) / selectedLayerObject.height,
                    angle: rot
                });

                fabricCanvas.renderAll();
            });

            // Slot Category Binding Listener (TASK-916)
            $('#insp-slot-category').on('change', function() {
                if (!selectedLayerObject || !selectedLayerObject.podLayerData) return;
                var layer = selectedLayerObject.podLayerData;
                var newCat = $(this).val();

                if (currentTemplateConfig && currentTemplateConfig.fields) {
                    var boundField = currentTemplateConfig.fields.find(function(f) {
                        return f.target_layer === layer.id || f.id.indexOf(layer.id) !== -1;
                    });
                    if (boundField) {
                        boundField.category_slug = newCat;
                    }
                }
            });

            // Toggle Lock (TASK-917)
            function toggleLayerLock(layerId) {
                var layer = (currentTemplateConfig.layers || []).find(function(l) { return l.id === layerId; });
                if (!layer) return;

                layer.locked = !layer.locked;

                fabricCanvas.getObjects().forEach(function(obj) {
                    if (obj.podLayerData && obj.podLayerData.id === layerId) {
                        obj.set({
                            lockMovementX: layer.locked,
                            lockMovementY: layer.locked,
                            lockScalingX: layer.locked,
                            lockScalingY: layer.locked,
                            lockRotation: layer.locked,
                            hasControls: !layer.locked
                        });
                    }
                });

                fabricCanvas.renderAll();
                renderLayersTree(currentTemplateConfig);
                if (selectedLayerObject && selectedLayerObject.podLayerData.id === layerId) {
                    $('#insp-toggle-lock-btn').text(layer.locked ? '🔒 Đang Khóa' : '🔓 Đang Mở');
                }
            }

            // Toggle Visibility (TASK-917)
            function toggleLayerVisibility(layerId) {
                var layer = (currentTemplateConfig.layers || []).find(function(l) { return l.id === layerId; });
                if (!layer) return;

                layer.always_visible = !(layer.always_visible !== false);

                fabricCanvas.getObjects().forEach(function(obj) {
                    if (obj.podLayerData && obj.podLayerData.id === layerId) {
                        obj.set({ visible: layer.always_visible });
                    }
                });

                fabricCanvas.renderAll();
                renderLayersTree(currentTemplateConfig);
                if (selectedLayerObject && selectedLayerObject.podLayerData.id === layerId) {
                    $('#insp-toggle-vis-btn').text(layer.always_visible === false ? '🕶️ Đang Ẩn' : '👁️ Đang Hiện');
                }
            }

            $('#insp-toggle-lock-btn').on('click', function() {
                if (selectedLayerObject && selectedLayerObject.podLayerData) {
                    toggleLayerLock(selectedLayerObject.podLayerData.id);
                }
            });

            $('#insp-toggle-vis-btn').on('click', function() {
                if (selectedLayerObject && selectedLayerObject.podLayerData) {
                    toggleLayerVisibility(selectedLayerObject.podLayerData.id);
                }
            });

            // Template Selector change
            $('#pod-studio-template-select').on('change', function() {
                loadTemplateIntoStudio($(this).val());
            });
            $('#pod-reload-template-btn').on('click', function() {
                loadTemplateIntoStudio($('#pod-studio-template-select').val());
            });

            // Save Template Configuration via AJAX
            $('#pod-save-template-btn').on('click', function() {
                if (!currentTemplateConfig) return;
                var $btn = $(this);
                $btn.prop('disabled', true).text('⏳ Đang Lưu...');

                var productId = $('#pod-assign-product-id').val();

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pod_save_template_config',
                        security: ajaxNonce,
                        template_id: currentTemplateConfig.template_id,
                        config_json: JSON.stringify(currentTemplateConfig),
                        product_id: productId
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('💾 Lưu Template');
                        if (res && res.success) {
                            alert('✅ ' + (res.data.message || 'Lưu thành công!'));
                        } else {
                            alert('❌ ' + (res.data ? res.data.message : 'Lỗi khi lưu.'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('💾 Lưu Template');
                        alert('Lỗi kết nối máy chủ.');
                    }
                });
            });

            // Assign Template directly to WooCommerce Product
            $('#pod-assign-product-btn').on('click', function() {
                var pId = $('#pod-assign-product-id').val();
                if (!pId) {
                    alert('Vui lòng chọn một sản phẩm WooCommerce trong danh sách thả xuống.');
                    return;
                }
                if (!currentTemplateConfig) return;

                var $btn = $(this);
                $btn.prop('disabled', true).text('⏳ Đang Gán...');

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'pod_assign_template_to_product',
                        security: ajaxNonce,
                        template_id: currentTemplateConfig.template_id,
                        product_id: pId
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('📦 Gán vào SP');
                        if (res && res.success) {
                            var permalink = res.data.product_permalink;
                            if (confirm(res.data.message + '\n\nBạn có muốn mở trang sản phẩm ngoài Storefront để xem trước không?')) {
                                window.open(permalink, '_blank');
                            }
                        } else {
                            alert('❌ ' + (res.data ? res.data.message : 'Lỗi khi gán.'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('📦 Gán vào SP');
                        alert('Lỗi kết nối máy chủ.');
                    }
                });
            });

            // Export JSON
            $('#pod-export-json-btn').on('click', function() {
                if (!currentTemplateConfig) return;
                var jsonStr = JSON.stringify(currentTemplateConfig, null, 2);
                navigator.clipboard.writeText(jsonStr).then(function() {
                    alert('📋 Đã sao chép toàn bộ Template JSON vào Clipboard!');
                }).catch(function() {
                    prompt('Template JSON:', jsonStr);
                });
            });

            // Zoom controls
            $('#pod-zoom-in-btn').on('click', function() {
                currentZoomScale = Math.min(1.0, currentZoomScale + 0.05);
                renderTemplateOnCanvas(currentTemplateConfig);
            });
            $('#pod-zoom-out-btn').on('click', function() {
                currentZoomScale = Math.max(0.15, currentZoomScale - 0.05);
                renderTemplateOnCanvas(currentTemplateConfig);
            });
            $('#pod-zoom-reset-btn').on('click', function() {
                currentZoomScale = 0.35;
                renderTemplateOnCanvas(currentTemplateConfig);
            });

            // Auto-load template if specified in query param
            var queryTpl = '<?php echo esc_js($initial_template_id); ?>';
            if (queryTpl && '<?php echo esc_js($active_tab); ?>' === 'studio') {
                loadTemplateIntoStudio(queryTpl);
            }
        });
        </script>
        <?php
    }
}
