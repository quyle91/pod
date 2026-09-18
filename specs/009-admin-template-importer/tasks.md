# Implementation Tasks: 009 - Admin Template Importer (PSD Direct Flow)

**Spec Key**: `009-admin-template-importer`  
**Status**: `IN_PROGRESS` (Phase 1 Completed)  

---

## Danh mục Công việc (Task Matrix & Roadmap)

### Phase 1: Thư viện Đồ họa & Quản lý Asset (Asset Library Foundation)
- [x] **TASK-901**: Xây dựng bảng Database `{$wpdb->prefix}pod_icon_categories` và `{$wpdb->prefix}pod_icons`, đồng thời đăng ký Custom Post Type `pod_icon` và Taxonomy `pod_icon_category` theo chuẩn giao diện WordPress native.
- [x] **TASK-902**: Xây dựng giao diện quản trị chuẩn WordPress cho phép Admin upload hàng loạt Icon/Clipart (SVG, PNG 300 DPI) lưu vào WP Media Library và gom nhóm theo Category. Tích hợp nút "⚡ Bulk Upload Icons" trên danh sách bài viết.
- [x] **TASK-903**: Xây dựng REST API nội bộ trả về danh sách icon theo từng Category phục vụ Visual Editor và Storefront Form (`/wp-json/pod-customizer/v1/asset-library/categories` và `/icons`).
- [x] **TASK-903b**: Tạo công cụ và phát sinh file Photoshop PSD mẫu `sample_portrait_template.psd` ($1600 \times 2000\text{ px}$) gồm: Background, các Group Màu Da, Group Kiểu Tóc (100% Layer-driven), Pet Icon slot, và Customer Name text layer phục vụ kiểm thử Phase 2 & 3.

---

### Phase 2: Hạ tầng Tải lên Trực tiếp & Phân mảnh (Direct-to-Backend Chunked Upload)
- [ ] **TASK-904**: Xây dựng endpoint nhận phân mảnh `POST /api/templates/upload-chunk` trên Node.js Backend (`pod-backend.localhost`), hỗ trợ ghép file PSD tự động và resumable upload.
- [ ] **TASK-905**: Xây dựng JS Uploader trên WP-Admin: tự động chia nhỏ file PSD thành các lát 5MB, gửi trực tiếp sang Node.js (bỏ qua PHP) và hiển thị thanh phần trăm tiến trình tải lên.
- [ ] **TASK-906**: Quản lý hàng đợi tác vụ (Job Queue) trên Node.js: cấp `job_id`, lưu file vào thư mục đĩa tạm và phản hồi trạng thái cho client.

---

### Phase 3: Backend PSD Parser, Tối ưu RAM & Lưu trữ Hai Tầng (Asynchronous Worker Engine)
- [ ] **TASK-907**: Tích hợp `ag-psd` chạy trong **Child Process / Worker riêng biệt** để cách ly bộ nhớ, kích hoạt cờ `skipCompositeImage: true` và `skipThumbnail: true` để tiết kiệm RAM.
- [ ] **TASK-908**: Cài đặt bộ bóc tách layer: Đọc cây thư mục, thứ tự Z-index, tọa độ $X, Y$, kích thước $W, H$, góc xoay, và xuất pixel data của layer thành file ảnh PNG 300 DPI trong suốt.
- [ ] **TASK-909**: **Chiến lược Lưu trữ Hai Tầng (Two-Tier Storage)**: Lưu các layer PNG bóc tách trực tiếp vào `wp-content/uploads/pod-templates/{template_id}/layers/` mà KHÔNG đăng ký vào `wp_posts` (chống rác WP Media Library và tránh chạy resize crop thumbnail).
- [ ] **TASK-910**: Đọc thông tin text layer: Font family, font size, màu sắc hex, căn lề và chuỗi ký tự mặc định.
- [ ] **TASK-911**: Áp dụng quy ước tiền tố thông minh (`[fixed]`, `[group:name]`, `[text]`, `[slot:icon]`, `[repeater]`) để phân loại layer; tự động bóc tách Folder Group thành các trường `layer_selector` (100% Layer-driven cho màu da/kiểu tóc, không dùng mã màu) và sinh Template JSON chuẩn.
- [ ] **TASK-912**: Tự động dọn dẹp (cleanup) file `.psd` tạm trên ổ cứng và giải phóng 100% RAM sau khi Worker hoàn tất.
- [ ] **TASK-913**: Xây dựng endpoint kiểm tra tiến độ `GET /api/templates/jobs/:id/status` phục vụ polling cập nhật giao diện Admin.

---

### Phase 4: Admin Visual Studio & Ánh xạ Thư viện (Visual Editor & Asset Binding)
- [ ] **TASK-914**: Xây dựng màn hình Canvas Visual Editor trong WP-Admin, tự động nạp kết quả JSON sau khi phân tích PSD thành công.
- [ ] **TASK-915**: Cho phép Admin dùng chuột kéo thả căn chỉnh vị trí hoặc nhập số liệu chính xác cho các thuộc tính: Tọa độ $X, Y$, Chiều rộng $W$, Chiều cao $H$, Độ xoay $\alpha$, Thứ tự lớp $Z$-index.
- [ ] **TASK-916**: Xây dựng panel thuộc tính (Property Inspector) hỗ trợ Data Binding: Cho phép Admin bấm vào một Icon Slot `[slot:icon]` và chọn liên kết với một Category trong Asset Library đã tạo ở Phase 1.
- [ ] **TASK-917**: Thêm tính năng Khóa layer (Lock layer) và Ẩn/Hiện layer (Toggle visibility).

---

### Phase 5: Hướng dẫn Thiết kế & Kiểm thử Tích hợp (Design Guidelines & E2E Verification)
- [ ] **TASK-918**: Soạn thảo tài liệu chuẩn **PSD Design Guidelines** cho Designer (quy tắc Clean-up, Rasterize Smart Object để giảm 70% dung lượng, quy ước đặt tên layer).
- [ ] **TASK-919**: Tích hợp nút *"Import Template từ PSD"* trực tiếp trong Meta Box cấu hình sản phẩm WooCommerce, lưu Template JSON vào `_pod_template_config`.
- [ ] **TASK-920**: Kiểm thử tích hợp E2E: Upload 1 file PSD thực tế từ Designer $\rightarrow$ Bóc tách layer qua Worker ngầm $\rightarrow$ Chỉnh sửa trên Visual Studio $\rightarrow$ Khách đặt hàng trên Storefront $\rightarrow$ Kiểm tra file in 300 DPI xuất xưởng từ `sharpRenderer.js`.
