<?php

namespace PodCustomizer\Admin;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class ProductDesignerMetaBox
 * Provides the WooCommerce Product Edit Meta Box for:
 * 1. Direct-to-backend chunked PSD upload and automatic layer extraction.
 * 2. Visual inspection of extracted layers (Fixed, Skin groups, Hair groups, Icon slots, Text slots).
 * 3. Asset Library Category binding for [slot:icon].
 * 4. Storing the layer/JSON configuration directly in product meta (_pod_template_config).
 */
class ProductDesignerMetaBox implements HandlerInterface {

    public const META_KEY_CONFIG = '_pod_template_config';
    public const META_KEY_TPL_ID = '_pod_template_id';

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('save_post_product', [$this, 'save_product_config'], 10, 2);

        // AJAX handlers for instant saving without full page reload
        add_action('wp_ajax_pod_save_product_metabox_config', [$this, 'ajax_save_product_config']);
    }

    /**
     * Register Meta Box on WooCommerce Product screen.
     */
    public function register_meta_box(): void {
        add_meta_box(
            'pod_product_designer_metabox',
            __('🎨 Thiết Kế POD: Bóc Tách Layer Từ Photoshop (.PSD)', 'pod-customizer'),
            [$this, 'render_meta_box'],
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render Meta Box inside WooCommerce Product Edit screen.
     */
    public function render_meta_box(\WP_Post $post): void {
        wp_nonce_field('pod_product_metabox_action', 'pod_product_metabox_nonce');

        $backend_url = get_option('pod_backend_url', 'http://pod-backend.localhost');
        $shared_secret = get_option('pod_shared_secret', 'pod_secret_token_123456');

        // Read current product config
        $raw_config = get_post_meta($post->ID, self::META_KEY_CONFIG, true);
        if (is_string($raw_config)) {
            $config = json_decode($raw_config, true);
        } elseif (is_array($raw_config)) {
            $config = $raw_config;
        } else {
            $config = null;
        }

        $has_config = !empty($config) && (!empty($config['layers']) || !empty($config['fields']));
        $json_string = $has_config ? json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        // Fetch Asset Library categories for slot binding
        $icon_categories = get_terms([
            'taxonomy' => 'pod_icon_category',
            'hide_empty' => false,
        ]);
        ?>
        <div id="pod-product-metabox-root" style="padding: 10px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            
            <!-- Top Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 13px; color: #64748b;">
                        <?php esc_html_e('Bóc tách trực tiếp file PSD thành các layer đồ họa 300 DPI và cấu hình tùy biến cho sản phẩm này.', 'pod-customizer'); ?>
                    </span>
                </div>
                <div style="display: flex; gap: 8px;">
                    <?php if ($has_config): ?>
                        <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank" class="button button-secondary" style="color: #4f46e5; border-color: #4f46e5;">
                            👁️ <?php esc_html_e('Xem Storefront ↗', 'pod-customizer'); ?>
                        </a>
                        <button type="button" id="pod-toggle-reupload-btn" class="button button-secondary">
                            🔄 <?php esc_html_e('Tải File PSD Khác', 'pod-customizer'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SECTION 1: PSD UPLOAD ZONE -->
            <div id="pod-metabox-upload-section" style="<?php echo $has_config ? 'display: none;' : ''; ?> margin-bottom: 20px;">
                <div id="pod-metabox-dropzone" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 36px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s ease;">
                    <div style="font-size: 40px; margin-bottom: 8px;">📁</div>
                    <h4 style="margin: 0 0 6px 0; font-size: 16px; color: #0f172a; font-weight: 700;">
                        <?php esc_html_e('Kéo thả file .PSD vào đây hoặc bấm để chọn file', 'pod-customizer'); ?>
                    </h4>
                    <p style="margin: 0 0 14px 0; font-size: 13px; color: #64748b;">
                        <?php esc_html_e('Hỗ trợ file PSD dung lượng lớn. Tự động cắt lát 5MB gửi sang Node.js bóc tách layer và sinh cấu trúc cho sản phẩm.', 'pod-customizer'); ?>
                    </p>
                    <input type="file" id="pod-metabox-file-input" accept=".psd" style="display: none;" />
                    <button type="button" class="button button-primary" style="background: #4f46e5; border-color: #4338ca; padding: 6px 20px; font-weight: 600;" onclick="document.getElementById('pod-metabox-file-input').click();">
                        <?php esc_html_e('Chọn file Photoshop (.PSD)', 'pod-customizer'); ?>
                    </button>
                </div>

                <!-- Upload Progress -->
                <div id="pod-metabox-progress-area" style="display: none; margin-top: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div>
                            <strong id="pod-metabox-filename" style="font-size: 13px; color: #0f172a;">file.psd</strong>
                            <span id="pod-metabox-filesize" style="font-size: 12px; color: #64748b; margin-left: 6px;"></span>
                        </div>
                        <span id="pod-metabox-percent" style="font-size: 13px; font-weight: 700; color: #4f46e5;">0%</span>
                    </div>
                    <div style="background: #e2e8f0; height: 8px; border-radius: 9999px; overflow: hidden; margin-bottom: 8px;">
                        <div id="pod-metabox-progress-fill" style="background: linear-gradient(90deg, #4f46e5, #06b6d4); width: 0%; height: 100%; transition: width 0.2s ease;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b;">
                        <span id="pod-metabox-chunk-status"><?php esc_html_e('Đang gửi dữ liệu...', 'pod-customizer'); ?></span>
                        <button type="button" id="pod-metabox-cancel-btn" class="button button-link-delete" style="font-size: 11px;"><?php esc_html_e('Hủy', 'pod-customizer'); ?></button>
                    </div>
                </div>

                <!-- Backend Parsing Worker Status -->
                <div id="pod-metabox-parsing-area" style="display: none; margin-top: 16px; padding: 14px 18px; border-radius: 10px; background: #f0fdf4; border: 1px solid #bbf7d0;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="spinner is-active" style="float: none; margin: 0;"></div>
                        <div>
                            <strong id="pod-metabox-job-title" style="font-size: 13px; color: #166534;">
                                <?php esc_html_e('Đang bóc tách layer ngầm 300 DPI...', 'pod-customizer'); ?>
                            </strong>
                            <p id="pod-metabox-job-msg" style="margin: 2px 0 0 0; font-size: 12px; color: #15803d;">
                                <?php esc_html_e('Giải mã các Group màu da, kiểu tóc và trích xuất ảnh PNG...', 'pod-customizer'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: CONFIGURED LAYERS & JSON CONTAINER -->
            <div id="pod-metabox-config-section" style="<?php echo $has_config ? '' : 'display: none;'; ?>">
                
                <!-- Summary Card -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <div style="font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                            <span>✅ <?php esc_html_e('Đã Bóc Tách Thành Công Từ PSD:', 'pod-customizer'); ?></span>
                            <span id="pod-summary-source-name" style="color: #4f46e5;"><?php echo esc_html($config['source_file'] ?? 'template.psd'); ?></span>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 4px; display: flex; gap: 14px;">
                            <span>📐 Kích thước in: <strong id="pod-summary-dims"><?php echo esc_html(($config['print_spec']['width_px'] ?? 1600) . ' × ' . ($config['print_spec']['height_px'] ?? 2000) . ' px @ 300 DPI'); ?></strong></span>
                            <span>📑 Layer đồ họa: <strong id="pod-summary-layer-count"><?php echo count($config['layers'] ?? []); ?></strong></span>
                            <span>⚙️ Trường tùy biến: <strong id="pod-summary-field-count"><?php echo count($config['fields'] ?? []); ?></strong></span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" id="pod-metabox-quick-save-btn" class="button button-primary" style="background: #10b981; border-color: #059669; font-weight: 700;">
                            💾 <?php esc_html_e('Lưu Cấu Hình POD', 'pod-customizer'); ?>
                        </button>
                    </div>
                </div>

                <!-- Sub-tabs: Layer Tree & JSON -->
                <div style="border-bottom: 1px solid #e2e8f0; margin-bottom: 16px; display: flex; gap: 4px;">
                    <button type="button" class="pod-inner-tab-btn active" data-target="pod-inner-tab-layers" style="background: none; border: none; padding: 8px 16px; font-weight: 700; font-size: 13px; color: #4f46e5; border-bottom: 2px solid #4f46e5; cursor: pointer;">
                        📑 <?php esc_html_e('Cây Layer & Biến Thể (100% Layer-Driven)', 'pod-customizer'); ?>
                    </button>
                    <button type="button" class="pod-inner-tab-btn" data-target="pod-inner-tab-json" style="background: none; border: none; padding: 8px 16px; font-weight: 600; font-size: 13px; color: #64748b; border-bottom: 2px solid transparent; cursor: pointer;">
                        📋 <?php esc_html_e('Cấu Trúc JSON Dành Cho Front-end', 'pod-customizer'); ?>
                    </button>
                </div>

                <!-- Tab 1: Layer Tree -->
                <div id="pod-inner-tab-layers" class="pod-inner-tab-content">
                    <div id="pod-metabox-layers-container" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Rendered by JS -->
                    </div>
                </div>

                <!-- Tab 2: Raw JSON -->
                <div id="pod-inner-tab-json" class="pod-inner-tab-content" style="display: none;">
                    <p style="margin: 0 0 8px 0; font-size: 12px; color: #64748b;">
                        <?php esc_html_e('Cấu trúc JSON này được nạp trực tiếp vào Front-end Storefront khi khách vào trang sản phẩm:', 'pod-customizer'); ?>
                    </p>
                    <textarea id="pod-config-json-textarea" name="<?php echo esc_attr(self::META_KEY_CONFIG); ?>" rows="14" style="width: 100%; font-family: monospace; font-size: 12px; line-height: 1.5; background: #0f172a; color: #f8fafc; border-radius: 8px; padding: 12px;"><?php echo esc_textarea($json_string); ?></textarea>
                </div>

            </div>

        </div>

        <script>
        jQuery(document).ready(function($) {
            var backendUrl = '<?php echo esc_js(rtrim($backend_url, '/')); ?>';
            var sharedSecret = '<?php echo esc_js($shared_secret); ?>';
            var productId = <?php echo intval($post->ID); ?>;
            var currentConfig = <?php echo !empty($config) ? json_encode($config) : 'null'; ?>;
            var chunkSize = 5 * 1024 * 1024; // 5MB
            var pollTimer = null;

            var $root = $('#pod-product-metabox-root');
            var $uploadSec = $('#pod-metabox-upload-section');
            var $configSec = $('#pod-metabox-config-section');
            var $dropzone = $('#pod-metabox-dropzone');
            var $fileInput = $('#pod-metabox-file-input');
            var $progressArea = $('#pod-metabox-progress-area');
            var $progressBar = $('#pod-metabox-progress-fill');
            var $percentText = $('#pod-metabox-percent');
            var $parsingArea = $('#pod-metabox-parsing-area');
            var $jsonTextarea = $('#pod-config-json-textarea');

            // Render layer tree on page load if config exists
            if (currentConfig) {
                renderLayersVisualTree(currentConfig);
            }

            // Sub-tab switching
            $root.on('click', '.pod-inner-tab-btn', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $('.pod-inner-tab-btn').removeClass('active').css({ color: '#64748b', borderBottomColor: 'transparent' });
                $(this).addClass('active').css({ color: '#4f46e5', borderBottomColor: '#4f46e5' });
                $('.pod-inner-tab-content').hide();
                $('#' + target).show();
            });

            // Toggle re-upload button
            $('#pod-toggle-reupload-btn').on('click', function(e) {
                e.preventDefault();
                $uploadSec.slideToggle();
            });

            // Drag and drop events
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
                if (files && files.length > 0) handleFile(files[0]);
            });

            $fileInput.on('change', function() {
                if (this.files && this.files.length > 0) handleFile(this.files[0]);
            });

            function formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                var k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            function handleFile(file) {
                if (!file.name.toLowerCase().endsWith('.psd')) {
                    alert('<?php echo esc_js(__('Vui lòng chọn file Adobe Photoshop định dạng .psd', 'pod-customizer')); ?>');
                    return;
                }

                $('#pod-metabox-filename').text(file.name);
                $('#pod-metabox-filesize').text('(' + formatBytes(file.size) + ')');
                $progressArea.show();
                $parsingArea.hide();
                $progressBar.css('width', '0%');
                $percentText.text('0%');

                startChunkUpload(file);
            }

            function startChunkUpload(file) {
                var totalSize = file.size;
                var totalChunks = Math.ceil(totalSize / chunkSize);
                var uploadId = 'prod_' + productId + '_' + Date.now();
                var isCancelled = false;

                $('#pod-metabox-cancel-btn').off('click').on('click', function() {
                    isCancelled = true;
                    $('#pod-metabox-chunk-status').text('Đã hủy tải lên.');
                    $progressBar.css('background', '#ef4444');
                });

                function uploadNextChunk(index) {
                    if (isCancelled) return;

                    var start = index * chunkSize;
                    var end = Math.min(totalSize, start + chunkSize);
                    var chunkBlob = file.slice(start, end);

                    var fd = new FormData();
                    fd.append('upload_id', uploadId);
                    fd.append('chunk_index', index);
                    fd.append('total_chunks', totalChunks);
                    fd.append('total_size', totalSize);
                    fd.append('filename', file.name);
                    fd.append('file_chunk', chunkBlob);

                    var pct = Math.round((index / totalChunks) * 100);
                    $progressBar.css('width', pct + '%');
                    $percentText.text(pct + '%');
                    $('#pod-metabox-chunk-status').text('Đang tải lên mảnh ' + (index + 1) + '/' + totalChunks + '...');

                    $.ajax({
                        url: backendUrl + '/api/templates/upload-chunk',
                        type: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        headers: { 'X-POD-Secret': sharedSecret },
                        success: function(res) {
                            if (isCancelled) return;

                            if (res && res.status === 'completed') {
                                $progressBar.css('width', '100%');
                                $percentText.text('100%');
                                $('#pod-metabox-chunk-status').text('✅ Tải lên và ghép file PSD thành công!');
                                pollJobStatus(res.job_id);
                            } else if (index + 1 < totalChunks) {
                                uploadNextChunk(index + 1);
                            }
                        },
                        error: function(xhr) {
                            var msg = 'Lỗi kết nối máy chủ.';
                            try { msg = JSON.parse(xhr.responseText).error || msg; } catch(e){}
                            $progressBar.css('background', '#ef4444');
                            $('#pod-metabox-chunk-status').html('<span style="color:#ef4444;">❌ ' + msg + '</span>');
                        }
                    });
                }

                uploadNextChunk(0);
            }

            function pollJobStatus(jobId) {
                $parsingArea.show();
                $('#pod-metabox-job-title').text('Đang bóc tách layer ngầm 300 DPI...');
                $('#pod-metabox-job-msg').text('File PSD đang được xử lý trong Worker riêng biệt...');

                if (pollTimer) clearInterval(pollTimer);

                pollTimer = setInterval(function() {
                    $.ajax({
                        url: backendUrl + '/api/templates/jobs/' + encodeURIComponent(jobId) + '/status',
                        type: 'GET',
                        headers: { 'X-POD-Secret': sharedSecret },
                        success: function(res) {
                            if (res && res.data) {
                                var job = res.data;
                                $('#pod-metabox-job-msg').text(job.message || 'Đang xử lý...');

                                if (job.status === 'completed' && job.result && job.result.template_config) {
                                    clearInterval(pollTimer);
                                    $parsingArea.hide();
                                    $uploadSec.slideUp();

                                    // Apply config to product
                                    currentConfig = job.result.template_config;
                                    var formattedJson = JSON.stringify(currentConfig, null, 2);
                                    $jsonTextarea.val(formattedJson);

                                    // Update summary bar
                                    $('#pod-summary-source-name').text(currentConfig.source_file || 'template.psd');
                                    var w = currentConfig.print_spec ? currentConfig.print_spec.width_px : 1600;
                                    var h = currentConfig.print_spec ? currentConfig.print_spec.height_px : 2000;
                                    $('#pod-summary-dims').text(w + ' × ' + h + ' px @ 300 DPI');
                                    $('#pod-summary-layer-count').text((currentConfig.layers || []).length);
                                    $('#pod-summary-field-count').text((currentConfig.fields || []).length);

                                    // Render Visual Tree
                                    renderLayersVisualTree(currentConfig);
                                    $configSec.slideDown();

                                    // Auto-save to product via AJAX
                                    saveConfigToProduct(currentConfig, function() {
                                        alert('🎉 Bóc tách PSD thành công! Cấu trúc Layer và JSON đã được nạp trực tiếp vào sản phẩm này.');
                                    });

                                } else if (job.status === 'failed') {
                                    clearInterval(pollTimer);
                                    $parsingArea.css({ background: '#fee2e2', borderColor: '#fca5a5' });
                                    $('#pod-metabox-job-title').css('color', '#991b1b').text('❌ Lỗi bóc tách PSD');
                                    $('#pod-metabox-job-msg').css('color', '#7f1d1d').text(job.error || 'Có lỗi xảy ra.');
                                }
                            }
                        }
                    });
                }, 1200);
            }

            function renderLayersVisualTree(cfg) {
                var $container = $('#pod-metabox-layers-container');
                $container.empty();

                if (!cfg) return;

                // 1. Group / Discrete Variant Selectors (Skin tone, Hair style - 100% Layer Driven)
                var fields = cfg.fields || [];
                var layers = cfg.layers || [];

                fields.forEach(function(field) {
                    if (field.type === 'layer_selector') {
                        var $card = $('<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">' +
                            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
                                '<div>' +
                                    '<span style="background: #fdf2f8; color: #9d174d; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">100% LAYER-DRIVEN</span> ' +
                                    '<strong style="font-size: 14px; color: #0f172a; margin-left: 6px;">' + (field.label || field.id) + '</strong>' +
                                '</div>' +
                                '<span style="font-size: 12px; color: #64748b;">' + (field.options ? field.options.length : 0) + ' biến thể layer ảnh</span>' +
                            '</div>' +
                            '<div class="pod-swatch-list" style="display: flex; gap: 10px; flex-wrap: wrap;"></div>' +
                        '</div>');

                        var $swatchList = $card.find('.pod-swatch-list');
                        (field.options || []).forEach(function(opt) {
                            var thumb = opt.thumbnail_url || '';
                            var $swatch = $('<div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; display: flex; align-items: center; gap: 8px; background: #f8fafc;">' +
                                (thumb ? '<img src="' + thumb + '" style="width: 32px; height: 32px; object-fit: contain; border-radius: 4px; background: #ffffff;" />' : '') +
                                '<div><strong style="font-size: 12px; color: #1e293b;">' + (opt.label || opt.id) + '</strong><div style="font-size: 10px; color: #64748b;">ID: ' + opt.layer_id + '</div></div>' +
                            '</div>');
                            $swatchList.append($swatch);
                        });

                        $container.append($card);
                    } else if (field.type === 'preset_picker') {
                        // Icon Slot with Category Binding
                        var $card = $('<div style="background: #ffffff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 16px;">' +
                            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
                                '<div>' +
                                    '<span style="background: #eff6ff; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">ICON / CLIPART SLOT</span> ' +
                                    '<strong style="font-size: 14px; color: #0f172a; margin-left: 6px;">' + (field.label || field.id) + '</strong>' +
                                '</div>' +
                                '<div style="display: flex; align-items: center; gap: 8px;">' +
                                    '<label style="font-size: 12px; font-weight: 600; color: #1e40af;">Ánh xạ Kho Icon:</label>' +
                                    '<select class="pod-slot-cat-select" data-field-id="' + field.id + '" style="font-size: 12px; font-weight: 600; min-width: 180px;">' +
                                        <?php foreach ($icon_categories as $c): ?>
                                            '<option value="<?php echo esc_attr($c->slug); ?>"><?php echo esc_js($c->name . " ({$c->count})"); ?></option>' +
                                        <?php endforeach; ?>
                                    '</select>' +
                                '</div>' +
                            '</div>' +
                            '<p style="margin: 0; font-size: 12px; color: #64748b;">Vùng giữ chỗ cho icon/clipart. Khách mua hàng ngoài Storefront sẽ được chọn icon thuộc danh mục đã chọn.</p>' +
                        '</div>');

                        if (field.category_slug) {
                            $card.find('.pod-slot-cat-select').val(field.category_slug);
                        }

                        $card.find('.pod-slot-cat-select').on('change', function() {
                            var newCat = $(this).val();
                            field.category_slug = newCat;
                            $jsonTextarea.val(JSON.stringify(currentConfig, null, 2));
                        });

                        $container.append($card);
                    } else if (field.type === 'text') {
                        // Text Slot
                        var targetL = layers.find(function(l){ return l.id === field.target_layer; }) || {};
                        var $card = $('<div style="background: #ffffff; border: 1px solid #fef3c7; border-radius: 8px; padding: 14px 16px;">' +
                            '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">' +
                                '<div>' +
                                    '<span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">TEXT SLOT</span> ' +
                                    '<strong style="font-size: 14px; color: #0f172a; margin-left: 6px;">' + (field.label || field.id) + '</strong>' +
                                '</div>' +
                                '<span style="font-size: 12px; color: #92400e; font-weight: 600;">Font: ' + (targetL.font_family || 'Montserrat') + ' (' + (targetL.font_size_pt || 48) + 'pt)</span>' +
                            '</div>' +
                            '<div style="font-size: 12px; color: #64748b;">Nội dung mặc định: <em>"' + (field.default_value || targetL.default_value || '') + '"</em> | Màu: <span style="display:inline-block; width:12px; height:12px; border-radius:2px; background:' + (targetL.color || '#1e293b') + '; vertical-align:middle; border:1px solid #cbd5e1;"></span> ' + (targetL.color || '#1e293b') + '</div>' +
                        '</div>');
                        $container.append($card);
                    }
                });

                // Fixed Graphic Layers
                var fixedLayers = layers.filter(function(l) { return l.type === 'fixed_image'; });
                if (fixedLayers.length > 0) {
                    var $fixedCard = $('<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">' +
                        '<div style="margin-bottom: 8px;"><span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">ẢNH CỐ ĐỊNH (BACKGROUND)</span></div>' +
                        '<div class="pod-fixed-list" style="display: flex; gap: 10px; flex-wrap: wrap;"></div>' +
                    '</div>');

                    var $fList = $fixedCard.find('.pod-fixed-list');
                    fixedLayers.forEach(function(l) {
                        $fList.append('<div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #334155; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; background: #ffffff;">' +
                            (l.url ? '<img src="' + l.url + '" style="width: 28px; height: 28px; object-fit: contain;" />' : '') +
                            '<strong>' + l.name + '</strong> (' + l.width + ' × ' + l.height + ' px)' +
                        '</div>');
                    });
                    $container.append($fixedCard);
                }
            }

            function saveConfigToProduct(cfg, callback) {
                $.ajax({
                    url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'pod_save_product_metabox_config',
                        security: '<?php echo esc_js(wp_create_nonce('pod_metabox_save')); ?>',
                        product_id: productId,
                        config_json: JSON.stringify(cfg)
                    },
                    success: function(res) {
                        if (callback) callback(res);
                    }
                });
            }

            $('#pod-metabox-quick-save-btn').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('⏳ Đang lưu...');

                try {
                    currentConfig = JSON.parse($jsonTextarea.val());
                } catch(e) {
                    alert('Lỗi định dạng JSON: ' + e.message);
                    $btn.prop('disabled', false).text('💾 Lưu Cấu Hình POD');
                    return;
                }

                saveConfigToProduct(currentConfig, function(res) {
                    $btn.prop('disabled', false).text('💾 Lưu Cấu Hình POD');
                    if (res && res.success) {
                        alert('✅ Đã lưu cấu hình POD vào sản phẩm thành công!');
                        renderLayersVisualTree(currentConfig);
                    } else {
                        alert('❌ Lỗi khi lưu: ' + (res.data ? res.data.message : ''));
                    }
                });
            });

        });
        </script>
        <?php
    }

    /**
     * Handle WordPress standard Post Save event.
     */
    public function save_product_config(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['pod_product_metabox_nonce']) || !wp_verify_nonce($_POST['pod_product_metabox_nonce'], 'pod_product_metabox_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST[self::META_KEY_CONFIG])) {
            $raw = wp_unslash($_POST[self::META_KEY_CONFIG]);
            $decoded = json_decode($raw, true);

            if (is_array($decoded) && (!empty($decoded['layers']) || !empty($decoded['fields']))) {
                update_post_meta($post_id, self::META_KEY_CONFIG, $decoded);
                if (!empty($decoded['template_id'])) {
                    update_post_meta($post_id, self::META_KEY_TPL_ID, sanitize_text_field($decoded['template_id']));
                }
            }
        }
    }

    /**
     * AJAX handler: Quick Save configuration to product postmeta.
     */
    public function ajax_save_product_config(): void {
        check_ajax_referer('pod_metabox_save', 'security');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện.', 'pod-customizer')]);
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        $config_raw = wp_unslash($_POST['config_json'] ?? '');

        if (!$product_id || empty($config_raw)) {
            wp_send_json_error(['message' => __('Dữ liệu sản phẩm hoặc cấu hình không hợp lệ.', 'pod-customizer')]);
        }

        $config = json_decode($config_raw, true);
        if (!is_array($config)) {
            wp_send_json_error(['message' => __('JSON không hợp lệ.', 'pod-customizer')]);
        }

        update_post_meta($product_id, self::META_KEY_CONFIG, $config);
        if (!empty($config['template_id'])) {
            update_post_meta($product_id, self::META_KEY_TPL_ID, sanitize_text_field($config['template_id']));
        }

        wp_send_json_success([
            'message' => __('Cấu hình POD đã được cập nhật trực tiếp vào sản phẩm!', 'pod-customizer')
        ]);
    }
}
