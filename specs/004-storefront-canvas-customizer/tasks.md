# Task Checklist: Storefront Personalization & Canvas Live Preview

**Feature Key**: `004-storefront-canvas-customizer`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Related Plan**: [`./plan.md`](./plan.md)  
**Status**: `IMPLEMENTED`  
**Authoritative References**:
- Project Constitution: [`../../.specify/constitution.md`](../../.specify/constitution.md)
- Workflows: [`../../.specify/workflows.md`](../../.specify/workflows.md)

---

## Task Breakdown & Verification Checklist

### Phase 1: Frontend Infrastructure & Hook Registration
- [x] **TASK-001**: Implement `src/Frontend/CustomizerAssets.php` implementing `HandlerInterface` to enqueue CSS/JS and localize config on single product pages.
- [x] **TASK-002**: Implement `src/Frontend/CustomizerRenderer.php` implementing `HandlerInterface` hooked into `woocommerce_before_add_to_cart_button`.
- [x] **TASK-003**: Register both frontend handlers in `src/Core/Plugin.php`.

---

### Phase 2: Canvas Visual Assets & UI Layout
- [x] **TASK-004**: Create sample base mockups in `assets/mockups/` (e.g. t-shirt base mockup).
- [x] **TASK-005**: Create clipart variant assets in `assets/cliparts/` (e.g. avatars, icons).
- [x] **TASK-006**: Implement modern responsive UI stylesheet `assets/css/pod-customizer.css` (split view: Canvas preview + Personalization toolbar).

---

### Phase 3: Interactive Canvas Engine & Layer Management
- [x] **TASK-007**: Integrate client-side Canvas library (`fabric.min.js` or lightweight canvas helper) into `assets/js/vendor/`.
- [x] **TASK-008**: Implement `assets/js/pod-customizer.js` to manage layer lifecycle (add/update text, switch clipart, upload user photos, set color).
- [x] **TASK-009**: Implement dynamic font loading and live text styling (font family, font size, text color).

---

### Phase 4: Strict JSON Serialization & Add-to-Cart Integration
- [x] **TASK-010**: Implement JSON serializer matching the strict schema defined in `plan.md`.
- [x] **TASK-011**: Bind serialized state to hidden `<input name="pod_canvas_state">` inside WooCommerce Add-to-Cart form.
- [x] **TASK-012**: Enforce client-side validation preventing submission if personalized input is missing or corrupted.

---

### Phase 5: Verification & Syntax Checks
- [x] **TASK-013**: Execute PHP syntax check on all new classes via `php:8.2-fpm`.
- [x] **TASK-014**: Execute JavaScript syntax check via `node:20-alpine` (`node --check`).
- [x] **TASK-015**: Test manual product page rendering and Add to Cart flow on `http://pod.localhost/`.
