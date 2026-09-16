# Workflow Specifications: E-Commerce Storefront & Admin Fulfillment

---
title: "Workflows & Lifecycle Diagrams"
updated_at: 2026-09-16T00:00:00Z
updated_by: Antigravity
version: 1.1
status: APPROVED_FOR_DEVELOPMENT
---

Tài liệu này đặc tả trực quan các quy trình cốt lõi của hệ thống POD Personalization bằng sơ đồ hình khối tiêu chuẩn:
1. **Sơ đồ hình khối tổng thể (Multi-Shape Flowchart)**: Thể hiện hành trình từ lúc User nhấn "Add to Cart", hệ thống ngầm render 300 DPI, đến Admin xử lý trong Dashboard và hoàn tất đơn hàng.
2. **Sơ đồ tương tác tuần tự (Sequence Diagram)**: Chi tiết thông điệp API và webhook giữa các tầng hệ thống.
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
    subgraph Frontend["🛒 LUỒNG 1: FRONT-END USER (TỪ ADD TO CART ĐẾN NHẬN HÀNG)"]
        StartUser(["(( Khách vào trang sản phẩm ))"]):::startEnd
        InputDesign[/"Khách nhập Text, chọn Font, chọn Clipart, đổi màu"/]:::userAction
        ClickAddToCart["[ Bấm nút: Add to cart ]"]:::userAction
        SaveCartMeta["[ Lưu JSON state vào WooCommerce Cart Item ]"]:::systemProcess
        UserCheckout["[ Xem giỏ hàng & Tiến hành Thanh toán ]"]:::userAction
        OrderCreated{"{ Đặt hàng thành công? }"}:::decision
        FailPay["[ Báo lỗi thanh toán ]"]:::error
        OrderProcessing(["(( Đơn hàng: PROCESSING ))"]):::startEnd
        UserWait["[ Chờ xưởng sản xuất & giao hàng ]"]:::userAction
        UserReceive(["(( Khách nhận bưu kiện tận tay ))"]):::startEnd
    end
    subgraph System["⚙️ LUỒNG 2: XỬ LÝ NGẦM & LƯU TRỮ DỮ LIỆU"]
        DB_Order[("[( MySQL: wp_woocommerce_order_itemmeta )]")]:::storage
        TriggerWebhook[["[[ Webhook: Bắn JSON sang pod-backend.localhost ]]"]]:::systemProcess
        RenderEngine[["[[ Sharp Engine: Ghép layer ảnh độ nét 300 DPI ]]"]]:::systemProcess
        CheckRender{"{ Render thành công? }"}:::decision
        StoragePrints[("[( Storage: File in order_xxx_300dpi.png )]")]:::storage
        UpdateMeta["[ REST Callback: Cập nhật _pod_print_ready_url ]"]:::systemProcess
        LogRetry["[ Ghi log lỗi & chờ retry ]"]:::error
    end
    subgraph Dashboard["👨‍💼 LUỒNG 3: ADMIN DASHBOARD & FULFILLMENT"]
        AdminLogin["[ Admin vào WooCommerce > Orders ]"]:::adminAction
        InspectOrder{"{ Đơn hàng có file in POD? }"}:::decision
        WaitPrint["[ Đợi Render hoàn tất ]"]:::decision
        DownloadPrint[/"Tải file in 300 DPI độ nét cao"/]:::adminAction
        SendToFactory["[ Gửi file in & thông tin cho Xưởng in ]"]:::adminAction
        FactoryPrint["[ Xưởng in lên áo/cốc & đóng gói kiện hàng ]"]:::adminAction
        GetTracking[/"Nhận mã vận đơn Tracking Number"/]:::adminAction
        CompleteOrder(["(( Đổi trạng thái: COMPLETED ))"]):::startEnd
        ShippingDelivery["[ Shipper vận chuyển giao hàng ]"]:::adminAction
    end
    StartUser --> InputDesign --> ClickAddToCart --> SaveCartMeta --> UserCheckout --> OrderCreated
    OrderCreated -- "Không" --> FailPay
    OrderCreated -- "Có" --> OrderProcessing
    OrderProcessing --> DB_Order
    DB_Order --> TriggerWebhook --> RenderEngine --> CheckRender
    CheckRender -- "Lỗi" --> LogRetry --> TriggerWebhook
    CheckRender -- "Thành công" --> StoragePrints --> UpdateMeta
    UpdateMeta --> AdminLogin
    AdminLogin --> InspectOrder
    InspectOrder -- "Đang xử lý" --> WaitPrint --> InspectOrder
    InspectOrder -- "Sẵn sàng" --> DownloadPrint
    DownloadPrint --> SendToFactory --> FactoryPrint --> GetTracking --> CompleteOrder
    CompleteOrder --> ShippingDelivery --> UserWait --> UserReceive
```

---

## 2. Toàn Bộ Vòng Đời Đơn Hàng (Sequence Diagram)

```mermaid
sequenceDiagram
    autonumber
    actor User as Khách Hàng (Storefront)
    participant Cart as WooCommerce Cart & Checkout
    participant Plugin as Plugin pod-customizer
    participant Backend as Backend Render Worker (Node.js)
    actor Admin as Chủ Shop / Admin (Dashboard)
    actor Factory as Xưởng In & Shipper (Fulfillment)
    rect rgb(240, 248, 255)
        Note over User, Cart: GIAI ĐOẠN 1: THÊM GIỎ HÀNG & ĐẶT HÀNG
        User->>User: Chọn mẫu, nhập chữ, đổi clipart, màu sắc trên Canvas
        User->>Cart: Bấm nút "Add to cart"
        Plugin->>Cart: Chặn hook woocommerce_add_cart_item_data: lưu JSON state
        Cart-->>User: Hiển thị giỏ hàng kèm badge "Personalized Design"
        User->>Cart: Tiến hành Checkout & Thanh toán thành công
        Cart->>Plugin: Chuyển giỏ hàng thành Order Item Meta (_pod_canvas_state)
    end
    rect rgb(255, 250, 240)
        Note over Cart, Backend: GIAI ĐOẠN 2: TỰ ĐỘNG KẾT XUẤT FILE IN (OFFLOADED)
        Cart->>Plugin: Đơn hàng chuyển sang trạng thái "Processing"
        Plugin->>Backend: Bắn Webhook (/api/v1/render) kèm JSON layer & Secret Token
        Backend->>Backend: Sharp compositing các layer ở độ phân giải thực 300 DPI
        Backend->>Backend: Lưu file PNG/PDF vào storage/prints/
        Backend->>Plugin: Callback API (/render-callback) cập nhật link file in & status
        Plugin->>Cart: Cập nhật _pod_print_ready_url và ghi Order Note
    end
    rect rgb(245, 255, 245)
        Note over Admin, Factory: GIAI ĐOẠN 3: ADMIN DUYỆT & ĐẨY XƯỞNG IN
        Admin->>Cart: Mở WooCommerce > Orders Dashboard
        Admin->>Cart: Mở chi tiết đơn hàng (Thấy nút "Download 300 DPI Print File")
        Admin->>Admin: Tải file in kiểm tra kích thước / độ nét
        Admin->>Factory: Gửi file in 300 DPI & thông tin giao hàng cho xưởng in
        Factory->>Factory: In sản phẩm (Áo, Cốc, Tranh...) & đóng gói
        Factory-->>Admin: Bàn giao vận chuyển & cấp mã vận đơn (Tracking number)
    end
    rect rgb(255, 245, 245)
        Note over Admin, User: GIAI ĐOẠN 4: GIAO HÀNG & HOÀN TẤT
        Admin->>Cart: Cập nhật trạng thái đơn thành "Completed" (gắn Tracking)
        Cart-->>User: Gửi email thông báo "Đơn hàng đã được giao cho shipper"
        Factory->>User: Shipper giao bưu kiện tận tay khách hàng
        User->>User: Nhận sản phẩm hoàn thiện đúng như bản thiết kế
    end
```

---

## 3. Đặc Tả Chi Tiết Từng Giai Đoạn

### Giai đoạn 1: Storefront Customization & Add to Cart
- **Người thực hiện**: Khách hàng (End-user).
- **Hành vi**: Khách mở trang sản phẩm POD, thao tác trên Live Preview Canvas nhẹ (72 DPI).
- **Sự kiện cốt lõi**:
  1. Khách bấm *"Add to cart"*, Canvas export toàn bộ cấu hình thành chuỗi JSON (`pod_canvas_state`).
  2. Plugin WordPress đón filter `woocommerce_add_cart_item_data`, kiểm tra tính hợp lệ của JSON và lưu vào session giỏ hàng.
  3. Giỏ hàng và trang thanh toán hiển thị badge nhận diện sản phẩm cá nhân hóa.

### Giai đoạn 2: Tự Động Render File In Độ Phân Giải Cao (Offloaded 300 DPI)
- **Người thực hiện**: Hệ thống tự động (Plugin + Backend Worker).
- **Hành vi**:
  1. Khi đơn hàng chuyển sang trạng thái `processing`, `OrderWebhookDispatcher` gửi request POST sang `http://pod-backend.localhost/api/v1/render`.
  2. Backend Worker xác thực token `X-POD-SECRET`, đẩy vào queue xử lý.
  3. Thư viện `sharp` tạo canvas mật độ in 300 DPI (`density: 300`), ghép lần lượt các layer ảnh gốc chất lượng cao và xuất file PNG/PDF vào `storage/prints/`.
  4. Backend Worker gọi ngược REST API `/wp-json/pod-customizer/v1/render-callback` để lưu URL file in vào Order Item Meta (`_pod_print_ready_url`) và cập nhật status `completed`.

### Giai đoạn 3: Admin Phê Duyệt & Xuất Xưởng (Admin Dashboard)
- **Người thực hiện**: Chủ shop / Quản lý đơn hàng (Admin).
- **Hành vi**:
  1. Admin truy cập `WooCommerce > Orders`, mở chi tiết đơn hàng.
  2. Bên dưới tên sản phẩm có hiển thị hộp thông tin kèm nút: **"Download 300 DPI Print File"**.
  3. Admin tải file về kiểm tra hoặc hệ thống tự động đẩy file qua API xưởng in (CustomCat, Printful, Dreamship).
  4. Xưởng in tiến hành sản xuất sản phẩm thực tế theo file in 300 DPI.

### Giai đoạn 4: Giao Hàng & Hoàn Tất Đơn
- **Người thực hiện**: Đơn vị vận chuyển + Khách hàng.
- **Hành vi**:
  1. Xưởng in bàn giao kiện hàng cho đơn vị vận chuyển, cập nhật mã vận đơn (Tracking number).
  2. Admin chuyển trạng thái đơn hàng sang `Completed`.
  3. Khách nhận email thông báo kèm mã vận đơn và nhận hàng hoàn thiện tận tay.
