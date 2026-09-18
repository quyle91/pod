# Feature Specification: Custom Database Storage & Queue Pipeline

**Feature Key**: `006-custom-database-tables`  
**Status**: `READY_FOR_REVIEW`  
**Created At**: 2026-09-16  

---

## 1. Authoritative Requirements (Sources of Truth)

This specification defines the dedicated relational database layer and migration system for POD Customizer, strictly implementing:

| Domain | Primary Requirement Document | Key Scope |
| :--- | :--- | :--- |
| **Entities & Database** | [`.specify/database.md`](../../.specify/database.md) | Canonical Schema for `{$wpdb->prefix}pod_render_jobs` and `{$wpdb->prefix}pod_preview_files` |
| **Execution Rules** | [`.specify/execution_rules.md`](../../.specify/execution_rules.md) | Section 2.2: Mandatory Table Prefix (`{$wpdb->prefix}pod_*`) & Explicit Foreign Key Constraints |
| **Project Constitution** | [`.specify/constitution.md`](../../.specify/constitution.md) | Strict Data Contracts, zero data loss, safe database upgrades using `dbDelta` |

---

## 2. Implementation Plan & Task Checklist

- **Technical Architecture & Migration Plan**: [`./plan.md`](./plan.md)
- **Actionable Task Breakdown**: [`./tasks.md`](./tasks.md)

---

## 3. Scope of Database Tables & Modules

1. **Table 1: `{$wpdb->prefix}pod_render_jobs` (Dedicated Render Task Queue)**:
   - Offloads render task payloads, status, attempts, and high-res print URLs from `woocommerce_order_itemmeta`.
   - **Primary Key**: `id` (`BIGINT UNSIGNED AUTO_INCREMENT`).
   - **Foreign Key**: `order_item_id` referencing `{$wpdb->prefix}woocommerce_order_items(order_item_id)` with `ON DELETE CASCADE`.
   - **Indexes**: `order_id`, `status`, `created_at`.
2. **Table 2: `{$wpdb->prefix}pod_preview_files` (Physical File Tracking & Retention Index)**:
   - Tracks every client-generated canvas preview snapshot (`.jpg`), linking it to cart/order sessions and managing automatic expiration.
   - **Primary Key**: `id` (`BIGINT UNSIGNED AUTO_INCREMENT`).
   - **Unique Index**: `file_hash` (`VARCHAR(64)`).
   - **Foreign Key**: `order_item_id` referencing `{$wpdb->prefix}woocommerce_order_items(order_item_id)` with `ON DELETE SET NULL`.
   - **Retention Index**: `expires_at`, `status`.
3. **Database Migration Manager (`src/Database/MigrationManager.php`)**:
   - Executes DDL migrations using WordPress `dbDelta()` on plugin activation and version upgrades (`pod_db_version`).
   - Handles foreign key creation via safe transactional SQL checks.
4. **Data Repository Layer**:
   - **`src/Database/Repositories/RenderJobRepository.php`**: CRUD operations and status state transitions for render jobs.
   - **`src/Database/Repositories/PreviewFileRepository.php`**: Indexing, expiry querying, and cleanup operations for preview files.
5. **Admin Integration**:
   - Updates Settings Page and Admin Order view to query status from repository layers with backward compatibility to `order_itemmeta`.

---

## 4. AI Verification Command Contract

```bash
# 1. Verify PHP syntax of new database classes and repositories
docker run --rm -v /home/quyle91/projects/pod/pod.localhost/source/wp-content/plugins/pod-customizer:/code php:8.2-fpm find /code -name "*.php" -exec php -l {} \;

# 2. Verify creation of custom tables with proper prefix in MySQL
docker exec pod_db mysql -u root -proot pod_wp -e "SHOW TABLES LIKE 'wp_pod%';"

# 3. Verify foreign key constraints on pod tables
docker exec pod_db mysql -u root -proot pod_wp -e "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='pod_wp' AND TABLE_NAME LIKE 'wp_pod%';"
```
