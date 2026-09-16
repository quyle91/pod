# Task Checklist: Local Development Infrastructure & Traefik Setup

**Feature Key**: `001-local-environment-setup`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Related Plan**: [`./plan.md`](./plan.md)  
**Status**: `IMPLEMENTED`  
**Authoritative References**:
- Infrastructure & Project Blueprint: [`../../.specify/project.md`](../../.specify/project.md)
- Project Constitution: [`../../.specify/constitution.md`](../../.specify/constitution.md)
- Execution Rules: [`../../.specify/execution_rules.md`](../../.specify/execution_rules.md)

---

## Task Breakdown & Verification Checklist

### Phase 1: WordPress Docker Environment (`pod.localhost`)
- [x] **TASK-001**: Create `.env` and `.env.example` with `APP_DOMAIN=pod.localhost`, `VOLUME_DB=pod_db_data`, `HOST_USER=1000:1000`.
- [x] **TASK-002**: Create `Dockerfile` with PHP 8.2-FPM and required extensions (`gd`, `mysqli`, `pdo_mysql`, `zip`, `opcache`).
- [x] **TASK-003**: Create `docker-compose.yml` and `docker-compose.override.yml` with services (`app`, `web`, `db`), volumes, and Traefik routing labels on `proxy_network`.
- [x] **TASK-004**: Create Nginx reverse proxy configuration (`docker/nginx/default.conf`) and PHP-FPM settings (`docker/php/www.conf`, `docker/php/zzz-uploads.ini`).

---

### Phase 2: Backend Render Engine Docker Environment (`pod-backend.localhost`)
- [x] **TASK-005**: Create `.env` and `.env.example` with `PORT=3001`, `APP_DOMAIN=pod-backend.localhost`, and `SHARED_SECRET`.
- [x] **TASK-006**: Create `Dockerfile` with Node.js 20 LTS, system fonts, and fontconfig dependencies.
- [x] **TASK-007**: Create `docker-compose.yml` with Traefik labels routing `pod-backend.localhost` to port 3001 on `proxy_network`.
- [x] **TASK-008**: Create `package.json` with dependencies (`express`, `sharp`, `cors`, `zod`, `axios`).

---

### Phase 3: Node.js Backend Service Scaffolding
- [x] **TASK-009**: Create `src/server.js` with Express, CORS, JSON parsers, and static serving `/prints`.
- [x] **TASK-010**: Implement `GET /health` endpoint for readiness/liveness checks.
- [x] **TASK-011**: Implement `src/middleware/auth.js` to enforce `X-POD-SECRET` header verification.
- [x] **TASK-012**: Implement `src/services/sharpRenderer.js` with 300 DPI metadata canvas creation and layer compositing.
- [x] **TASK-013**: Implement `src/routes/render.js` for `POST /api/v1/render` with callback support.

---

### Phase 4: Automated Testing & Syntax Verification
- [x] **TASK-014**: Validate Docker Compose configuration for `pod.localhost` with `docker compose config`.
- [x] **TASK-015**: Validate Docker Compose configuration for `pod-backend.localhost` with `docker compose config`.
- [x] **TASK-016**: Execute JavaScript syntax verification across all backend files using `node --check`.

---

### Phase 5: AI & Dev Verification
- [x] **TASK-017**: Confirm Traefik network compatibility with existing `proxy_network` and `local_traefik` container.
- [x] **TASK-018**: Verify all specifications and execution rules are cross-referenced with `.specify/`.
- [x] **TASK-019**: Live Deployment Verification:
  - `pod_app` (PHP 8.2-FPM): RUNNING
  - `pod_web` (Nginx): RUNNING
  - `pod_db` (MySQL 8.4): RUNNING
  - `pod_backend` (Node.js 20 + Sharp): RUNNING
  - Endpoint `http://pod-backend.localhost/health` -> 200 OK (`status: healthy`)
  - Endpoint `http://pod.localhost` -> 302 Redirect to `/wp-admin/install.php` (WordPress Core Loaded)
