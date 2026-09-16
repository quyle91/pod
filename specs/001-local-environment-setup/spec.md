# Feature Specification: Local Development Infrastructure & Traefik Setup

**Feature Key**: `001-local-environment-setup`  
**Status**: `IMPLEMENTED`  
**Created At**: 2026-09-16  

---

## 1. Authoritative Requirements (Sources of Truth)

This foundational feature implements the baseline local containerized architecture, Traefik dynamic routing, and development environment defined in:

| Domain | Primary Requirement Document | Key Scope |
| :--- | :--- | :--- |
| **Project Constitution** | [`.specify/constitution.md`](../../.specify/constitution.md) | Architectural boundaries (WordPress vs Node.js Backend Render Worker), No Dummy Fallbacks |
| **Project Blueprint** | [`.specify/project.md`](../../.specify/project.md) | High-level system architecture, domains (`pod.localhost`, `pod-backend.localhost`), Tech Stack |
| **Execution Rules** | [`.specify/execution_rules.md`](../../.specify/execution_rules.md) | PHP OOP/SOLID/DRY rules, Node.js Sharp rules, AI verification commands |

---

## 2. Implementation Plan & Task Checklist

- **Technical Architecture & Container Plan**: [`./plan.md`](./plan.md)
- **Actionable Task Breakdown**: [`./tasks.md`](./tasks.md)

---

## 3. Scope of Services

1. **`pod.localhost`**:
   - `app`: PHP 8.2-FPM container with `gd`, `mysqli`, `pdo_mysql`, `zip`, `opcache`.
   - `web`: Nginx (`nginx:stable-alpine`) with FastCGI proxy to `app:9000`.
   - `db`: MySQL 8.4 container with dedicated volume `pod_db_data`.
   - Traefik labels routing `Host('pod.localhost')` on `proxy_network`.
2. **`pod-backend.localhost`**:
   - `backend`: Node.js 20 LTS container with `sharp` native image compositing, `/health`, and `/api/v1/render` endpoints.
   - Traefik labels routing `Host('pod-backend.localhost')` on `proxy_network`.

---

## 4. AI Verification Command Contract

```bash
# 1. Verify Docker Compose Syntax for pod.localhost
docker compose -f /home/quyle91/projects/pod/pod.localhost/docker-compose.yml -f /home/quyle91/projects/pod/pod.localhost/docker-compose.override.yml config

# 2. Verify Docker Compose Syntax for pod-backend.localhost
docker compose -f /home/quyle91/projects/pod/pod-backend.localhost/docker-compose.yml config

# 3. Verify syntax of all PHP files
docker run --rm -v /home/quyle91/projects/pod/pod.localhost/source/wp-content/plugins/pod-customizer:/code php:8.2-fpm find /code -name "*.php" -exec php -l {} \;

# 4. Verify syntax of all JavaScript backend files
docker run --rm -v /home/quyle91/projects/pod/pod-backend.localhost/src:/app/src node:20-alpine sh -c "node --check /app/src/server.js && node --check /app/src/routes/render.js && node --check /app/src/services/sharpRenderer.js && node --check /app/src/middleware/auth.js"
```
