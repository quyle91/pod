# Implementation Plan: Node.js 300 DPI Graphics Render Engine

**Feature Key**: `003-backend-render-engine`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Status**: `IMPLEMENTED`  

---

## 1. Sequence & Data Flow Diagram

```mermaid
sequenceDiagram
    autonumber
    participant WP as WordPress (pod.localhost)
    participant Auth as Auth Middleware
    participant Route as Render Route (/api/v1/render)
    participant Engine as SharpRenderer Service
    participant Storage as File Storage (/app/storage/prints)
    participant Callback as WordPress Callback API

    WP->>Auth: POST /api/v1/render (with X-POD-SECRET header)
    Auth->>Auth: Verify shared secret token
    Auth->>Route: Pass validated request
    Route->>Engine: sharpRenderer.render(payload)
    Engine->>Engine: Create 300 DPI canvas & composite layers
    Engine->>Storage: Save high-res PNG/PDF
    Engine->>Route: Return file metadata & URL
    Route-->>WP: HTTP 200 OK (immediate response)
    Route-)Callback: Async POST /render-callback (order_id, print_url)
```

---

## 2. Directory Layout of Backend

```text
pod-backend.localhost/
├── .env & .env.example
├── Dockerfile
├── docker-compose.yml
├── package.json
├── storage/
│   └── prints/                        <--- Rendered output files
└── src/
    ├── server.js                      <--- Express setup & middlewares
    ├── middleware/
    │   └── auth.js                    <--- Secret token verification
    ├── routes/
    │   └── render.js                  <--- /api/v1/render endpoint
    └── services/
        └── sharpRenderer.js           <--- Sharp 300 DPI compositing
```
