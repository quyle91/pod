# Technical Plan: Automated End-to-End Order-to-Print Flow

**Feature Key**: `005-e2e-order-to-print-flow`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Status**: `READY_FOR_REVIEW`  

---

## 1. Network Topology & Container Communication

In Docker local development, communication occurs across two distinct paths:

```mermaid
sequenceDiagram
    autonumber
    participant WP as WordPress (pod_app / pod_web)
    participant Traefik as Local Traefik (:80)
    participant Backend as Backend Worker (pod_backend:3001)
    participant Storage as /app/prints Directory

    Note over WP, Backend: Step 1: Order Webhook
    WP->>Backend: POST http://pod_backend:3001/api/v1/render (Header: X-POD-SECRET)
    Backend-->>WP: 202 Accepted { "status": "processing" }

    Note over Backend, Storage: Step 2: 300 DPI Rendering
    Backend->>Backend: Sharp creates 300 DPI canvas
    Backend->>Storage: Writes order_X_item_Y_300dpi.png

    Note over Backend, WP: Step 3: Callback Update
    Backend->>WP: POST http://pod_web/wp-json/pod-customizer/v1/render-callback (Header: X-POD-SECRET)
    WP->>WP: update_metadata(_pod_print_ready_url)
    WP-->>Backend: 200 OK { "success": true }
```

> [!IMPORTANT]
> - Container-to-container calls inside the `proxy_network` must use internal Docker service hostnames (`http://pod_backend:3001` and `http://pod_web/`) to avoid external DNS or loopback hairpinning limitations on localhost.
> - Client browser downloads access external URLs via Traefik: `http://pod-backend.localhost/prints/...` and `http://pod.localhost/`.

---

## 2. Shared Authentication Configuration

Both ends must share identical secret tokens:
- **WordPress**: `pod_customizer_secret_key` in `wp_options` (or via constant/admin settings).
- **Node.js**: `SHARED_SECRET=pod_dev_secret_key_2026` in `pod-backend.localhost/.env`.

---

## 3. Order Item Meta Lifecycle State Machine

```mermaid
stateDiagram-v2
    [*] --> InCart: Buyer configures design
    InCart --> OrderPlaced: Add to Cart (state in session)
    OrderPlaced --> Processing: Checkout completed (_pod_canvas_state saved)
    Processing --> Rendering: OrderWebhookDispatcher triggers /api/v1/render
    Rendering --> Completed: Callback received (_pod_print_ready_url saved)
    Rendering --> Failed: Sharp error / invalid layers
    Failed --> Rendering: Admin triggers manual re-render button
    Completed --> [*]: Admin downloads 300 DPI print file
```

---

## 4. Acceptance Criteria

1. An order placed with personalized parameters triggers an automated webhook to Node.js backend.
2. A valid PNG file with 300 DPI metadata is written to `pod-backend.localhost/storage/prints/`.
3. The order item in WooCommerce Admin displays an active download link leading to the high-res file.
4. The downloaded image contains composite layers (mockup, clipart, styled text) matching the customer's design.
