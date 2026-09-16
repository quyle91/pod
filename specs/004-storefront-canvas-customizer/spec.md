# Feature Specification: Storefront Personalization & Canvas Live Preview

**Feature Key**: `004-storefront-canvas-customizer`  
**Status**: `IMPLEMENTED`  
**Created At**: 2026-09-16  

---

## 1. Authoritative Requirements (Sources of Truth)

This specification defines the storefront personalization interface and client-side Canvas Live Preview on WooCommerce single product pages, adhering strictly to:

| Domain | Primary Requirement Document | Key Scope |
| :--- | :--- | :--- |
| **Project Constitution** | [`.specify/constitution.md`](../../.specify/constitution.md) | Client-side 72 DPI preview boundary, Strict Data Contracts, No dummy fallbacks |
| **Project Blueprint** | [`.specify/project.md`](../../.specify/project.md) | Phase 4 milestone: Live Canvas preview, JSON state generator, Add-to-cart integration |
| **Workflow Specifications** | [`.specify/workflows.md`](../../.specify/workflows.md) | Luồng 1 (Front-end User) & Giai đoạn 1 (Storefront Customization & Add to Cart) |
| **Execution Rules** | [`.specify/execution_rules.md`](../../.specify/execution_rules.md) | Single Responsibility, WordPress enqueue standards, strict validation at source |

---

## 2. Implementation Plan & Task Checklist

- **Technical Architecture & Data Contract Plan**: [`./plan.md`](./plan.md)
- **Actionable Task Breakdown**: [`./tasks.md`](./tasks.md)

---

## 3. Scope of Modules & Features

1. **Frontend Assets & Script Enqueueing (`src/Frontend/CustomizerAssets.php`)**:
   - Conditionally enqueues interactive Canvas library (Fabric.js / Konva / native Canvas) and custom styling on customizable single product pages.
   - Localizes dynamic configuration (product template dimensions, base mockup URL, available fonts, clipart catalog).
2. **Product Page Customizer Hook & Layout (`src/Frontend/CustomizerRenderer.php`)**:
   - Hooks into `woocommerce_before_add_to_cart_button` or `woocommerce_single_product_summary`.
   - Renders the interactive customization container: Live Preview Canvas on the product visual side, and dynamic Control Panel (Text inputs, Font selector, Color picker, Clipart variant picker, Photo file uploader).
3. **Canvas Engine & State Manager (`assets/js/pod-customizer.js`)**:
   - Manages interactive layers: Base mockup/background layer, clipart layer, custom text layer, user uploaded photo layer.
   - Live updates preview in real time (72 DPI) with zoom/fit constraints.
4. **Strict JSON Data Contract Serializer**:
   - Serializes complete canvas state into structured JSON matching the backend Sharp render contract:
     - `canvas`: `{ width, height, unit: 'px' }`
     - `layers`: Array of typed layers (`image`, `text`, `clipart`) with precise coordinates (`x`, `y`, `width`, `height`, `scaleX`, `scaleY`, `rotation`, `fontFamily`, `fontSize`, `fill`).
   - Validates schema before submission; rejects empty or corrupted states (No dummy fallbacks).
5. **WooCommerce Form Binding**:
   - Injects hidden input field `pod_canvas_state` into the standard WooCommerce `<form class="cart">`.
   - Prevents Add to Cart if required personalization fields are invalid.

---

## 4. AI Verification Command Contract

```bash
# 1. Verify PHP syntax of new frontend classes
docker run --rm -v /home/quyle91/projects/pod/pod.localhost/source/wp-content/plugins/pod-customizer:/code php:8.2-fpm find /code -name "*.php" -exec php -l {} \;

# 2. Verify JavaScript syntax of storefront customizer scripts
docker run --rm -v /home/quyle91/projects/pod/pod.localhost/source/wp-content/plugins/pod-customizer/assets:/assets node:20-alpine sh -c "find /assets -name '*.js' -exec node --check {} \;"
```
