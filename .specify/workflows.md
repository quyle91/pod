# Workflow Specifications: E-Commerce Storefront & Admin Fulfillment

---
title: "Workflows & Lifecycle Diagrams"
updated_at: 2026-09-16T15:15:00Z
updated_by: Antigravity
version: 2.0
status: APPROVED_FOR_DEVELOPMENT
---

Tài liệu này đặc tả trực quan các quy trình cốt lõi của hệ thống POD Personalization bằng sơ đồ hình khối tiêu chuẩn:
1. **Sơ đồ hình khối tổng thể (Multi-Shape Flowchart)**: Thể hiện hành trình từ lúc User thiết kế trên Canvas, kiểm tra Backend Health khi Add to Cart, hệ thống ngầm render 300 DPI & đóng gói ZIP, kéo file về lưu trữ nội bộ tại WordPress Client, giải phóng worker tạm thời, đến Admin xử lý trong Dashboard và hoàn tất đơn hàng.
2. **Sơ đồ tương tác tuần tự (Sequence Diagram)**: Chi tiết thông điệp API, webhook, cơ chế stream lưu file client-side và dọn dẹp worker phi trạng thái.
3. **Đặc tả nghiệp vụ chi tiết 4 giai đoạn**.

---

## 1. Sơ Đồ Quy Trình Tổng Thể Đa Khối Hình (Comprehensive Flowchart)

> **Quy ước hình khối:**  
> - **Hình Tròn / Bầu dục `(( ... ))` / `([ ... ])`**: Điểm Bắt đầu, Kết thúc hoặc Trạng thái đơn hàng (Milestones / Status).  
> - **Hình Chữ nhật `[ ... ]`**: Hành động hoặc bước xử lý nghiệp vụ (Process).  
> - **Hình Thoi / Tam giác rẽ nhánh `{ ... }`**: Điểm quyết định logic / Rẽ nhánh điều kiện (Decision).  
> - **Hình Bình hành `[/ ... /]`**: Nhập dữ liệu đầu vào hoặc Xuất kết quả (Input / Output).  
> - **Hình Trụ `[( ... )]`**: Cơ sở dữ liệu và Kho lưu trữ file (Database / Storage).  
> - **Hình Hộp kép `[[ ... ]]`**: Tiến trình hệ thống xử lý ngầm (Subprocess).

```mermaid
flowchart TD
    classDef startEnd fill:#1e293b,stroke:#0f172a,stroke-width:2px,color:#fff;
    classDef userAction fill:#eff6ff,stroke:#3b82f6,stroke-width:2px,color:#1e3a8a;
    classDef systemProcess fill:#f5f3ff,stroke:#8b5cf6,stroke-width:2px,color:#4c1d95;
    classDef decision fill:#fffbeb,stroke:#f59e0b,stroke-width:2px,color:#78350f;
    classDef storage fill:#ecfdf5,stroke:#10b981,stroke-width:2px,color:#064e3b;
    classDef adminAction fill:#f0fdf4,stroke:#16a34a,stroke-width:2px,color:#14532d;
    classDef error fill:#fef2f2,stroke:#ef4444,stroke-width:2px,color:#7f1d1d;

    subgraph Frontend["🛒 LUỒNG 1: STOREFRONT CUSTOMIZATION & ADD TO CART"]
        StartUser(["(( Khách vào trang sản phẩm ))"]):::startEnd
        InputDesign[/"Khách nhập Text, chọn Font, clipart, đổi màu trên Live Canvas"/]:::userAction
        ClickAddToCart["[ Bấm nút: Add to cart ]"]:::userAction
        CheckBackendHealth{"{ Backend Worker Online? (/health) }"}:::decision
        FailBackend["[ Chặn Add to Cart & Báo lỗi máy chủ gián đoạn ]"]:::error
        SaveCartMeta["[ Lưu JSON state & Mockup preview vào Cart Item ]"]:::systemProcess
        UserCheckout["[ Xem Cart & Mini-cart 'Customization: preview' -> Thanh toán ]"]:::userAction
        OrderCreated{"{ Đặt hàng thành công? }"}:::decision
        FailPay["[ Báo lỗi thanh toán WooCommerce ]"]:::error
        OrderProcessing(["(( Đơn hàng: PROCESSING ))"]):::startEnd
        UserWait["[ Chờ xưởng sản xuất & giao hàng ]"]:::userAction
        UserReceive(["(( Khách nhận bưu kiện tận tay ))"]):::startEnd
    end

    subgraph System["⚙️ LUỒNG 2: STATELESS RENDER WORKER & CLIENT STORAGE"]
        DB_Order[("[( MySQL: wp_woocommerce_order_itemmeta & pod_render_jobs )]")]:::storage
        TriggerWebhook[["[[ OrderWebhookDispatcher: Bắn POST /render sang Backend ]]"]]:::systemProcess
        RenderEngine[["[[ Sharp Engine: Ghép layer 300 DPI & Đóng gói Production ZIP ]]"]]:::systemProcess
        CheckRender{"{ Render thành công? }"}:::decision
        LogRetry["[ Ghi log lỗi & chờ Admin Re-render ]"]:::error
        CallbackWP[["[[ Webhook Callback: Gửi temp URLs về WordPress REST API ]]"]]:::systemProcess
        StreamToClient[["[[ PrintStorageManager: Stream & Lưu file vào wp-content/uploads/pod-prints/ ]]"]]:::systemProcess
        StorageClient[("[( Client Storage: order_xxx_300dpi.png & order_xxx_production.zip )]")]:::storage
        UpdateClientMeta["[ Cập nhật Item Meta bằng URL nội bộ WordPress & Reply stored_locally ]"]:::systemProcess
        WorkerUnlink["[ Backend Worker tự động XÓA FILE TẠM (unlink) -> Giữ Worker Stateless ]"]:::systemProcess
    end

    subgraph Dashboard["👨‍💼 LUỒNG 3: ADMIN DASHBOARD & FACTORY FULFILLMENT"]
        AdminLogin["[ Admin vào WooCommerce > Orders ]"]:::adminAction
        InspectOrder{"{ Đơn hàng có file in POD? }"}:::decision
        WaitPrint["[ Đợi Render hoàn tất hoặc Bấm Re-render ]"]:::decision
        DownloadPrint[/"Tải trực tiếp ZIP hoặc PNG 300 DPI từ Client WordPress"/]:::adminAction
        SendEmailFactory["[ Bấm 'Send email to factory' (Work Order HTML 4 phần) ]"]:::adminAction
        FactoryPrint["[ Xưởng in kiểm tra mockup, giải nén ZIP & tiến hành in ấn ]"]:::adminAction
        GetTracking[/"Cập nhật mã vận đơn Tracking Number"/]:::adminAction
        CompleteOrder(["(( Đổi trạng thái: COMPLETED ))"]):::startEnd
        ShippingDelivery["[ Shipper vận chuyển giao hàng ]"]:::adminAction
    end

    StartUser --> InputDesign --> ClickAddToCart --> CheckBackendHealth
    CheckBackendHealth -- "Offline / Timeout" --> FailBackend
    CheckBackendHealth -- "Online" --> SaveCartMeta --> UserCheckout --> OrderCreated
    OrderCreated -- "Không" --> FailPay
    OrderCreated -- "Có" --> OrderProcessing
    OrderProcessing --> DB_Order
    DB_Order --> TriggerWebhook --> RenderEngine --> CheckRender
    CheckRender -- "Lỗi" --> LogRetry
    CheckRender -- "Thành công" --> CallbackWP
    CallbackWP --> StreamToClient --> StorageClient --> UpdateClientMeta --> WorkerUnlink
    UpdateClientMeta --> AdminLogin
    AdminLogin --> InspectOrder
    InspectOrder -- "Đang xử lý / Lỗi" --> WaitPrint --> InspectOrder
    InspectOrder -- "Sẵn sàng" --> DownloadPrint
    InspectOrder -- "Sẵn sàng" --> SendEmailFactory
    DownloadPrint --> FactoryPrint
    SendEmailFactory --> FactoryPrint --> GetTracking --> CompleteOrder
    CompleteOrder --> ShippingDelivery --> UserWait --> UserReceive
```

---

## 2. Toàn Bộ Vòng Đời Đơn Hàng (Sequence Diagram)

```mermaid
sequenceDiagram
    autonumber
    actor User as Khách Hàng (Storefront)
    participant Cart as WooCommerce Cart & Checkout
    participant Plugin as Plugin pod-customizer (Client)
    participant Backend as Backend Render Worker (Node.js + Sharp)
    actor Admin as Chủ Shop / Admin (Dashboard)
    actor Factory as Xưởng In (Fulfillment)

    rect rgb(240, 248, 255)
        Note over User, Cart: GIAI ĐOẠN 1: THIẾT KẾ CANVAS, KIỂM TRA HEALTH & ĐẶT HÀNG
        User->>User: Chọn mẫu, nhập chữ, đổi font, clipart, màu sắc trên Live Canvas
        User->>Cart: Bấm nút "Add to cart"
        Cart->>Plugin: Hook woocommerce_add_to_cart_validation
        Plugin->>Backend: Ping GET /health (timeout 2s)
        alt Backend Offline / Timeout
            Plugin-->>User: Chặn thêm giỏ hàng kèm thông báo: Máy chủ in ấn đang bảo trì
        else Backend Healthy
            Plugin->>Cart: Cho phép Add to cart, lưu JSON state & Mockup preview
            Cart-->>User: Giỏ hàng hiển thị 1 dòng sạch sẽ: "Customization: [preview]"
            User->>Cart: Tiến hành Checkout & Thanh toán thành công
            Cart->>Plugin: Chuyển giỏ hàng thành Order Item Meta (_pod_canvas_state)
        end
    end

    rect rgb(255, 250, 240)
        Note over Cart, Backend: GIAI ĐOẠN 2: STATELESS RENDER WORKER & CLIENT STREAMING STORAGE
        Cart->>Plugin: Đơn hàng chuyển sang trạng thái "Processing"
        Plugin->>Backend: Webhook POST /api/v1/render kèm Canvas JSON & Secret Token
        Backend->>Backend: Sharp compositing các layer ở độ nét 300 DPI
        Backend->>Backend: Đóng gói Factory Production ZIP (PNG, mockup, raw assets, specs.txt)
        Backend->>Plugin: Webhook Callback (/render-callback) kèm temp URLs
        Note over Plugin: PrintStorageManager stream trực tiếp file PNG & ZIP về WordPress
        Plugin->>Plugin: Lưu vào wp-content/uploads/pod-prints/YYYY/MM/order_xxx/
        Plugin->>Cart: Cập nhật _pod_print_ready_url & _pod_production_zip_url (Local Client URLs)
        Plugin-->>Backend: Phản hồi HTTP 200 OK (stored_locally: true)
        Note over Backend: Backend Worker lập tức XÓA FILE TẠM trên ổ đĩa backend (Stateless)
    end

    rect rgb(245, 255, 245)
        Note over Admin, Factory: GIAI ĐOẠN 3: ADMIN DUYỆT ĐƠN & GỬI WORK ORDER XƯỞNG IN
        Admin->>Cart: Mở WooCommerce > Orders Dashboard
        Admin->>Cart: Mở chi tiết đơn hàng (Xem Mockup preview, Nút Tải ZIP, Nút Tải PNG 300 DPI)
        Admin->>Admin: Tải trực tiếp ZIP từ server WordPress để kiểm tra chất lượng
        Admin->>Factory: Bấm "Send email to factory" (Gửi Work Order HTML 4 phần chuẩn công nghiệp)
        Factory->>Factory: Mở email, xem Mockup đối chiếu, bấm tải Production ZIP trực tiếp từ Client
        Factory->>Factory: Tiến hành in ấn, kiểm thử và đóng gói sản phẩm
        Factory-->>Admin: Cập nhật mã vận đơn (Tracking number)
    end

    rect rgb(255, 245, 245)
        Note over Admin, User: GIAI ĐOẠN 4: VẬN CHUYỂN GIAO HÀNG & HOÀN TẤT
        Admin->>Cart: Cập nhật trạng thái đơn thành "Completed" (gắn Tracking)
        Cart-->>User: Gửi email thông báo "Đơn hàng đã được bàn giao cho đơn vị vận chuyển"
        Factory->>User: Shipper giao bưu kiện tận tay khách hàng
        User->>User: Nhận sản phẩm hoàn thiện đúng theo thiết kế cá nhân hóa
    end
```

---

## 3. Đặc Tả Chi Tiết Từng Giai Đoạn

### Giai đoạn 1: Storefront Customization, Health Check & Add to Cart
- **Người thực hiện**: Khách hàng (End-user).
- **Hành vi**: Khách mở trang sản phẩm POD, thao tác thiết kế trên Live Preview Canvas.
- **Sự kiện cốt lõi**:
  1. Khách bấm *"Add to cart"*, Canvas export toàn bộ cấu hình thành chuỗi JSON (`pod_canvas_state`) kèm ảnh preview mockup JPEG nhẹ.
  2. Filter `woocommerce_add_to_cart_validation` kích hoạt:
     - Gửi ping nhanh (`timeout: 2s`) tới endpoint `{$backend_url}/health`.
     - Nếu máy chủ render offline hoặc mất kết nối: Hệ thống chặn thêm giỏ hàng và hiển thị thông báo lỗi thân thiện: *"Dịch vụ tùy biến in ấn tạm thời gián đoạn. Vui lòng thử lại sau ít phút."*
     - Nếu kết nối bình thường: Cho phép thêm vào giỏ hàng.
  3. Giỏ hàng và Mini-cart Flatsome hiển thị 1 dòng văn bản duy nhất: `Customization: [preview]` (link mở ảnh preview trong tab mới, không dùng thẻ `<img>` tránh vỡ layout).
  4. Hệ thống tự động xóa cache `sessionStorage` cart fragments khi tải trang để đảm bảo dữ liệu luôn mới nhất.

---

### Giai đoạn 2: Stateless Render Worker & Client-Side File Storage
- **Người thực hiện**: Hệ thống tự động (Plugin + Backend Worker).
- **Hành vi**:
  1. Khi đơn hàng chuyển sang trạng thái `processing`, `OrderWebhookDispatcher` gửi request POST sang Backend Worker (`/api/v1/render`) kèm chữ ký `X-POD-SECRET`.
  2. Backend Worker (Node.js + Sharp) xử lý:
     - Ghép các layer ảnh chất lượng cao và text vector SVG ở mật độ 300 DPI (`density: 300`).
     - Đóng gói file ZIP hoàn chỉnh gồm: `01_print_ready_300dpi.png`, `02_mockup_preview.jpg`, `03_raw_assets/`, `04_production_specs.txt`.
  3. Backend Worker gọi Webhook Callback sang WordPress (`/wp-json/pod-customizer/v1/render-callback`) gửi kèm đường dẫn tạm thời.
  4. Phía WordPress (`PrintStorageManager`):
     - Sử dụng `wp_remote_get()` dạng stream trực tiếp để tải file PNG 300 DPI và file ZIP về lưu cố định tại:  
       `wp-content/uploads/pod-prints/{year}/{month}/order_{order_id}/`.
     - Cập nhật Order Item Meta bằng **chính URL công khai của WordPress client**:  
       `_pod_print_ready_url` & `_pod_production_zip_url`.
     - Trả về phản hồi `HTTP 200 OK` với cờ `stored_locally: true`.
  5. Phía Backend Worker:
     - Khi nhận được xác nhận `stored_locally: true`, worker lập tức gọi `fs.unlink()` để **xóa sạch file tạm** trên ổ đĩa backend.
     - Backend duy trì trạng thái hoàn toàn **stateless**, không tiêu tốn dung lượng lưu trữ dài hạn.

---

### Giai đoạn 3: Admin Dashboard & Factory Work Order Dispatch
- **Người thực hiện**: Quản trị viên (Admin) & Xưởng in (Factory).
- **Hành vi**:
  1. Menu Cài đặt tập trung tại: **WP Admin > Cài đặt (Settings) > POD Customizer** (`options-general.php?page=pod-customizer-settings`).
  2. Admin truy cập chi tiết đơn hàng tại `WooCommerce > Orders`:
     - Bên dưới sản phẩm tùy biến hiển thị hộp thông tin: ảnh preview hoàn thiện, nút **`[📦 Tải gói sản xuất (ZIP)]`**, nút **`[⬇️ Tải file in 300 DPI]`** và nút **`[🔄 Re-render Item]`**.
     - Cả 2 nút tải file đều trỏ trực tiếp về máy chủ WordPress client.
  3. Gửi thông tin xưởng in:
     - Nhập email xưởng (hoặc dùng email mặc định từ Settings) và bấm **`[✉️ Gửi email cho xưởng in]`**.
     - Hệ thống gửi email HTML Work Order chuyên nghiệp gồm 4 phần:
       1. Bảng thông số đơn hàng & quy cách in ấn (SKU phôi, kích cỡ, màu sắc, số lượng, chuẩn 300 DPI PNG).
       2. Nút bấm nổi bật tải Complete Production ZIP Package và Standalone 300 DPI PNG kèm đường dẫn URL thô.
       3. Ảnh Mockup hoàn thiện trực quan để thợ in đối chiếu vị trí/tỉ lệ.
       4. Thông tin giao nhận (Tên khách hàng, số điện thoại, địa chỉ nhận hàng, ghi chú của khách).
     - Tự động ghi Order Note trong lịch sử đơn hàng.

---

### Giai đoạn 4: Giao Hàng & Hoàn Tất Đơn
- **Người thực hiện**: Xưởng in + Đơn vị vận chuyển + Khách hàng.
- **Hành vi**:
  1. Xưởng in giải nén file ZIP, tiến hành in ấn bằng máy in chuyên dụng (DTG/Sublimation), kiểm định chất lượng đối chiếu với ảnh mockup.
  2. Bàn giao kiện hàng cho đơn vị vận chuyển và cấp mã vận đơn (Tracking number).
  3. Admin cập nhật mã vận đơn và chuyển trạng thái đơn hàng thành `Completed`.
  4. Khách nhận email thông báo tiến độ giao hàng và nhận bưu kiện hoàn thiện tận tay.
