# Task Checklist: WordPress Plugin Architecture Boilerplate (`pod-customizer`)

**Feature Key**: `002-plugin-architecture-boilerplate`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Related Plan**: [`./plan.md`](./plan.md)  
**Status**: `IMPLEMENTED`  
**Authoritative References**:
- Project Constitution: [`../../.specify/constitution.md`](../../.specify/constitution.md)
- Execution Rules: [`../../.specify/execution_rules.md`](../../.specify/execution_rules.md)

---

## Task Breakdown & Verification Checklist

### Phase 1: Core Lifecycle & Contracts
- [x] **TASK-001**: Create `pod-customizer.php` entrypoint with plugin headers, constants, and PSR-4 fallback autoloader.
- [x] **TASK-002**: Create `composer.json` defining `PodCustomizer\` PSR-4 autoload mapping to `src/`.
- [x] **TASK-003**: Create `src/Contracts/HandlerInterface.php` for standardizing hook registration.
- [x] **TASK-004**: Create `src/Contracts/DispatcherInterface.php` for abstracting external render requests.
- [x] **TASK-005**: Create `src/Core/Plugin.php` singleton orchestrator with extensibility hook `pod_customizer_loaded`.

---

### Phase 2: WooCommerce Cart & Order Handlers
- [x] **TASK-006**: Implement `src/WooCommerce/CartHandler.php` to intercept cart item additions, sanitize `pod_canvas_state`, and display custom badge in cart table.
- [x] **TASK-007**: Implement `src/WooCommerce/OrderHandler.php` to persist canvas state to `_pod_canvas_state` and render download button in Admin Order edit view.

---

### Phase 3: Webhook Dispatcher & REST API Callback
- [x] **TASK-008**: Implement `src/WooCommerce/OrderWebhookDispatcher.php` listening for `woocommerce_order_status_processing` to send render tasks to backend worker.
- [x] **TASK-009**: Implement `src/API/CallbackController.php` exposing `/wp-json/pod-customizer/v1/render-callback` secured with `X-POD-SECRET` header.

---

### Phase 4: Admin Settings UI
- [x] **TASK-010**: Implement `src/Admin/SettingsPage.php` providing WooCommerce sub-menu to configure Backend Worker URL and shared secret key.

---

### Phase 5: Automated Testing & Verification
- [x] **TASK-011**: Execute PHP syntax check on all newly created plugin files using `php:8.2-fpm` (0 errors).
- [x] **TASK-012**: Verify all classes follow PSR-4 naming and namespace standards.
