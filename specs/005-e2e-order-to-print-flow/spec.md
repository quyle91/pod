# Feature Specification: Automated End-to-End Order-to-Print Flow

**Feature Key**: `005-e2e-order-to-print-flow`  
**Status**: `READY_FOR_REVIEW`  
**Created At**: 2026-09-16  

---

## 1. Authoritative Requirements (Sources of Truth)

This specification defines the complete end-to-end integration and automated fulfillment pipeline between WooCommerce (`pod.localhost`) and the Node.js Graphics Worker (`pod-backend.localhost`), strictly implementing:

| Domain | Primary Requirement Document | Key Scope |
| :--- | :--- | :--- |
| **Project Constitution** | [`.specify/constitution.md`](../../.specify/constitution.md) | Offloaded 300 DPI rendering, token authentication (`X-POD-SECRET`), No Dummy Fallbacks |
| **Project Blueprint** | [`.specify/project.md`](../../.specify/project.md) | Phase 5 milestone: Automated E2E Order-to-Print flow |
| **Workflow Specifications** | [`.specify/workflows.md`](../../.specify/workflows.md) | Giai đoạn 2 (Render File In Offloaded 300 DPI) & Giai đoạn 3 (Admin Phê Duyệt & Xuất Xưởng) |
| **Execution Rules** | [`.specify/execution_rules.md`](../../.specify/execution_rules.md) | Sharp high-resolution layer compositing, callback order meta updates |

---

## 2. Implementation Plan & Task Checklist

- **Technical Architecture & Pipeline Plan**: [`./plan.md`](./plan.md)
- **Actionable Task Breakdown**: [`./tasks.md`](./tasks.md)

---

## 3. Scope of Pipeline & Verification

1. **Plugin Activation & Runtime Settings**:
   - Activate `pod-customizer` on WordPress (`pod.localhost`).
   - Configure Backend Worker URL (`http://pod_backend:3001` for direct internal docker communication, or `http://pod-backend.localhost` via Traefik).
   - Configure shared secret key `pod_dev_secret_key_2026` across both WordPress options and Node.js environment.
2. **Order Webhook Alignment & Triggering**:
   - When an order containing personalized line items reaches `processing` status (e.g. checkout completion or manual admin status change), `OrderWebhookDispatcher` sends the exact serialized Canvas JSON state to `POST /api/v1/render`.
3. **Sharp 300 DPI Layer Compositing Engine**:
   - Node.js worker parses layers, fetches image assets/cliparts, and renders text layers using SVG/Pango with exact font styling.
   - Generates production-ready print file at 300 DPI (e.g. `order_{id}_item_{item_id}_300dpi.png`).
   - Stores file in `/prints` static directory.
4. **Asynchronous REST Callback**:
   - Node.js invokes WordPress REST endpoint `/wp-json/pod-customizer/v1/render-callback` with payload:
     - `order_id`: WooCommerce order ID.
     - `item_id`: WooCommerce line item ID.
     - `print_url`: Publicly downloadable URL of the 300 DPI file.
     - `status`: `completed`.
   - WordPress updates order item meta `_pod_print_ready_url` and records an Order Note with audit timestamp.
5. **Admin Dashboard Fulfillment View**:
   - Admin accesses WooCommerce Edit Order page.
   - Line item displays status badge **"Print Ready (300 DPI)"** and active button **"Download 300 DPI Print File"**.
   - Admin downloads and inspects the high-resolution composite file.

---

## 4. AI Verification Command Contract

```bash
# 1. Verify healthcheck of backend render worker
docker exec pod_app curl -s http://pod_backend:3001/health

# 2. Verify WordPress callback endpoint connectivity
docker exec pod_backend curl -s http://pod_web/wp-json/pod-customizer/v1/render-callback

# 3. Verify end-to-end render output directory contains 300 DPI files
docker exec pod_backend ls -la /app/prints
```
