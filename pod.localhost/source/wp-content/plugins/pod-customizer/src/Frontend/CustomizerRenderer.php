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

        $this->rendered = true;

        ?>
        <div id="pod-customizer-app" class="pod-customizer-app">
            <div class="pod-header">
                <div class="pod-header-title">
                    <span class="pod-icon">🎨</span>
                    <h4><?php esc_html_e('Customize Your Design', 'pod-customizer'); ?></h4>
                </div>
                <span class="pod-live-badge"><?php esc_html_e('Live 3D/2D Preview', 'pod-customizer'); ?></span>
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
                        <button type="button" class="pod-btn-tool" id="pod-btn-reset" title="<?php esc_attr_e('Reset to Default', 'pod-customizer'); ?>">
                            🔄 <?php esc_html_e('Reset', 'pod-customizer'); ?>
                        </button>
                        <span class="pod-canvas-hint"><?php esc_html_e('Tip: Click on elements on canvas to drag or resize', 'pod-customizer'); ?></span>
                    </div>
                </div>

                <!-- Right Visual: Tool Controls -->
                <div class="pod-controls-container">
                    <!-- Nav Tabs -->
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
                            <div class="pod-form-group">
                                <label for="pod-input-text" class="pod-control-label"><?php esc_html_e('Custom Text Message', 'pod-customizer'); ?></label>
                                <input type="text" id="pod-input-text" class="pod-input" placeholder="<?php esc_attr_e('Enter your personalized text...', 'pod-customizer'); ?>" value="Best Dad Ever">
                            </div>

                            <div class="pod-grid-2col">
                                <div class="pod-form-group">
                                    <label for="pod-select-font" class="pod-control-label"><?php esc_html_e('Font Style', 'pod-customizer'); ?></label>
                                    <select id="pod-select-font" class="pod-select">
                                        <option value="Roboto">Roboto</option>
                                        <option value="Montserrat">Montserrat</option>
                                        <option value="Pacifico">Pacifico</option>
                                        <option value="Oswald">Oswald</option>
                                        <option value="Dancing Script">Dancing Script</option>
                                    </select>
                                </div>
                                <div class="pod-form-group">
                                    <label class="pod-control-label"><?php esc_html_e('Text Size', 'pod-customizer'); ?> <span id="pod-size-val">44px</span></label>
                                    <input type="range" id="pod-range-size" min="20" max="90" value="44" class="pod-range">
                                </div>
                            </div>

                            <div class="pod-form-group">
                                <label class="pod-control-label"><?php esc_html_e('Text Color', 'pod-customizer'); ?></label>
                                <div class="pod-color-presets" id="pod-text-colors">
                                    <button type="button" class="pod-color-btn active" data-color="#111827" style="background:#111827;"></button>
                                    <button type="button" class="pod-color-btn" data-color="#ef4444" style="background:#ef4444;"></button>
                                    <button type="button" class="pod-color-btn" data-color="#3b82f6" style="background:#3b82f6;"></button>
                                    <button type="button" class="pod-color-btn" data-color="#10b981" style="background:#10b981;"></button>
                                    <button type="button" class="pod-color-btn" data-color="#f59e0b" style="background:#f59e0b;"></button>
                                    <button type="button" class="pod-color-btn" data-color="#8b5cf6" style="background:#8b5cf6;"></button>
                                    <button type="button" class="pod-color-btn" data-color="#ffffff" style="background:#ffffff; border:1px solid #d1d5db;"></button>
                                    <input type="color" id="pod-custom-color" value="#111827" class="pod-color-picker" title="<?php esc_attr_e('Custom Color Picker', 'pod-customizer'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 3. Clipart Tab -->
                        <div class="pod-tab-pane" id="tab-clipart">
                            <label class="pod-control-label"><?php esc_html_e('Choose Clipart Graphic', 'pod-customizer'); ?></label>
                            <div class="pod-clipart-grid" id="pod-clipart-grid">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>

                        <!-- 4. Photo Upload Tab -->
                        <div class="pod-tab-pane" id="tab-upload">
                            <label class="pod-control-label"><?php esc_html_e('Upload Personal Photo', 'pod-customizer'); ?></label>
                            <div class="pod-upload-dropzone" id="pod-upload-dropzone">
                                <span class="pod-upload-icon">📂</span>
                                <p><?php esc_html_e('Click or drag & drop photo here', 'pod-customizer'); ?></p>
                                <span class="pod-upload-meta"><?php esc_html_e('Supports PNG, JPG, WebP (Max 10MB)', 'pod-customizer'); ?></span>
                                <input type="file" id="pod-file-input" accept="image/png, image/jpeg, image/webp" class="pod-file-hidden">
                            </div>
                            <div class="pod-upload-preview" id="pod-upload-preview" style="display:none;">
                                <span class="pod-upload-name" id="pod-upload-name"></span>
                                <button type="button" class="pod-btn-remove" id="pod-btn-remove-photo">✕ <?php esc_html_e('Remove', 'pod-customizer'); ?></button>
                            </div>
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
