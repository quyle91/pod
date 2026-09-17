<?php

namespace PodCustomizer\Frontend;

use PodCustomizer\Contracts\HandlerInterface;

/**
 * Class CustomizerRenderer
 * Renders the interactive personalization UI container on WooCommerce single product pages.
 * Follows Single Responsibility Principle (SRP).
 */
class CustomizerRenderer implements HandlerInterface {

    /**
     * Prevent duplicate rendering on pages firing multiple cart hooks.
     *
     * @var bool
     */
    private bool $rendered = false;

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_customizer_ui'], 15);
        add_action('woocommerce_before_variations_form', [$this, 'render_customizer_ui'], 10);
    }

    /**
     * Render the Live Preview Canvas and Personalization Controls into the Add to Cart form.
     *
     * @return void
     */
    public function render_customizer_ui(): void {
        if ($this->rendered) {
            return;
        }

        global $product;
        $product = $product ?: wc_get_product();
        if (!$product || !apply_filters('pod_is_product_customizable', true, $product)) {
            return;
        }

        $active_template = \PodCustomizer\Services\TemplateLoader::get_active_template($product->get_id());
        $is_template_mode = !empty($active_template);
        ?>
        <div id="pod-customizer-app" class="pod-customizer-app <?php echo $is_template_mode ? 'pod-is-template-mode' : ''; ?>">
            <?php 
            $is_admin = current_user_can('administrator') || current_user_can('manage_options');
            if ($is_admin): 
                $all_templates = \PodCustomizer\Services\TemplateLoader::get_all_templates();
            ?>
                <!-- Admin Template Switcher Bar (Visible only to Administrators) -->
                <div class="pod-admin-template-bar">
                    <div class="pod-admin-bar-top">
                        <div class="pod-admin-bar-title">
                            <span class="pod-admin-badge">👑 Admin Switcher</span>
                            <span class="pod-admin-desc"><?php esc_html_e('Chuyển đổi nhanh mẫu Template POD (Chỉ hiển thị với Administrator):', 'pod-customizer'); ?></span>
                        </div>
                        <a href="http://pod-backend.localhost/test-render.html?secret=pod_secret_token_123456" target="_blank" class="pod-admin-qa-link" title="<?php esc_attr_e('Mở công cụ Sharp Render Studio 300 DPI', 'pod-customizer'); ?>">
                            🛠️ <?php esc_html_e('Sharp 300 DPI Studio', 'pod-customizer'); ?> ↗
                        </a>
                    </div>
                    <div class="pod-admin-bar-pills">
                        <?php foreach ($all_templates as $tpl_id => $tpl_item): 
                            $is_current = ($active_template && ($active_template['id'] ?? '') === $tpl_id);
                            $tpl_url = add_query_arg('pod_tpl', $tpl_id);
                            $tpl_icon = '📄';
                            if ($tpl_id === 'tpl_01') $tpl_icon = '👕';
                            if ($tpl_id === 'tpl_02') $tpl_icon = '☕';
                            if ($tpl_id === 'tpl_03') $tpl_icon = '🎂';
                        ?>
                            <a href="<?php echo esc_url($tpl_url); ?>" class="pod-admin-pill <?php echo $is_current ? 'is-active' : ''; ?>">
                                <span class="pod-pill-icon"><?php echo $tpl_icon; ?></span>
                                <span class="pod-pill-name"><strong><?php echo esc_html($tpl_id); ?></strong>: <?php echo esc_html($tpl_item['name']); ?></span>
                                <?php if ($is_current): ?>
                                    <span class="pod-pill-active-tag">Active</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <a href="<?php echo esc_url(remove_query_arg('pod_tpl')); ?>" class="pod-admin-pill <?php echo !$is_template_mode ? 'is-active' : ''; ?>">
                            <span class="pod-pill-icon">🎨</span>
                            <span class="pod-pill-name"><strong>Legacy</strong>: Free-form Canvas</span>
                            <?php if (!$is_template_mode): ?>
                                <span class="pod-pill-active-tag">Active</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            <div class="pod-header">
                <div class="pod-header-title">
                    <span class="pod-icon">🎨</span>
                    <h4><?php echo $is_template_mode ? esc_html($active_template['product_title'] ?? __('Personalize Product', 'pod-customizer')) : esc_html__('Customize Your Design', 'pod-customizer'); ?></h4>
                </div>
                <span class="pod-live-badge"><?php esc_html_e('Live 2D Preview', 'pod-customizer'); ?></span>
            </div>

            <div class="pod-workspace">
                <!-- Left Visual: Live Canvas Stage -->
                <div class="pod-stage-container">
                    <div class="pod-canvas-wrapper" id="pod-canvas-wrapper">
                        <canvas id="pod-live-canvas" width="600" height="600"></canvas>
                        <div class="pod-canvas-loading" id="pod-canvas-loading">
                            <span class="pod-spinner"></span>
                            <span><?php esc_html_e('Loading studio...', 'pod-customizer'); ?></span>
                        </div>
                    </div>
                    <div class="pod-stage-actions">
                        <span class="pod-canvas-hint">
                            <?php echo $is_template_mode ? esc_html__('Tip: Fill in the options on the right to customize your live preview', 'pod-customizer') : esc_html__('Tip: Click on elements on canvas to drag or resize', 'pod-customizer'); ?>
                        </span>
                    </div>
                </div>

                <!-- Right Visual: Tool Controls -->
                <div class="pod-controls-container">
                    <?php if ($is_template_mode): ?>
                        <!-- Template-Driven Personalization Form -->
                        <div class="pod-template-form-container" id="pod-template-form-container">
                            <div class="pod-template-form-header">
                                <h5 style="margin: 0 0 4px; font-size: 15px; font-weight: 700; color: #1e293b;">
                                    <?php echo esc_html($active_template['name']); ?>
                                </h5>
                                <p style="margin: 0 0 16px; font-size: 12px; color: #64748b;">
                                    <?php esc_html_e('Customize the options below to personalize your item in real time.', 'pod-customizer'); ?>
                                </p>
                            </div>
                            <div class="pod-template-form-fields" id="pod-template-form-fields">
                                <!-- Populated dynamically by form.js from active template fields -->
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Nav Tabs for Legacy Free-Form Canvas -->
                        <div class="pod-tabs-nav" role="tablist">
                            <button type="button" class="pod-tab-btn active" data-tab="tab-base">
                                👕 <span><?php esc_html_e('Base', 'pod-customizer'); ?></span>
                            </button>
                            <button type="button" class="pod-tab-btn" data-tab="tab-text">
                                ✍️ <span><?php esc_html_e('Text', 'pod-customizer'); ?></span>
                            </button>
                            <button type="button" class="pod-tab-btn" data-tab="tab-clipart">
                                🎨 <span><?php esc_html_e('Clipart', 'pod-customizer'); ?></span>
                            </button>
                            <button type="button" class="pod-tab-btn" data-tab="tab-upload">
                                📷 <span><?php esc_html_e('Photo', 'pod-customizer'); ?></span>
                            </button>
                        </div>

                        <!-- Tab Contents -->
                        <div class="pod-tabs-content">
                            <!-- 1. Base Product Mockup Tab -->
                            <div class="pod-tab-pane active" id="tab-base">
                                <label class="pod-control-label"><?php esc_html_e('Select Product Variant & Color', 'pod-customizer'); ?></label>
                                <div class="pod-mockups-grid" id="pod-mockups-grid">
                                    <!-- Populated dynamically by JS from config -->
                                </div>
                            </div>

                            <!-- 2. Text Tab -->
                            <div class="pod-tab-pane" id="tab-text">
                                <div class="pod-pane-header">
                                    <label class="pod-control-label" style="margin-bottom: 0;"><?php esc_html_e('Custom Text Lines', 'pod-customizer'); ?></label>
                                    <button type="button" class="pod-btn-add" id="pod-btn-add-text">
                                        <svg class="pod-icon-plus" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 1v10M1 6h10"/></svg>
                                        <span><?php esc_html_e('Add Text', 'pod-customizer'); ?></span>
                                    </button>
                                </div>
                                <div class="pod-layer-list" id="pod-text-list">
                                    <!-- Populated dynamically by JS -->
                                </div>
                            </div>

                            <!-- 3. Clipart Tab -->
                            <div class="pod-tab-pane" id="tab-clipart">
                                <div class="pod-pane-header">
                                    <label class="pod-control-label" style="margin-bottom: 0;"><?php esc_html_e('Clipart Layers', 'pod-customizer'); ?></label>
                                    <button type="button" class="pod-btn-add" id="pod-btn-add-clipart">
                                        <svg class="pod-icon-plus" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 1v10M1 6h10"/></svg>
                                        <span><?php esc_html_e('Add Clipart', 'pod-customizer'); ?></span>
                                    </button>
                                </div>
                                <div class="pod-layer-list" id="pod-clipart-list">
                                    <!-- Populated dynamically by JS -->
                                </div>
                            </div>

                            <!-- 4. Photo Upload Tab -->
                            <div class="pod-tab-pane" id="tab-upload">
                                <div class="pod-pane-header">
                                    <label class="pod-control-label" style="margin-bottom: 0;"><?php esc_html_e('Photo Layers', 'pod-customizer'); ?></label>
                                    <button type="button" class="pod-btn-add" id="pod-btn-add-photo">
                                        <svg class="pod-icon-plus" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 1v10M1 6h10"/></svg>
                                        <span><?php esc_html_e('Add Photo', 'pod-customizer'); ?></span>
                                    </button>
                                </div>
                                <div class="pod-layer-list" id="pod-photo-list">
                                    <!-- Populated dynamically by JS -->
                                </div>
                            </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Popup for Text Editing -->
            <div class="pod-modal-backdrop" id="pod-text-modal" style="display:none;">
                <div class="pod-modal-dialog">
                    <div class="pod-modal-header">
                        <h4 class="pod-modal-title" id="pod-modal-title"><?php esc_html_e('Edit Text', 'pod-customizer'); ?></h4>
                        <button type="button" class="pod-modal-close" id="pod-modal-btn-close">✕</button>
                    </div>
                    <div class="pod-modal-body">
                        <div class="pod-form-group">
                            <label for="pod-modal-input-text" class="pod-control-label"><?php esc_html_e('Text Content', 'pod-customizer'); ?></label>
                            <input type="text" id="pod-modal-input-text" class="pod-input" placeholder="<?php esc_attr_e('Enter your text...', 'pod-customizer'); ?>">
                        </div>

                        <div class="pod-grid-2col">
                            <div class="pod-form-group">
                                <label for="pod-modal-select-font" class="pod-control-label"><?php esc_html_e('Font Style', 'pod-customizer'); ?></label>
                                <select id="pod-modal-select-font" class="pod-select">
                                    <option value="Roboto">Roboto</option>
                                    <option value="Montserrat">Montserrat</option>
                                    <option value="Pacifico">Pacifico</option>
                                    <option value="Oswald">Oswald</option>
                                    <option value="Dancing Script">Dancing Script</option>
                                </select>
                            </div>
                            <div class="pod-form-group">
                                <label class="pod-control-label"><?php esc_html_e('Text Size', 'pod-customizer'); ?> <span id="pod-modal-size-val">44px</span></label>
                                <input type="range" id="pod-modal-range-size" min="18" max="100" value="44" class="pod-range">
                            </div>
                        </div>

                        <div class="pod-form-group">
                            <label class="pod-control-label"><?php esc_html_e('Text Color', 'pod-customizer'); ?></label>
                            <div class="pod-color-presets" id="pod-modal-text-colors">
                                <button type="button" class="pod-color-btn" data-color="#111827" style="background:#111827;"></button>
                                <button type="button" class="pod-color-btn" data-color="#ef4444" style="background:#ef4444;"></button>
                                <button type="button" class="pod-color-btn" data-color="#3b82f6" style="background:#3b82f6;"></button>
                                <button type="button" class="pod-color-btn" data-color="#10b981" style="background:#10b981;"></button>
                                <button type="button" class="pod-color-btn" data-color="#f59e0b" style="background:#f59e0b;"></button>
                                <button type="button" class="pod-color-btn" data-color="#8b5cf6" style="background:#8b5cf6;"></button>
                                <button type="button" class="pod-color-btn" data-color="#ffffff" style="background:#ffffff; border:1px solid #d1d5db;"></button>
                                <input type="color" id="pod-modal-custom-color" value="#111827" class="pod-color-picker" title="<?php esc_attr_e('Custom Color Picker', 'pod-customizer'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="pod-modal-footer">
                        <button type="button" class="pod-btn-modal-done" id="pod-modal-btn-done"><?php esc_html_e('Done', 'pod-customizer'); ?></button>
                    </div>
                </div>
            </div>

            <!-- Modal Popup for Clipart Selection -->
            <div class="pod-modal-backdrop" id="pod-clipart-modal" style="display:none;">
                <div class="pod-modal-dialog pod-clipart-modal-dialog">
                    <div class="pod-modal-header">
                        <h4 class="pod-modal-title" id="pod-clipart-modal-title"><?php esc_html_e('Choose Clipart Graphic', 'pod-customizer'); ?></h4>
                        <button type="button" class="pod-modal-close" id="pod-clipart-modal-btn-close">✕</button>
                    </div>
                    <div class="pod-modal-body">
                        <div class="pod-clipart-grid" id="pod-clipart-grid">
                            <!-- Populated dynamically by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Popup for Photo Upload -->
            <div class="pod-modal-backdrop" id="pod-photo-modal" style="display:none;">
                <div class="pod-modal-dialog">
                    <div class="pod-modal-header">
                        <h4 class="pod-modal-title" id="pod-photo-modal-title"><?php esc_html_e('Upload Personal Photo', 'pod-customizer'); ?></h4>
                        <button type="button" class="pod-modal-close" id="pod-photo-modal-btn-close">✕</button>
                    </div>
                    <div class="pod-modal-body">
                        <div class="pod-upload-dropzone" id="pod-upload-dropzone">
                            <span class="pod-upload-icon">📂</span>
                            <p><?php esc_html_e('Click or drag & drop photo here', 'pod-customizer'); ?></p>
                            <span class="pod-upload-meta"><?php esc_html_e('Supports PNG, JPG, WebP (Max 10MB)', 'pod-customizer'); ?></span>
                            <input type="file" id="pod-file-input" accept="image/png, image/jpeg, image/webp" class="pod-file-hidden" multiple>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mandatory Hidden Inputs for WooCommerce Cart submission -->
            <input type="hidden" name="pod_canvas_state" id="pod_canvas_state" value="">
            <input type="hidden" name="pod_preview_image" id="pod_preview_image" value="">
        </div>
        <?php
    }
}
