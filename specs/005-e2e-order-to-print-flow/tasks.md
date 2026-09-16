# Task Checklist: Automated End-to-End Order-to-Print Flow

**Feature Key**: `005-e2e-order-to-print-flow`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Related Plan**: [`./plan.md`](./plan.md)  
**Status**: `IMPLEMENTED`  
**Authoritative References**:
- Project Constitution: [`../../.specify/constitution.md`](../../.specify/constitution.md)
- Workflows: [`../../.specify/workflows.md`](../../.specify/workflows.md)
- Execution Rules: [`../../.specify/execution_rules.md`](../../.specify/execution_rules.md)

---

## Task Breakdown & Verification Checklist

### Phase 1: Plugin Activation & Security Alignment
- [x] **TASK-001**: Activate `pod-customizer` plugin inside WordPress using WP-CLI or database update.
- [x] **TASK-002**: Configure WordPress settings `pod_customizer_worker_url` to `http://pod_backend:3001` and `pod_customizer_secret_key` to match `SHARED_SECRET`.
- [x] **TASK-003**: Verify mutual connectivity: `pod_app` can curl `pod_backend:3001/health` and `pod_backend` can curl `pod_web`.

---

### Phase 2: Webhook Dispatcher & Callback Payload Integration
- [x] **TASK-004**: Refine `OrderWebhookDispatcher` to format request body according to the backend render contract (`order_id`, `item_id`, `layers`, `canvas`).
- [x] **TASK-005**: Ensure callback URL is passed dynamically or defaults to internal network URL `http://pod_web/wp-json/pod-customizer/v1/render-callback`.
- [x] **TASK-006**: Update `CallbackController` to record a clear WooCommerce Order Note upon successful print file generation.

---

### Phase 3: Sharp 300 DPI Real Compositing Refinement
- [x] **TASK-007**: Ensure backend Sharp renderer properly composites text layers using SVG overlay with exact font styling (fontFamily, fontSize, fill).
- [x] **TASK-008**: Ensure backend Sharp renderer fetches remote/local image layers and composites them with correct dimensions, coordinates (`x`, `y`), and rotation.
- [x] **TASK-009**: Ensure rendered output file is saved with explicit 300 DPI density (`withMetadata({ density: 300 })`).

---

### Phase 4: Automated E2E Order Test Simulation
- [x] **TASK-010**: Create an automated E2E test script (or WP-CLI order generator) that:
  1. Creates a sample WooCommerce product with personalization enabled.
  2. Adds the item to cart with a valid serialized Canvas JSON state.
  3. Transitions order status to `processing`.
  4. Waits for the async render worker to produce the print file and call back.
  5. Asserts that `_pod_print_ready_url` is populated in Order Item Meta.
- [x] **TASK-011**: Verify that the generated file in `storage/prints/` is a valid 300 DPI PNG file containing all composite layers.

---

### Phase 5: Admin Dashboard UI Verification
- [x] **TASK-012**: Verify that opening the Order in WooCommerce Admin displays the "Download 300 DPI Print File" button.
- [x] **TASK-013**: Verify that clicking the button opens/downloads the rendered print file correctly via `http://pod-backend.localhost/prints/...`.
- [x] **TASK-014**: Verify interactive "Re-render" button and Factory Email dispatch with Order Notes.

