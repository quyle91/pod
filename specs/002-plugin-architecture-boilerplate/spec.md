# Feature Specification: WordPress Plugin Architecture Boilerplate (`pod-customizer`)

**Feature Key**: `002-plugin-architecture-boilerplate`  
**Status**: `IMPLEMENTED`  
**Created At**: 2026-09-16  

---

## 1. Authoritative Requirements (Sources of Truth)

This specification implements the core WooCommerce plugin architecture adhering strictly to PHP SOLID and DRY principles defined in:

| Domain | Primary Requirement Document | Key Scope |
| :--- | :--- | :--- |
| **Project Constitution** | [`.specify/constitution.md`](../../.specify/constitution.md) | PHP & WordPress Plugin Development Standards, Strict Data Contracts |
| **Execution Rules** | [`.specify/execution_rules.md`](../../.specify/execution_rules.md) | PSR-4 autoloading, Single Responsibility, Open/Closed hooks, No dummy fallbacks |
| **Project Blueprint** | [`.specify/project.md`](../../.specify/project.md) | Cart item data lifecycle, Order line item metadata, Webhook triggers |

---

## 2. Implementation Plan & Task Checklist

- **Technical Architecture & OOP Design**: [`./plan.md`](./plan.md)
- **Actionable Task Breakdown**: [`./tasks.md`](./tasks.md)

---

## 3. Scope of Plugin Modules

1. **`Core\Plugin`**: Singleton bootstrap managing lifecycle and handler registration.
2. **`Contracts\HandlerInterface`**: Interface contract for modular hook registration (`register_hooks()`).
3. **`Contracts\DispatcherInterface`**: Abstraction contract for dispatching render tasks (`dispatch()`).
4. **`WooCommerce\CartHandler`**: Intercepts `woocommerce_add_cart_item_data`, sanitizes Canvas JSON state, displays customization badges in cart/checkout.
5. **`WooCommerce\OrderHandler`**: Persists Canvas state into `_pod_canvas_state` upon order placement, renders "Download 300 DPI Print File" button in Admin Order edit view.
6. **`WooCommerce\OrderWebhookDispatcher`**: Dispatches render webhook to `pod-backend.localhost` when order transitions to `processing`.
7. **`API\CallbackController`**: Authenticated REST API endpoint `/wp-json/pod-customizer/v1/render-callback` updating order item with high-res file download link.
8. **`Admin\SettingsPage`**: WooCommerce sub-menu page configuring Backend Worker URL and shared secret token.

---

## 4. AI Verification Command Contract

```bash
# 1. Verify PHP syntax on all plugin files
docker run --rm -v /home/quyle91/projects/pod/pod.localhost/source/wp-content/plugins/pod-customizer:/code php:8.2-fpm find /code -name "*.php" -exec php -l {} \;
```
