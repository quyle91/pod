# Implementation Tasks: 008 - Template-Driven Personalizer & Admin Configuration

**Spec Key**: `008-admin-product-designer-config`  
**Status**: `COMPLETED`  

---

## 🔗 Quick Test Links (Đường dẫn Test Trực tiếp)

| Template | Tên Mẫu & Tính Năng | Đường Dẫn Trực Tiếp |
| :--- | :--- | :--- |
| **Template 01** | 👕 **Áo Thun Slogan / Combo Đa Năng**: Auto-Fit Text, Repeater, Preset Picker | [http://pod.localhost/product/happy-ninja-2/?pod_tpl=tpl_01](http://pod.localhost/product/happy-ninja-2/?pod_tpl=tpl_01) |
| **Render Studio** | 🛠️ **Sharp 300 DPI Studio & QA Tool** (Kiểm tra render backend) | [http://pod-backend.localhost/test-render.html?domain=pod.localhost&secret=pod_secret_token_123456](http://pod-backend.localhost/test-render.html?domain=pod.localhost&secret=pod_secret_token_123456) |

---

## Task Matrix & Checklist

### Phase 1: Template Catalog & Schemas (Bộ sưu tập Template Mẫu & Định nghĩa Dữ liệu)
- [x] **TASK-801**: Tạo thư mục lưu trữ template `wp-content/plugins/pod-customizer/templates/`.
- [x] **TASK-802**: Khởi tạo file mẫu `templates/tpl_01.json` (Hợp nhất toàn bộ tính năng Text Auto-Fit, Repeater nến, Preset Picker biểu tượng).
- [x] **TASK-803**: Xây dựng lớp PHP `PodCustomizer\Services\TemplateLoader` nạp template động qua `?pod_tpl=...` hoặc database.

---

### Phase 2: Storefront Form-Driven Engine (Giao diện Form & Canvas Preview ngoài Storefront)
- [x] **TASK-806**: Xây dựng component Form sinh động dựa theo danh sách `fields` trong template JSON.
- [x] **TASK-807**: Cài đặt thuật toán **Auto-Shrink Text** trên Fabric.js: tự động tính tỷ lệ co nhỏ font chữ khi độ dài chuỗi vượt quá `max_width_px`.
- [x] **TASK-808**: Cài đặt hỗ trợ chữ xoay góc (`rotation: deg`) cho text layer trên Fabric.js.
- [x] **TASK-809**: Cài đặt thuật toán **Dynamic Repeater**: tự động sinh và dàn đều $N$ item (`horizontal_center_gap`) trong khung chứa khi người dùng đổi số lượng.
- [x] **TASK-810**: Cài đặt component **Preset Icon Swatches**: bấm chọn icon tức thời cập nhật ảnh SVG/PNG trên canvas.
- [x] **TASK-811**: Thêm Debounce khi gõ phím để preview mượt mà trên thiết bị di động.

---

### Phase 3: Sharp Backend 300 DPI Rendering Parity (Xử lý Render xưởng in chuẩn 100%)
- [x] **TASK-812**: Cài đặt các file font chữ `.ttf` chuẩn (*Montserrat, Dancing Script, Oswald*) vào container `pod_backend`.
- [x] **TASK-813**: Cập nhật `sharpRenderer.js` để nhận diện payload template và áp dụng chính xác thuật toán Auto-Shrink SVG text (kích thước font co giãn tương ứng).
- [x] **TASK-814**: Xử lý XML escaping cho tiếng Việt có dấu và ký tự đặc biệt (`&`, `<`, `>`, `"`, `'`).
- [x] **TASK-815**: Cập nhật `sharpRenderer.js` để render các sub-image lặp lại (Repeater) với đúng tọa độ dàn đều như trên canvas.

---

### Phase 4: E2E Verification & Schema Freeze (Kiểm thử toàn diện & Khóa Schema)
- [x] **TASK-816**: Kiểm thử E2E trên Storefront với mẫu template hợp nhất `tpl_01`.
- [x] **TASK-817**: Kiểm tra độ sắc nét và tỷ lệ 1:1 của file in 300 DPI tạo bởi backend Sharp.
- [x] **TASK-818**: Đánh giá và đóng băng cấu trúc JSON Schema chuẩn.

*(Lưu ý: Phase 5 ban đầu về Meta Box nhập thủ công được lược bỏ theo yêu cầu. Quy trình tạo và cấu hình template sẽ được tự động hóa hoàn toàn qua Spec 009: PSD Template Importer. Sau khi hoàn thành Spec 009, việc kiểm thử giao diện Front-end sẽ được thực hiện toàn diện trong Spec 010).*
