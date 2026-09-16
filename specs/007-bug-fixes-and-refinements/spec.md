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

