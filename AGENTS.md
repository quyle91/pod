# AGENTS & AI DIRECTIVES - POD PROJECT

> **CRITICAL NOTICE FOR ALL AI AGENTS & DEVELOPERS:**  
> The `.specify/` folder is the **SINGLE SOURCE OF TRUTH** for this entire project.  
> All requirements, architectures, coding conventions, data contracts, and execution rules are strictly defined inside [`.specify/`](file:///.specify/).

---

## 1. Source of Truth & Governance

Every AI agent working on this repository MUST strictly consult and adhere to the specifications in the `.specify/` directory before proposing, designing, or implementing any changes.

- **Do NOT silently invent missing functionality.**
- **Do NOT alter approved business logic or workflows without explicit approval.**
- **Do NOT bypass or simplify requirements to ease implementation.**
- **All implementation decisions and code changes must strictly align with the files in [`.specify/`](file:///.specify/).**

---

## 2. Directory Structure of `.specify/`

| File / Directory | Purpose & Contents |
| :--- | :--- |
| [`.specify/constitution.md`](file:///.specify/constitution.md) | **Project Constitution**: Core rules, architecture separation (WordPress vs Backend Render Engine), strict data contracts, and quality standards. |
| [`.specify/project.md`](file:///.specify/project.md) | **Project Overview**: System architecture blueprint, local domains (`pod.localhost`, `pod-backend.localhost`), tech stack, and 5 development phases. |
| [`.specify/execution_rules.md`](file:///.specify/execution_rules.md) | **Execution & Quality Rules**: Strict PHP rules (DRY, SOLID - SRP/OCP/DIP, WPCS, PSR-4), Node.js Sharp rules, and AI Verification Command Contract. |
| [`specs/`](file:///specs/) | **Feature Specifications**: Phased tasks and acceptance criteria (e.g. `specs/001-local-environment-setup/`, `specs/002-plugin-architecture-boilerplate/`, `specs/003-backend-render-engine/`). |
| [`.specify/research/`](file:///.specify/research/) | **Research & Notes**: Architecture comparisons, CustomMax analysis, and background documentation. |

---

## 3. Strict Coding Conventions Summary

### A. PHP / WordPress Plugin (`pod.localhost`)
- **DRY (Don't Repeat Yourself)**: Eliminate code redundancy using traits, services, and shared contract interfaces.
- **SOLID Principles**:
  - **Single Responsibility (SRP)**: Each class has one focused duty (e.g., `CartHandler`, `OrderHandler`, `OrderWebhookDispatcher`).
  - **Open/Closed (OCP)**: Extend functionality through WordPress Hooks (`do_action`, `apply_filters`) instead of modifying core classes.
  - **Dependency Inversion (DIP)**: Depend on abstractions (`HandlerInterface`, `DispatcherInterface`).
- **No Dummy Fallbacks**: Strictly validate Canvas JSON states at ingestion point. Never use permissive default chains (`val || fallback`) that conceal corrupted data.

### B. Backend Render Worker (`pod-backend.localhost`)
- Use **Sharp** for high-resolution 300 DPI compositing. Never run heavy 300 DPI image processing within PHP-FPM.
- Asynchronous task handling with token-based mutual authentication (`X-POD-SECRET`).

---

## 4. Workflow for AI Agents

1. **Investigate `.specify/` First**: Always read the relevant files in `.specify/` matching the requested task before writing any code.
2. **Review Constitution & Execution Rules**: Ensure code respects `.specify/constitution.md` and `.specify/execution_rules.md`.
3. **Run AI Verification Contract**: Execute the automated checks defined in the active spec's `AI Verification Command Contract` and report results.
