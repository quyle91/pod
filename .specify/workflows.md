# Workflow Specifications: E-Commerce Storefront & Admin Fulfillment

---
title: "Workflows & Lifecycle Diagrams"
updated_at: 2026-09-16T00:00:00Z
updated_by: Antigravity
version: 1.0
status: APPROVED_FOR_DEVELOPMENT
---

Tài liệu này đặc tả chi tiết 2 quy trình hoạt động cốt lõi của hệ thống POD Personalization:
1. **Quy trình tuần tự hoàn chỉnh (End-to-End Sequence)** từ khi khách hàng bấm "Add to Cart" đến khi nhận hàng.
2. **Quy trình quản trị của Admin trong WooCommerce Dashboard** từ khâu cấu hình template đến khi xuất xưởng (Fulfillment).

---

## 1. Toàn Bộ Vòng Đời Đơn Hàng (Sequence Diagram)

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

## 2. Quy Trình Làm Việc Của Admin Trong Dashboard (Flowchart)

```mermaid
flowchart TD
    subgraph Setup["1. Cấu hình ban đầu (One-time Setup)"]
        A1["Vào WooCommerce > Settings > POD Customizer"] --> A2["Điền Backend Worker URL & Secret Key"]
        A2 --> A3["Tạo Template sản phẩm POD:<br/>Upload mockup áo, định nghĩa vùng in X/Y/Width/Height"]
    end
    subgraph OrderManagement["2. Quản trị & Xử lý đơn hàng hàng ngày"]
        B1["Khách đặt hàng mới"] --> B2["Vào WooCommerce > Orders"]
        B2 --> B3{"Kiểm tra đơn hàng có tùy biến POD?"}
        B3 -- "Không" --> B4["Xử lý như sản phẩm thông thường"]
        B3 -- "Có" --> B5{"Trạng thái Render file in 300 DPI"}
        B5 -- "Pending / Rendering" --> B6["Đợi vài giây để Backend Worker xử lý xong"]
        B6 --> B5
        B5 -- "Failed" --> B7["Bấm nút 'Re-render' để gửi lại webhook"]
        B7 --> B6
        B5 -- "Completed" --> B8["Hiển thị nút 'Download 300 DPI Print File'"]
        B8 --> B9["Admin tải file in ấn độ phân giải cao"]
    end
    subgraph Fulfillment["3. Xuất xưởng & Hoàn tất"]
        B9 --> C1["Gửi file in sang xưởng POD<br/>(hoặc webhook tự động đẩy qua CustomCat/Printful)"]
        C1 --> C2["Nhận mã Tracking từ đơn vị vận chuyển"]
        C2 --> C3["Nhập Tracking Code & Đổi Order status sang 'Completed'"]
        C3 --> C4["Khách nhận thông báo giao hàng & nhận bưu kiện"]
    end
    Setup --> OrderManagement
    OrderManagement --> Fulfillment
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
