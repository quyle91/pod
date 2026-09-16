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

## 2. Active & Upcoming Tasks (Checklist Extension)

*New bug reports and enhancement tasks will be appended here as testing continues.*

- [ ] **TASK-040**: *(Awaiting next user report or edge case)*
