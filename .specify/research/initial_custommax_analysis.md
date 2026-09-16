# Nghiên cứu Ban Đầu: Phân Tích Mô Hình CustomMax & Kiến Trúc POD cho WordPress

Tài liệu này lưu trữ nội dung trao đổi và phân tích ban đầu về việc xây dựng giải pháp Personalization / Print on Demand (POD) tương tự CustomMax cho WordPress / WooCommerce.

---

## 1. Bản chất của CustomMax
CustomMax là ứng dụng POD trên Shopify chuyên về:
- **Live Preview & Personalization**: Cho phép khách hàng nhập chữ, đổi clipart (tóc, màu áo, phụ kiện...), tải ảnh cá nhân lên và xem trực tiếp (Live Preview) ngay trên trang sản phẩm.
- **Custom Fields / Product Options**: Bổ sung options/variants vượt giới hạn mặc định.
- **Render Print File & Fulfillment**: Tự động sinh file in độ phân giải cao (300 DPI) để đẩy qua xưởng in/fulfillment.

---

## 2. Vì sao cần Backend Worker riêng biệt?
- Ảnh hiển thị trên web chỉ là 72 DPI (nhẹ, nhanh).
- File in ấn thực tế gửi xưởng phải là **300 DPI**, kích thước thực tế lớn (vài nghìn pixel).
- Nếu để máy chủ WordPress (PHP / GD / Imagick) gánh việc merge các layer ảnh 300 DPI khi có nhiều đơn hàng cùng lúc, máy chủ web sẽ cạn kiệt CPU/RAM và sập web bán hàng.
- **Giải pháp**: Tách một Microservice Node.js riêng biệt sử dụng thư viện đồ họa tốc độ cao (`sharp` / C++ bindings) để nhận payload JSON từ WordPress, xếp hàng đợi và render file in 300 DPI ra storage.

---

## 3. Kiến trúc Phân tầng đã thống nhất
1. **Storefront (WooCommerce)**: Nhẹ, chạy Fabric.js/Konva preview, thu thập JSON state.
2. **Core Plugin (`pod-customizer`)**: Lưu trữ JSON state vào Order Item Meta, bắn Webhook sang Backend khi đơn chuyển trạng thái `processing`.
3. **Backend Worker (`pod-backend.localhost`)**: Nhận Webhook, chạy Sharp ghép layer 300 DPI, gọi Callback API cập nhật link file in về đơn hàng WooCommerce.
