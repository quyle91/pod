# Implementation Tasks: 008 - Template-Driven Personalizer & Admin Configuration

**Spec Key**: `008-admin-product-designer-config`  
**Status**: `POC_STOREFRONT_AND_BACKEND_COMPLETED`  

---

## Task Matrix & Checklist

### Phase 1: Template Catalog & Schemas (Bộ sưu tập Template Mẫu & Định nghĩa Dữ liệu)
- [x] **TASK-801**: Tạo thư mục lưu trữ template `wp-content/plugins/pod-customizer/templates/`.
- [x] **TASK-802**: Khởi tạo file mẫu `templates/tpl_01_text_autofit.json` (Áo thun T-Shirt: chỉ gồm Text slot có auto-shrink và xoay góc).
- [x] **TASK-803**: Khởi tạo file mẫu `templates/tpl_02_preset_picker.json` (Cốc Mug: gồm Text slot + bảng chọn biểu tượng Preset Icon).
- [x] **TASK-804**: Khởi tạo file mẫu `templates/tpl_03_birthday_candles.json` (Combo Sinh Nhật: gồm Text slot + ô đếm số lượng nến Repeater + bảng chọn Icon).
- [x] **TASK-805**: Xây dựng lớp PHP `PodCustomizer\Services\TemplateLoader` cho phép nạp template động qua tham số URL `?pod_tpl=...` hoặc qua postmeta của sản phẩm.

---

### Phase 2: Storefront Form-Driven Engine (Giao diện Form & Canvas Preview ngoài Storefront)
- [x] **TASK-806**: Xây dựng component Form sinh động dựa theo danh sách `fields` trong template JSON (thay thế giao diện vẽ/kéo thả tự do cũ).
- [x] **TASK-807**: Cài đặt thuật toán **Auto-Shrink Text** trên Fabric.js: tự động tính tỷ lệ co nhỏ font chữ khi độ dài chuỗi vượt quá `max_width_px`.
- [x] **TASK-808**: Cài đặt hỗ trợ chữ xoay góc (`rotation: deg`) cho text layer trên Fabric.js.
- [x] **TASK-809**: Cài đặt thuật toán **Dynamic Repeater**: tự động sinh và dàn đều $N$ cây nến (`horizontal_center_gap`) trong khung chứa khi người dùng đổi số tuổi.
- [x] **TASK-810**: Cài đặt component **Preset Icon Swatches**: bấm chọn icon tức thời cập nhật ảnh SVG/PNG trên canvas.
- [x] **TASK-811**: Thêm Debounce (50-100ms) khi gõ phím để preview mượt mà trên thiết bị di động.

---

### Phase 3: Sharp Backend 300 DPI Rendering Parity (Xử lý Render xưởng in chuẩn 100%)
- [x] **TASK-812**: Cài đặt các file font chữ `.ttf` chuẩn (*Montserrat, Dancing Script, Oswald*) vào container `pod_backend`.
- [x] **TASK-813**: Cập nhật `sharpRenderer.js` để nhận diện payload template và áp dụng chính xác thuật toán Auto-Shrink SVG text (kích thước font co giãn tương ứng).
- [x] **TASK-814**: Xử lý XML escaping cho tiếng Việt có dấu và ký tự đặc biệt (`&`, `<`, `>`, `"`, `'`).
- [x] **TASK-815**: Cập nhật `sharpRenderer.js` để render các sub-image lặp lại (Repeater candles) với đúng tọa độ dàn đều như trên canvas.

---

### Phase 4: E2E Verification & Schema Freeze (Kiểm thử toàn diện & Khóa Schema)
- [x] **TASK-816**: Kiểm thử E2E trên Storefront với cả 3 mẫu template (`tpl_01`, `tpl_02`, `tpl_03`).
- [x] **TASK-817**: Kiểm tra độ sắc nét và tỷ lệ 1:1 của file in 300 DPI tạo bởi backend Sharp.
- [x] **TASK-818**: Đánh giá và đóng băng cấu trúc JSON Schema chuẩn.

---

### Phase 5: WP-Admin Product Configuration Interface (Giao diện Quản trị WordPress)
- [ ] **TASK-819**: Tạo Meta Box `🎨 POD Customizer Studio Config` trong trang Edit Product WooCommerce dựa trên Schema đã đóng băng.
- [ ] **TASK-820**: Cho phép Admin chọn template có sẵn hoặc tùy biến thông số (Đổi ảnh Mockup qua WP Media Library, chỉnh tọa độ `x, y`, sửa font, đổi icon preset).
- [ ] **TASK-821**: Lưu dữ liệu an toàn với CSRF nonce verification, sanitization sâu, và fallback giá trị mặc định.
