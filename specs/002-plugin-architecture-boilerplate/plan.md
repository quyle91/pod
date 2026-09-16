# Implementation Plan: WordPress Plugin Architecture Boilerplate (`pod-customizer`)

**Feature Key**: `002-plugin-architecture-boilerplate`  
**Related Spec**: [`./spec.md`](./spec.md)  
**Status**: `IMPLEMENTED`  

---

## 1. Class Diagram & Architecture Design

```mermaid
classDiagram
    class Plugin {
        -Plugin instance$
        -array handlers
        +instance()$ Plugin
        +init() void
    }

    class HandlerInterface {
        <<interface>>
        +register_hooks() void
    }

    class DispatcherInterface {
        <<interface>>
        +dispatch(int order_id, int item_id, array canvas_state) array
    }

    class CartHandler {
        +register_hooks() void
        +add_cart_item_custom_data() array
        +get_cart_item_from_session() array
        +display_custom_data_in_cart() array
    }

    class OrderHandler {
        +register_hooks() void
        +save_cart_data_to_order_line_item() void
        +display_print_ready_file_in_admin() void
    }

    class OrderWebhookDispatcher {
        +register_hooks() void
        +on_order_processing(int order_id) void
        +dispatch(int order_id, int item_id, array canvas_state) array
    }

    class CallbackController {
        +register_hooks() void
        +register_routes() void
        +check_permission() bool
        +handle_callback() WP_REST_Response
    }

    class SettingsPage {
        +register_hooks() void
        +add_settings_menu() void
        +register_settings() void
        +render_settings_page() void
    }

    HandlerInterface <|.. CartHandler
    HandlerInterface <|.. OrderHandler
    HandlerInterface <|.. OrderWebhookDispatcher
    DispatcherInterface <|.. OrderWebhookDispatcher
    HandlerInterface <|.. CallbackController
    HandlerInterface <|.. SettingsPage
    Plugin o-- HandlerInterface
```

---

## 2. Directory Structure of Plugin

```text
source/wp-content/plugins/pod-customizer/
├── pod-customizer.php                  <--- Bootstrap & autoloader fallback
├── composer.json                       <--- PSR-4 namespace mapping
└── src/
    ├── Contracts/
    │   ├── HandlerInterface.php
    │   └── DispatcherInterface.php
    ├── Core/
    │   └── Plugin.php
    ├── WooCommerce/
    │   ├── CartHandler.php
    │   ├── OrderHandler.php
    │   └── OrderWebhookDispatcher.php
    ├── Admin/
    │   └── SettingsPage.php
    └── API/
        └── CallbackController.php
```
