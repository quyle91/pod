# Specify Execution & Quality Assurance Rules - POD Project

This repository strictly enforces the **Specify System** for feature development. All AI agents, developers, and reviewers MUST adhere to the following rules during specification, implementation, testing, and quality assurance.

---

## 1. Structure of Feature Checklist (`tasks.md`)

Every feature specification directory (`.specify/specs/XXX-.../tasks.md`) MUST contain the following 5 mandatory phases:

- **Phase 1**: Infrastructure & Environment Configuration
- **Phase 2**: Core Data Models, Schemas & Contracts
- **Phase 3**: Integration & Business Logic Implementation
- **Phase 4**: Automated Testing & AI Verification
- **Phase 5**: End-to-End Developer Acceptance

---

## 2. Strict PHP & WordPress Architecture Rules

### 2.1 Object-Oriented Design & PSR-4 Autoloading
1. **No Monolithic Files**: Logic must never be placed inside the root `pod-customizer.php` file except for constants definition, bootstrap instantiation, and activation/deactivation hooks.
2. **Directory-to-Namespace Parity**: The namespace `PodCustomizer\` must map 1:1 with `src/` (or `includes/`).
3. **Dependency Injection**: Pass dependencies via constructors rather than abusing static globals or hidden singletons where testing is required.

### 2.2 Open/Closed Principle (Extensibility Rule)
1. **Hook-Driven Architecture**: Every critical processing step must fire WordPress actions and filters:
   - `do_action('pod_customizer_before_cart_item_added', $cart_item_data, $product_id);`
   - `apply_filters('pod_customizer_render_payload', $payload, $order, $item);`
2. **Pluggable Render Dispatchers**: Dispatching to the backend worker must implement `DispatcherInterface`, allowing local mock dispatchers in unit tests and production HTTP dispatchers in live environments.

### 2.3 DRY (Don't Repeat Yourself) Rule
1. Duplicate JSON encoding/decoding, input validation, or error logging across handlers is strictly forbidden.
2. Abstract common behaviors into traits or abstract base classes (e.g. `AbstractWebhookHandler`, `SanitizationHelper`).

### 2.4 Strict Data Contracts & Zero Fallbacks
1. Never use permissive default fallbacks (`$val ?? 'default'`) when expected schema properties are missing from the client Canvas JSON.
2. Reject invalid payloads with informative, typed errors at the point of ingestion (`wp_send_json_error(...)` with HTTP 422).

---

## 3. Node.js Backend Render Service Rules

1. **High-Performance Image Pipeline**:
   - Use `sharp` for all compositing and raster processing. Avoid pure JavaScript canvas implementations for large 300 DPI outputs.
2. **Asynchronous Execution & Concurrency Limits**:
   - Limit concurrent render operations using worker pools to prevent CPU saturation or Out-Of-Memory (OOM) crashes.
3. **Structured Logging**:
   - All render tasks must log: Task ID, Order ID, Processing Duration (ms), Memory Usage, and Exit Status.

---

## 4. AI Verification Command Contract

Every feature spec must define an `AI Verification Command Contract` listing concrete commands executable within Docker containers, such as:
- Docker configuration syntax check: `docker compose config`
- PHP syntax verification: `find src -name "*.php" -exec php -l {} \;`
- Backend healthcheck: `curl -f http://pod-backend.localhost/health`
- Storefront response check: `curl -f -I http://pod.localhost`

The implementing AI agent must execute these commands and report the output before marking any task as complete.
