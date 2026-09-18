# Task Checklist: Bug Fixes & Refinements

**Spec Key**: `007-bug-fixes-and-refinements`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Status**: `IN_PROGRESS`  
**Last Updated**: 2026-09-16  

This checklist tracks actionable fixes for all reported bugs and architectural refinements. As new bugs or tasks are raised, they will be appended to this list.

---

## 1. Resolved Tasks

### BUG-001: Mini-Cart & Cart Meta Layout Fixes
- [x] **TASK-001**: Remove raw `<img>` tag from cart item customization meta in `CartHandler.php`.
- [x] **TASK-002**: Format customization line to strictly `Customization: <a href="..." target="_blank">[preview]</a>`.
- [x] **TASK-003**: Inject inline CSS in `wp_head` (`display: inline !important; float: none !important;`) to keep meta on a single clean line in Flatsome mini-cart dropdown.
- [x] **TASK-004**: Inject footer script in `wp_footer` to auto-clear stale `sessionStorage` cart fragments (`wc_fragments_*`) on page load.
- [x] **TASK-005**: Verify layout and preview link behavior on both Cart page (`/cart`) and Mini-Cart dropdown.

---

### BUG-002: Purge Hardcoded Domain Fallbacks & Code Clutter
- [x] **TASK-006**: Remove hardcoded fallback container domain `'http://pod_backend:3001'` from `SettingsPage.php`; default `pod_backend_url` to empty string `''`.
- [x] **TASK-007**: Remove fallback URLs from `OrderWebhookDispatcher.php`; pull strictly from `get_option('pod_backend_url')`.
- [x] **TASK-008**: Centralize URL resolution in `OrderHandler::resolve_file_url(?string $url)` and remove hardcoded `http://pod-backend.localhost` occurrences.
- [x] **TASK-009**: Remove domain replacement regex `.replace('http://pod.localhost', 'http://pod_web')` from `sharpRenderer.js`.
- [x] **TASK-010**: Remove callback URL string replacement in `render.js` and use dynamic `BASE_URL` with fallback to `req.get('host')`.
- [x] **TASK-011**: Verify zero hardcoded `localhost` references in backend source code (`pod-backend.localhost/src/`).

---

### BUG-003: Graceful Handling When Backend Is Offline
- [x] **TASK-012**: Add `woocommerce_add_to_cart_validation` filter in `CartHandler.php` targeting personalized products (`_pod_canvas_state` or `pod_canvas_state`).
- [x] **TASK-013**: Implement `validate_backend_availability()` with a 2-second timeout health ping (`wp_remote_get("{$backend_url}/health")`).
- [x] **TASK-014**: Display user-friendly WooCommerce error notice and return `false` to block add-to-cart when backend is unreachable.
- [x] **TASK-015**: Keep canvas customizer fully interactive on the product page prior to clicking Add to Cart.
- [x] **TASK-016**: Verify with PHP test harness: non-customized product passes, online custom product passes, offline custom product cleanly blocked.

---

### BUG-004: Production Work Order Email to Factory
- [x] **TASK-017**: Refactor `OrderHandler::handle_ajax_send_printer_email()` to produce a structured HTML Work Order.
- [x] **TASK-018**: Include Order Header section (Order ID, timestamp, product name, quantity in bold red, SKU, variation attributes, 300 DPI print standard).
- [x] **TASK-019**: Include Production Downloads section with dual CTA buttons + raw clickable URLs for:
  - Complete Production Package (ZIP)
  - Standalone 300 DPI Print File (PNG)
- [x] **TASK-020**: Include Visual Check section with embedded Finished Mockup Preview image (`<img>`).
- [x] **TASK-021**: Include Delivery & Recipient section (recipient name, phone number, shipping address, customer note).
- [x] **TASK-022**: Add WooCommerce Order Note logging dispatch timestamp and recipient factory email upon success.
- [x] **TASK-023**: Resolve Intelephense static linting on `WC_Order_Item_Product` methods.

---

### BUG-005: Public Backend Worker URL & ZIP Download Accessibility
- [x] **TASK-024**: Update `pod_backend_url` setting in WordPress database to the public URL `http://pod-backend.localhost`.
- [x] **TASK-025**: Add `BASE_URL=http://pod-backend.localhost` to `pod-backend.localhost/.env` and restart backend container.
- [x] **TASK-026**: Add `http_api_curl` hook in `Plugin.php` to route `.localhost` domains through Docker gateway (`172.18.0.1:80` / Traefik ingress).
- [x] **TASK-027**: Upgrade `OrderHandler::resolve_file_url()` to dynamically rewrite legacy internal container URLs (`http://pod_backend:3001` or relative paths) to the configured public backend URL.
- [x] **TASK-028**: Migrate existing order metadata (#277 item 7) to public URL.
- [x] **TASK-029**: Verify direct browser download of ZIP file returning `HTTP 200 OK` (39.8 KB).

---

### BUG-006: Move Settings Menu to Settings Submenu (options-general.php)
- [x] **TASK-030**: Remove standalone top-level admin menu (`add_menu_page`) and WooCommerce submenu (`add_submenu_page('woocommerce', ...)`).
- [x] **TASK-031**: Register settings page under WordPress standard "Settings" menu using `add_options_page('options-general.php')`.
- [x] **TASK-032**: Update capability checks to `manage_options` and redirect target in `handle_manual_cleanup()` to `admin_url('options-general.php')`.
- [x] **TASK-033**: Verify menu appears at `wp-admin -> Cài đặt (Settings) -> POD Customizer`.

---

### BUG-007: Client-Side Production Storage & Stateless Render Backend Worker
- [x] **TASK-034**: Implement `PrintStorageManager` in `src/Services/PrintStorageManager.php` to stream and persist production files into `wp-content/uploads/pod-prints/{year}/{month}/order_{order_id}/`.
- [x] **TASK-035**: Update `CallbackController::handle_callback()` to download print PNG and factory ZIP locally and save local client URLs into WooCommerce item metadata.
- [x] **TASK-036**: Return `stored_locally: true` confirmation from WordPress callback REST endpoint.
- [x] **TASK-037**: Update `render.js` on Node.js backend to delete temporary render files from `/app/storage/prints/` upon confirmed client transfer.
- [x] **TASK-038**: Update `OrderHandler::resolve_file_url()` to support local `/wp-content/` paths.
- [x] **TASK-039**: Verify end-to-end re-render on Order #277 Item #7: files successfully streamed to WordPress uploads, local URLs saved to order item meta, backend worker files deleted, and direct download returns `HTTP 200 OK` from Nginx web server.

---

### BUG-008: Presigned Secure Download Links & Work Order Data Integrity
- [x] **TASK-040**: Fix Recipient Name and Shipping Address whitespace evaluation by wrapping candidate methods in `trim()`.
- [x] **TASK-041**: Add `woocommerce_order_status_on-hold` hook in `OrderWebhookDispatcher` so COD and Bank Transfer orders auto-render print files upon order placement.
- [x] **TASK-042**: Add safety auto-check in `OrderHandler::handle_ajax_send_printer_email()`: auto-dispatches render and alerts merchant if files are not yet generated.
- [x] **TASK-043**: Implement HMAC-SHA256 Presigned Download Link generator and validator in `PrintStorageManager`.
- [x] **TASK-044**: Register `/wp-json/pod-customizer/v1/download-production` endpoint in `CallbackController` with tamper detection and 7-day expiration checks.
- [x] **TASK-045**: Log download audit trail into WooCommerce Order Notes (recording IP, timestamp, and file type).
- [x] **TASK-046**: Verify signed link download (`200 OK`), tampered signature rejection (`403 Forbidden`), and expired link rejection (`410 Gone`).

---

### BUG-009: 300 DPI Layout Alignment & Comprehensive Factory Production Specs Manifest
- [x] **TASK-047**: Export text dimensions (`width`, `height`) in `pod-customizer.js` during canvas state serialization.
- [x] **TASK-048**: Forward `recipient_name`, `product_name`, and `preview_url` from `OrderWebhookDispatcher.php` to render backend.
- [x] **TASK-049**: Implement center-origin to top-left coordinate transformation `(centerX - w/2, centerY - h/2)` for image/clipart layers in `sharpRenderer.js`, taking rotation bounding boxes into account.
- [x] **TASK-050**: Implement full-canvas SVG text rendering in `sharpRenderer.js` with `text-anchor`, `dominant-baseline="central"`, `rotate(deg, cx, cy)`, and multi-line line splitting.
- [x] **TASK-051**: Build comprehensive `04_production_specs.txt` generator with layer specifications, exact typography/color metrics, asset references, and dynamic reflection for future custom fields.
- [x] **TASK-052**: Support base64 data URLs and remote HTTP preview URLs for `02_mockup_preview.jpg` bundling in factory ZIP packages.
- [x] **TASK-053**: Re-render Order #281 Item #13, verify pixel bounds, text centering, cat positioning, and all 4 ZIP bundle contents.

---

### BUG-010: Responsive 2-Column Workspace Layout (Un-hardcode 360px)
- [x] **TASK-054**: Replace `grid-template-columns: 360px 1fr` with `repeat(2, minmax(0, 1fr))` in `pod-customizer.css`.
- [x] **TASK-055**: Remove `@media (max-width: 860px)` breakpoint to maintain persistent 2-column layout regardless of viewport width.

### BUG-011: Storefront Customizer Flat Minimalist Styling & Single-Column Stack Layout
- [x] **TASK-056**: Convert `.pod-workspace` to single-column layout (`grid-template-columns: 1fr; gap: 20px`).
- [x] **TASK-057**: Update `.pod-customizer-app` padding to 20px, remove box-shadow, and remove border-radius.
- [x] **TASK-058**: Remove border-radius and box-shadow on `.pod-canvas-wrapper`, `.pod-tabs-nav`, `.pod-tab-btn`, and `.pod-tabs-content` for a clean flat aesthetic with subtle `#e2e8f0` borders.
- [x] **TASK-059**: Enforce absolute zero border-radius (`border-radius: 0 !important;`) across all components (inputs, selects, buttons, swatches, badges, dropzones, mockup/clipart cards, cart thumbnails).

---

### BUG-012: SQLite-Backed Domain License Verification & Connection Diagnostics
- [x] **TASK-060**: Cài đặt thư viện SQLite (`sql.js` WASM/SQLite engine) và tạo `src/services/licenseManager.js` trên `pod-backend.localhost` quản lý CSDL file `storage/licenses.sqlite`.
- [x] **TASK-061**: Khởi tạo schema bảng `licenses` trong SQLite và tự động seed bản ghi mặc định cho domain `pod.localhost` với thời hạn 1 năm.
- [x] **TASK-062**: Nâng cấp endpoint `GET /health` nhận `X-POD-DOMAIN` và `X-POD-SECRET`, luôn trả về HTTP 200 kèm metadata giấy phép (`starts_at`, `expires_at`, `days_remaining`, `is_expired`).
- [x] **TASK-063**: Cập nhật middleware `auth.js` trên backend để chặn nghiêm ngặt (HTTP 403 `LICENSE_EXPIRED` / `DOMAIN_UNREGISTERED`, HTTP 401 `INVALID_SECRET`) các API nghiệp vụ (`/api/v1/render`, `/api/v1/test-render`) khi license hết hạn hoặc không hợp lệ.
- [x] **TASK-064**: Cập nhật AJAX Test Connection trong `SettingsPage.php` (`pod-customizer`): gửi kèm `X-POD-DOMAIN`, trích xuất thông tin ngày kích hoạt, ngày hết hạn và số ngày còn lại để hiển thị trong kết quả Test Connection (kèm cảnh báo nếu hết hạn).
- [x] **TASK-065**: Kiểm thử tích hợp: 
  - Test Connection khi còn hạn $\rightarrow$ Hiển thị số ngày còn lại hợp lệ (365 ngày).
  - Test Connection khi giả lập hết hạn $\rightarrow$ Vẫn kết nối thành công HTTP 200 nhưng hiển thị cảnh báo đỏ nổi bật.
  - Gọi lệnh render khi giả lập hết hạn $\rightarrow$ Backend chặn an toàn với HTTP 403 và mã lỗi `LICENSE_EXPIRED`.

---

### BUG-013: Domain License Protection & Diagnostics on Render Studio (/test-render)
- [x] **TASK-066**: Nâng cấp route `GET /test-render` trong `server.js` để xác thực domain license qua `licenseManager.verifyLicense` (chặn truy cập trực tiếp nếu secret sai hoặc license hết hạn/chưa đăng ký).
- [x] **TASK-067**: Cập nhật link khởi chạy Render Studio trong `SettingsPage.php` truyền tự động tham số `domain` của site hiện tại (`?domain=...&secret=...`).
- [x] **TASK-068**: Bổ sung hiển thị `Domain` và badge trạng thái `License Active (X days remaining)` / `License Expired` trên thanh Header của `test-render.html` thông qua `/health`.
- [x] **TASK-069**: Đảm bảo các hàm gọi render từ `test-render.html` (`runRenderByOrder`, `runRenderByJson`) truyền đầy đủ `X-POD-DOMAIN` và `domain_name`, hiển thị cảnh báo đẹp mắt khi license hết hạn.

---

## 2. Active & Upcoming Tasks (Checklist Extension)

*New bug reports and enhancement tasks will be appended here as testing continues.*

- [ ] **TASK-070**: *(Awaiting next user report or edge case)*
