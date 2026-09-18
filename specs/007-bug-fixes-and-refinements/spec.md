# Feature & Maintenance Specification: Bug Fixes and Architectural Refinements

**Spec Key**: `007-bug-fixes-and-refinements`  
**Status**: `ACTIVE_MAINTENANCE`  
**Created At**: 2026-09-16  
**Last Updated**: 2026-09-16  

---

## 1. Executive Summary & Purpose

This specification serves as the centralized living document tracking bug reports, architectural cleanups, UX/UI fixes, and robustness improvements raised during testing and integration of the POD Customizer ecosystem (`pod-customizer` WordPress plugin and `pod-backend.localhost` Sharp render engine).

As additional bugs or edge cases are identified by the user, they will be registered, tracked, and verified through this specification and its accompanying checklist [`tasks.md`](./tasks.md).

---

## 2. Authoritative Architecture & Engineering Rules

All fixes in this specification must strictly comply with the following architectural invariants:

1. **Client Portability (No Hardcoded Internal Fallbacks)**:
   - The WordPress plugin (`pod-customizer`) is designed as a standalone, portable client that can be deployed across multiple independent WordPress storefronts.
   - It must **never** hardcode Docker container names (e.g. `pod_backend`, `pod_web`), local IP addresses, or `.localhost` domains.
   - All external endpoints must be configured via `wp-admin` Settings (`pod_backend_url`) or derived dynamically via WordPress standard APIs (`rest_url()`, `home_url()`).

2. **Clean Backend Microservice (No Domain Replacement Hacks)**:
   - The Node.js rendering worker (`pod-backend`) must operate as a domain-agnostic graphics processing service.
   - It must **never** perform domain string mutations (e.g. `.replace('http://pod.localhost', 'http://pod_web')`).
   - Layer assets are fetched directly from the URLs provided in the payload; callbacks are dispatched directly to `payload.callback_url`; and public file URLs are built using `process.env.BASE_URL` or request host.

3. **Public URL Access for Production Downloads**:
   - Download links for high-resolution 300 DPI PNGs, Mockup Previews, and Production ZIP packages must always be valid, publicly resolvable URLs accessible from outside the container network (e.g. user browser, print factory staff).

4. **Fail-Fast Storefront Validation**:
   - Canvas customization remains interactive on the client side at all times.
   - If the render backend service is offline, down, or unreachable, adding items to the cart must be intercepted immediately at the validation phase (`woocommerce_add_to_cart_validation`) with a user-friendly error notice, preventing dead or unrenderable orders.

---

## 3. Bug Registry & Detailed Requirements

### BUG-001: Mini-Cart & Cart Meta Layout Glitch
- **Reported Issue**: In Flatsome and standard WooCommerce mini-cart dropdowns, personalized cart item meta included raw `<img>` elements that broke layout alignment, caused image overflow, or held stale cached preview thumbnails.
- **Requirement**:
  - Replace `<img>` tag in cart/mini-cart meta with a clean, single-line text hyperlink: `Customization: [preview]` targeting the preview image in a new tab.
  - Apply scoped CSS (`display: inline !important; float: none !important;`) to ensure single-line rendering.
  - Invalidate stale `sessionStorage` cart fragments (`wc_fragments_*`) on page load to eliminate stale cached thumbnails.

### BUG-002: Hardcoded Fallbacks & Domain Hacks
- **Reported Issue**: Plugin source and backend render services contained multiple fallback strings (`http://pod-backend.localhost`, `http://pod_web`), violating client portability.
- **Requirement**:
  - Purge all hardcoded fallback domains from `SettingsPage.php`, `OrderHandler.php`, `OrderWebhookDispatcher.php`, `sharpRenderer.js`, and `render.js`.
  - Centralize file URL resolution in `OrderHandler::resolve_file_url()`.
  - Configure `pod_backend_url` as a standard, configurable WordPress option (defaulting to empty string `''`).

### BUG-003: Graceful Handling When Backend Is Offline
- **Reported Issue**: When backend Docker container is stopped or offline, what happens during customer interactions?
- **Requirement**:
  - Allow users to freely customize and design on the canvas without premature error popups.
  - Intercept the action when clicking **Add to Cart** via `woocommerce_add_to_cart_validation`.
  - Ping `{$backend_url}/health` with a fast 2-second timeout. If unreachable or down, reject the request (`return false`) and show WooCommerce notice: *"Dịch vụ tùy biến in ấn tạm thời gián đoạn (máy chủ render đang bảo trì hoặc mất kết nối). Vui lòng thử lại sau ít phút."*

### BUG-004: Incomplete Factory Work Order Email
- **Reported Issue**: Clicking "Send email to factory" dispatched plain text with missing production specifications, customer shipping details, and mockup verification.
- **Requirement**:
  - Build a professional, responsive 4-section HTML Work Order email:
    1. **Product & Specs Table**: Order ID, Order Date, Product Name, Quantity (prominent), SKU, Variation attributes (Size, Color, etc.), and 300 DPI Transparent PNG standard.
    2. **Production Files & Downloads**: Prominent CTA button + raw URL for Complete Production Package (ZIP) and Standalone 300 DPI PNG.
    3. **Visual Mockup Preview**: Embedded preview image (`<img>`) for visual alignment check by printer operators.
    4. **Delivery & Recipient Info**: Recipient name, phone number, shipping address, and customer notes.
  - Add WooCommerce Order Note logging dispatch timestamp and recipient email.

### BUG-005: Internal Backend URL & Broken ZIP Downloads
- **Reported Issue**: `Backend Worker URL` was configured as `http://pod_backend:3001` (internal Docker hostname). When administrators or factory workers clicked ZIP download links in the browser, the download failed because `pod_backend` cannot be resolved outside Docker.
- **Requirement**:
  - Store and use the **Public URL** (`http://pod-backend.localhost` in local dev, `https://render.yourdomain.com` in production) in `pod_backend_url`.
  - Add `http_api_curl` hook in `Plugin.php` so WordPress inside Docker can route `*.localhost` requests through the Docker gateway (`172.18.0.1:80`) to Traefik.
  - Set `BASE_URL` in backend `.env` to ensure callbacks and render outputs always construct public URLs.
  - Upgrade `OrderHandler::resolve_file_url()` to dynamically rewrite any legacy internal URLs (`http://pod_backend:3001`) to the configured public backend URL.

### BUG-006: Move Settings Menu to Settings Submenu (options-general.php)
- **Reported Issue**: Settings page was registered as a top-level menu and under WooCommerce submenu, cluttering the primary sidebar.
- **Requirement**:
  - Move the menu item to WordPress's standard **Cài đặt (Settings)** menu (`options-general.php`).
  - Use `add_options_page()` with capability `manage_options` (with fallback `manage_woocommerce`).
  - Redirect cleanup actions to `options-general.php?page=pod-customizer-settings`.

### BUG-007: Client-Side Production Storage & Stateless Render Backend Worker
- **Reported Issue**: Production print files (300 DPI PNG, factory ZIP) were stored permanently on the render backend, causing storage bloat on the worker and failing to keep client assets under client control.
- **Requirement**:
  - The render backend must act strictly as a stateless graphics processor.
  - Create `PrintStorageManager` to stream and persist production files in WordPress: `wp-content/uploads/pod-prints/{year}/{month}/order_{order_id}/`.
  - Update `CallbackController` to download production files, store local WordPress URLs in WooCommerce order item meta, and reply with `stored_locally: true`.
  - On the backend, delete temporary files once the client confirms successful local transfer.

### BUG-008: Presigned Secure Download Links & Work Order Data Integrity
- **Reported Issue**: Production file downloads in emails exposed direct static file URLs, lacking access control, tamper resistance, and expiration. Furthermore, recipient names were blank due to untrimmed single-space strings from WooCommerce, and `on-hold` orders did not auto-render.
- **Requirement**:
  - Implement presigned URLs with HMAC-SHA256 signatures (`/wp-json/pod-customizer/v1/download-production`) and 7-day TTL.
  - Reject tampered signatures with `403 Forbidden` and expired links with `410 Gone`.
  - Record download audit entries (IP, timestamp, item ID) directly into WooCommerce Order Notes.
  - Apply `trim()` to recipient name and shipping address resolution.
  - Listen to `woocommerce_order_status_on-hold` so COD/BACS orders render immediately.
### BUG-009: 300 DPI Layout Alignment & Comprehensive Factory Production Specs Manifest
- **Reported Issue**: 
  1. The 300 DPI print file (`01_print_ready_300dpi.png`) had layout discrepancies compared to the canvas preview mockup (`02_mockup_preview.jpg`). Fabric.js center-origin coordinates were treated as top-left by Sharp, shifting layers down and right (clipping the clipart at the bottom edge), and text SVGs were clipped by a small hardcoded 800px viewport.
  2. The production specs file inside the ZIP (`04_production_specs.txt`) lacked user customization information (no text, no font, no colors, no asset paths) and had no extensibility for future dynamic fields.
  3. `02_mockup_preview.jpg` was omitted from factory packages because `preview_url` was not forwarded by `OrderWebhookDispatcher`.
- **Requirement**:
  - Transform center coordinates `(centerX, centerY)` to top-left `(centerX - w/2, centerY - h/2)` for image/clipart layers, taking rotation bounding boxes into account.
  - Render SVG text at target canvas dimensions (`3000x3000px`) using `text-anchor="middle"` and `dominant-baseline="central"`, with multi-line splitting and font fallback.
  - Generate a detailed, human-readable `04_production_specs.txt` manifest containing complete order metadata, layer-by-layer specifications (type, text, font, size, color, alignment, positions, rotation, raw asset paths), and dynamic key-value reflection for future custom fields.
  - Forward `recipient_name`, `product_name`, and `preview_url` in `OrderWebhookDispatcher` so factory bundles include the preview mockup and order context.

### BUG-010: Responsive 2-Column Workspace Layout (Un-hardcode 360px)
- **Reported Issue**: `.pod-workspace` had a fixed hardcoded left column width (`grid-template-columns: 360px 1fr`) and collapsed to 1 column at 860px. When the container width is narrow or variable, the hardcoded 360px caused layout imbalance. The business requirement dictates maintaining a persistent 2-column layout (50/50 split) across all screen widths without arbitrary media query collapses.
- **Requirement**:
  - Replace `grid-template-columns: 360px 1fr` with `repeat(2, minmax(0, 1fr))` to guarantee equal-width, flexible columns.
  - Remove the `@media (max-width: 860px)` single-column rule to keep the 2-column presentation consistent.

### BUG-011: Storefront Customizer Flat Minimalist Styling & Single-Column Stack Layout
- **Reported Issue**: Customizer widget featured rounded cards and heavy elevation box shadows that clashed with minimalist flat theme designs, and requested a single-column stacked layout (Canvas on top, control tabs below).
- **Requirement**:
  - Convert `.pod-workspace` to single-column layout (`grid-template-columns: 1fr; gap: 20px`).
  - Set `.pod-customizer-app` padding to `20px`.
  - Remove `box-shadow` and `border-radius` from the app container, canvas wrapper, tab navigation, and tab content panels.
  - Maintain a clean, subtle border (`1px solid var(--pod-border)` / `#e2e8f0`).

### BUG-012: SQLite-Backed Domain License Verification & Connection Diagnostics (Days Remaining & Graceful Status)
- **Reported Issue**:
  1. Backend hiện tại chỉ xác thực shared secret tĩnh (`X-POD-SECRET`), chưa quản lý thời hạn giấy phép kết nối theo từng Domain (ví dụ gói 1 năm), dẫn đến rủi ro không thể kiểm soát các shop/domain đối tác hết hạn.
  2. Nút "Test Connection" trong trang Cài đặt WordPress (`SettingsPage.php`) hiện chỉ kiểm tra server sống hay chết (`/health`), chưa chẩn đoán và hiển thị thời hạn giấy phép (ngày bắt đầu `starts_at`, ngày hết hạn `expires_at`, và số ngày sử dụng còn lại `days_remaining`).
- **Architectural & Business Requirements**:
  1. **Hạ tầng CSDL SQLite phía Backend (Zero External DB Dependency)**:
     - Sử dụng SQLite file-based (lưu tại `storage/licenses.sqlite` trên container `pod_backend`).
     - Bảng `licenses`:
       - `domain VARCHAR(255) PRIMARY KEY`
       - `secret_token VARCHAR(255) NOT NULL`
       - `status VARCHAR(20) DEFAULT 'active'` (`'active' | 'suspended' | 'expired'`)
       - `starts_at DATETIME NOT NULL`
       - `expires_at DATETIME NOT NULL`
       - `created_at DATETIME DEFAULT CURRENT_TIMESTAMP`
     - Tự động gieo mầm (seed) bản ghi mặc định cho domain `pod.localhost` (thời hạn 1 năm từ ngày tạo) khi khởi chạy server.
  2. **Quy tắc Kiểm tra Kết nối (Test Connection Diagnostics Rule)**:
     - Khi Admin bấm nút "Test Connection" trong WordPress (`SettingsPage.php`):
       - WordPress gửi kèm `X-POD-SECRET` và `X-POD-DOMAIN` (domain hiện tại của site).
       - Backend trả về **HTTP 200 (Luôn luôn thành công về mặt kết nối)** bất kể license còn hạn hay đã hết hạn:
         ```json
         {
           "status": "healthy",
           "service": "pod-backend-render-engine",
           "license": {
             "domain": "pod.localhost",
             "status": "active",
             "starts_at": "2026-09-18T00:00:00Z",
             "expires_at": "2027-09-18T00:00:00Z",
             "days_remaining": 365,
             "is_expired": false
           }
         }
         ```
       - Phía WordPress Admin hiển thị thông báo trực quan rõ ràng:
         - ✅ Kết nối mạng thành công tới Render Engine.
         - 📅 Ngày kích hoạt: `YYYY-MM-DD`
         - ⏳ Ngày hết hạn: `YYYY-MM-DD` (Còn lại: `N` ngày).
         - Nếu `is_expired == true`: Vẫn xác nhận kết nối mạng thành công nhưng hiển thị cảnh báo đỏ nổi bật: *"Gói kết nối của domain này đã hết hạn (quá hạn X ngày). Vui lòng gia hạn dịch vụ."*
  3. **Quy tắc Bắt buộc Chặn với Các API Nghiệp Vụ (Strict Gatekeeping for Functional APIs)**:
     - Tất cả các API chức năng nặng:
       - `POST /api/v1/render` (Render đơn hàng 300 DPI)
       - `POST /api/v1/templates/parse-psd` (Bóc tách file PSD)
     - Bắt buộc phải đi qua middleware xác thực bản quyền:
       - Nếu sai secret hoặc domain không tồn tại: `401 Unauthorized`.
       - Nếu `status !== 'active'` hoặc `expires_at <= NOW()` (hết hạn): **Lập tức từ chối với mã lỗi HTTP 403 Forbidden**:
         ```json
         {
           "success": false,
           "error_code": "LICENSE_EXPIRED",
           "error": "Gói kết nối Render Engine của domain [domain] đã hết hạn. Vui lòng gia hạn để tiếp tục xử lý in ấn."
         }
         ```

---

## 13. BUG-013: Domain License Protection & Diagnostics on Render Studio (/test-render)
### Vấn đề & Yêu cầu:
- Trang công cụ Render Studio (`GET /test-render` / `test-render.html`) trước đây chỉ kiểm tra secret token cứng mà chưa tích hợp kiểm tra bản quyền domain SQLite.
- Cần áp dụng cơ chế xác thực domain và hạn sử dụng tương tự:
  1. Khi truy cập `GET /test-render?domain=...&secret=...`:
     - Nếu không có secret hoặc secret sai $\rightarrow$ Hiển thị trang 401 Unauthorized.
     - Nếu domain chưa đăng ký $\rightarrow$ Hiển thị trang 403 Domain Chưa Đăng Ký.
     - Nếu license đã hết hạn $\rightarrow$ Hiển thị trang 403 License Expired (thông báo ngày hết hạn cụ thể).
  2. Nút bấm khởi chạy công cụ trong WordPress (`SettingsPage.php`) tự động sinh URL gắn kèm `domain` và `secret`.
  3. Header của `test-render.html` tự động lấy thông tin chẩn đoán từ `/health`, hiển thị domain hiện tại cùng huy hiệu trạng thái: `License Active (X days remaining)` hoặc `License Expired`.
  4. Các nút thực hiện render trên công cụ truyền header `X-POD-DOMAIN` và `domain_name` vào API, ngăn chặn an toàn và thông báo rõ ràng khi giấy phép hết hạn.
