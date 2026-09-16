# Task Checklist: Custom Database Storage & Dedicated Tables

**Feature Key**: `006-custom-database-tables`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Related Plan**: [`./plan.md`](./plan.md)  
**Status**: `IMPLEMENTED`  

---

## Task Breakdown & Verification Checklist

### Phase 1: Migration Engine & DDL Schema
- [x] **TASK-001**: Create `src/Database/MigrationManager.php` implementing `HandlerInterface` with `dbDelta` logic and version tracking (`pod_db_version`).
- [x] **TASK-002**: Implement DDL execution for `{$wpdb->prefix}pod_render_jobs` with proper index structures.
- [x] **TASK-003**: Implement DDL execution for `{$wpdb->prefix}pod_preview_files` with unique hash and expiry indexes.
- [x] **TASK-004**: Implement idempotent foreign key constraint adder referencing `{$wpdb->prefix}woocommerce_order_items(order_item_id)`.

---

### Phase 2: Repository Data Access Layer
- [x] **TASK-005**: Create `src/Database/Repositories/RenderJobRepository.php` with methods: `create_or_update()`, `get_by_order_item_id()`, `update_status()`, `mark_completed()`, `mark_failed()`.
- [x] **TASK-006**: Create `src/Database/Repositories/PreviewFileRepository.php` with methods: `record_preview()`, `link_to_order_item()`, `get_expired_previews()`, `mark_deleted()`.

---

### Phase 3: Service & Handler Integration
- [x] **TASK-007**: Integrate `PreviewFileRepository` into `PreviewStorageManager`: Record newly uploaded preview files into `pod_preview_files` table upon file creation.
- [x] **TASK-008**: Update `OrderHandler`: When saving line item data at checkout, record a new entry in `pod_render_jobs` with status `pending` and link preview file ID.
- [x] **TASK-009**: Update `CallbackController`: When Node.js backend finishes rendering, update `pod_render_jobs` entry directly via `RenderJobRepository`.
- [x] **TASK-010**: Update `PreviewStorageManager::cleanup_expired_previews()` to query expired rows from `pod_preview_files` table instead of scanning entire directories.

---

### Phase 4: Automated Testing & Verification
- [x] **TASK-011**: Verify PHP syntax across all new database classes using Docker PHP runtime (`0 errors`).
- [x] **TASK-012**: Execute migration inside `pod_app` and verify table existence in MySQL (`SHOW TABLES LIKE 'wp_pod%'`).
- [x] **TASK-013**: Verify foreign key constraints and `ON DELETE CASCADE` via `information_schema`.

---

### Phase 5: End-to-End Functional Acceptance
- [x] **TASK-014**: Place a test customized order and verify a record is cleanly inserted into `wp_pod_render_jobs` with valid JSON payload.
- [x] **TASK-015**: Delete a test order line item in WooCommerce and verify that the corresponding row in `wp_pod_render_jobs` is automatically deleted via `CASCADE`.

