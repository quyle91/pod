# Implementation Tasks: 008 - Admin Product Designer Configuration

**Spec Key**: `008-admin-product-designer-config`  
**Status**: `PENDING_EXECUTION`  

---

## Task Matrix & Checklist

### Phase 1: Core Admin Product Configuration & Meta Box (Màn hình quản trị sản phẩm)
- [ ] **TASK-801**: Tạo class `PodCustomizer\Admin\ProductConfigManager` chịu trách nhiệm lấy, chuẩn hóa, và lưu cấu hình `_pod_product_design_config` theo schema chuẩn.
- [ ] **TASK-802**: Tạo class `PodCustomizer\Admin\ProductCustomizerMetaBox` gắn vào màn hình chỉnh sửa sản phẩm WooCommerce (`post_type=product`).
- [ ] **TASK-803**: Thiết kế Tab 1 trong Meta Box: **General & Print Specs** (Bật/tắt Customizer cho sản phẩm, chọn preset nhanh T-Shirt/Mug/Tote/Canvas, nhập Width px, Height px, DPI 300).
- [ ] **TASK-804**: Thiết kế Tab 2 trong Meta Box: **Views & Styles Manager** (Danh sách các mặt in: Front/Back, chọn phôi Mockup từ WordPress Media Library qua `wp.media`, tọa độ vùng in `canvas_bounds`).
- [ ] **TASK-805**: Thiết kế Tab 3 trong Meta Box: **Permissions & Rules** (Bật/tắt cho phép thêm Text, Clipart, Upload ảnh, giới hạn file size MB).
- [ ] **TASK-806**: Lưu dữ liệu an toàn với CSRF nonce verification, sanitization sâu, và fallback giá trị mặc định nếu sản phẩm mới tạo.

---

### Phase 2: Dynamic Storefront Data Bridge (Đồng bộ cấu hình ra Storefront)
- [ ] **TASK-807**: Cập nhật `CustomizerAssets.php` để đọc cấu hình từ `ProductConfigManager::get_product_config($product_id)` và truyền vào `window.podCustomizerConfig`.
- [ ] **TASK-808**: Cập nhật logic lọc `apply_filters('pod_is_product_customizable')` tự động kiểm tra checkbox `enabled` của sản phẩm đó trong Admin.
- [ ] **TASK-809**: Nâng cấp `assets/js/src/canvas.js` và `mockup.js` để khởi tạo kích thước canvas và ảnh mockup tương ứng với từng sản phẩm.

---

### Phase 3: Multi-View Storefront Switcher (Chuyển đổi các mặt in Front/Back)
- [ ] **TASK-810**: Bổ sung thanh chuyển đổi mặt in (View Switcher UI) trên Storefront Canvas khi sản phẩm có nhiều hơn 1 view.
- [ ] **TASK-811**: Quản lý đa trạng thái canvas trong `assets/js/src/state.js` khi người dùng chuyển qua lại giữa các mặt in mà không làm mất layer đã thiết kế.
- [ ] **TASK-812**: Đóng gói payload đặt hàng đa mặt in `_pod_canvas_state` để backend render đầy đủ các mặt in thành phẩm.

---

### Phase 4: Verification & Extensibility (Kiểm thử & Khung mở rộng)
- [ ] **TASK-813**: Tạo sản phẩm thử nghiệm với quy cách in khác nhau (1 sản phẩm Áo thun 2 mặt in, 1 sản phẩm Cốc sứ Mug in vòng quanh).
- [ ] **TASK-814**: Kiểm tra hoạt động trên Storefront (chuyển đổi phôi mockup, giới hạn vùng in, thêm vào giỏ hàng).
- [ ] **TASK-815**: Giữ cấu trúc module mở rộng sẵn sàng tiếp nhận các yêu cầu bổ sung tiếp theo từ người dùng.
