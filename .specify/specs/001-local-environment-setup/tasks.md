# Spec 001: Local Development Infrastructure & Project Scaffolding

---
title: "001: Local Environment & Scaffolding"
status: IMPLEMENTED
created_at: 2026-09-16T00:00:00Z
updated_at: 2026-09-16T00:00:00Z
author: Antigravity
reviewer: User
---

## 1. Goal

Set up the local containerized environment on Docker WSL for both `pod.localhost` and `pod-backend.localhost` behind Traefik, alongside initializing the foundational codebase for the WordPress plugin and Node.js backend worker.

---

## 2. Architecture & Service Breakdown

- `pod.localhost`:
  - `app` service: PHP 8.2-FPM with essential extensions (`gd`, `mysqli`, `zip`, `opcache`).
  - `web` service: Nginx with Traefik routing rules for `pod.localhost`.
  - `db` service: MySQL 8.4 container with persistent data volume.
  - WordPress plugin boilerplate: `/source/wp-content/plugins/pod-customizer`.
- `pod-backend.localhost`:
  - `backend` service: Node.js 20 LTS with `sharp` support and Traefik routing rules for `pod-backend.localhost`.

---

## 3. Tasks Checklist

### Phase 1: Infrastructure & Environment Configuration
- [x] Create `pod.localhost` Docker configuration (`docker-compose.yml`, `docker-compose.override.yml`, `Dockerfile`, `.env`, `.env.example`).
- [x] Create `pod.localhost` Nginx and PHP-FPM configuration files (`docker/nginx/default.conf`, `docker/php/www.conf`, `docker/php/zzz-uploads.ini`).
- [x] Create `pod-backend.localhost` Docker configuration (`Dockerfile`, `docker-compose.yml`, `.env`, `.env.example`).

### Phase 2: Core Data Models, Schemas & Contracts
- [x] Define Canvas JSON State Data Contract in specification documentation.
- [x] Define Backend Render Webhook Payload contract (`order_id`, `item_id`, `dimensions`, `layers`, `callback_url`).

### Phase 3: Integration & Business Logic Implementation
- [x] Scaffold WordPress Plugin `pod-customizer` adhering to PSR-4, DRY, and SOLID principles (`src/Core/Plugin.php`, `src/Contracts/`, `src/WooCommerce/`).
- [x] Scaffold Node.js Backend service with Express, `/health` endpoint, and `/api/v1/render` endpoint stub.

### Phase 4: Automated Testing & AI Verification
- [x] Run `docker compose config` validation on `pod.localhost`.
- [x] Run `docker compose config` validation on `pod-backend.localhost`.
- [x] Run PHP syntax checks on all newly created PHP files (`php -l`).
- [x] Run Node.js syntax checks on all backend files (`node --check`).

### Phase 5: AI & Dev Verification
- [x] Verify network attachments to `proxy_network` and Traefik router labels.
- [x] Confirm directory structure aligns with `.specify/constitution.md`.

---

## 4. AI Verification Command Contract

```bash
# 1. Verify Docker compose configurations
docker compose -f /home/quyle91/projects/pod/pod.localhost/docker-compose.yml -f /home/quyle91/projects/pod/pod.localhost/docker-compose.override.yml config
docker compose -f /home/quyle91/projects/pod/pod-backend.localhost/docker-compose.yml config

# 2. Verify PHP Syntax in plugin
docker run --rm -v /home/quyle91/projects/pod/pod.localhost/source/wp-content/plugins/pod-customizer:/code php:8.2-fpm find /code -name "*.php" -exec php -l {} \;

# 3. Verify Node.js backend syntax
docker run --rm -v /home/quyle91/projects/pod/pod-backend.localhost/src:/app/src node:20-alpine sh -c "node --check /app/src/server.js && node --check /app/src/routes/render.js && node --check /app/src/services/sharpRenderer.js && node --check /app/src/middleware/auth.js"
```
