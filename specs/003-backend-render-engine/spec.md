# Feature Specification: Node.js 300 DPI Graphics Render Engine

**Feature Key**: `003-backend-render-engine`  
**Status**: `IMPLEMENTED`  
**Created At**: 2026-09-16  

---

## 1. Authoritative Requirements (Sources of Truth)

This specification defines the Node.js backend worker responsible for offloaded high-resolution print file creation using `sharp`, referenced in:

| Domain | Primary Requirement Document | Key Scope |
| :--- | :--- | :--- |
| **Project Constitution** | [`.specify/constitution.md`](../../.specify/constitution.md) | Architectural Boundaries & Offloaded Graphics Processing |
| **Execution Rules** | [`.specify/execution_rules.md`](../../.specify/execution_rules.md) | Sharp high-performance image pipeline, Token authentication, Structured logging |
| **Project Blueprint** | [`.specify/project.md`](../../.specify/project.md) | Node.js 20 LTS, Sharp Libvips, Asynchronous task queue |

---

## 2. Implementation Plan & Task Checklist

- **Technical Architecture & Data Flow**: [`./plan.md`](./plan.md)
- **Actionable Task Breakdown**: [`./tasks.md`](./tasks.md)

---

## 3. Scope of Backend Endpoints & Services

1. **`GET /health`**: Returns system health, service identifier, and ISO timestamp.
2. **`POST /api/v1/render`**: Protected endpoint requiring `X-POD-SECRET`. Accepts personalization payload (`order_id`, `item_id`, `layers`, `dimensions`, `callback_url`), executes 300 DPI compositing, and fires asynchronous callback to WordPress.
3. **`GET /prints/:filename`**: Static file server delivering high-resolution PNG/PDF output files.
4. **`services/sharpRenderer.js`**: Core module compositing multi-layer canvases at 300 DPI print resolution.

---

## 4. AI Verification Command Contract

```bash
# 1. Verify Node.js syntax across all backend modules
docker run --rm -v /home/quyle91/projects/pod/pod-backend.localhost/src:/app/src node:20-alpine sh -c "node --check /app/src/server.js && node --check /app/src/routes/render.js && node --check /app/src/services/sharpRenderer.js && node --check /app/src/middleware/auth.js"
```
