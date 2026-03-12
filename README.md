# web-dat-ve-xem-phim

## Ghi chú những gì đã làm

### Frontend
- Tách `header`, `sidebar`, `footer` ra các file riêng: `header.html`, `sidebar.html`, `footer.html`.
- Thay thế các vùng trong `index.html` bằng các mount: `#headerMount`, `#sidebarMount`, `#footerMount`.
- Thêm cơ chế load partials bằng `fetch()` và khởi tạo lại sidebar sau khi load.
- Sửa cấu trúc footer để tránh bị dính vào phần nội dung chính (footer được inject đúng vào vùng footer).

### Backend (Module Authentication nền tảng)
- Bổ sung `bearerToken()` vào `app/Core/Request.php` để đọc JWT từ header.
- Thêm các core utilities:
  - `app/Core/Validator.php` (validate cơ bản)
  - `app/Core/Logger.php` (ghi log vào `storage/logs/app.log`)
  - `app/Core/Auth.php` (JWT đơn giản)
- Thêm model & repository:
  - `app/Models/User.php`
  - `app/Repositories/UserRepository.php` (find/create)
- Thêm service/controller:
  - `app/Services/AuthService.php` (register/login/profile)
  - `app/Controllers/Auth/AuthController.php`
- Cập nhật front controller `public/index.php` để khởi tạo `Application` trước khi load routes.
- Bổ sung routes Auth trong `config/routes.php`.

## Kế hoạch bước tiếp theo (chỉ lập kế hoạch)

### 1. Hoàn thiện Authentication & User
- Thêm middleware xác thực JWT và phân quyền (RBAC).
- Chuẩn hóa response/error handling dùng chung.
- Viết unit/integration tests cho Auth.

### 2. Cinema Management
- Thiết kế models/repositories/services cho `cinemas`, `rooms`, `seats`.
- CRUD APIs và validate dữ liệu.
- Logging + tests.

### 3. Movie & Schedule
- Xây dựng module phim, thể loại, lịch chiếu.
- APIs cho danh sách, chi tiết, lọc theo trạng thái.
- Tối ưu query (index, pagination).

### 4. Booking & Ticketing
- Thiết kế flow giữ ghế, đặt vé, thanh toán vé.
- Transaction đảm bảo tính nhất quán (seat lock, rollback).
- Ghi log và alert khi lỗi giao dịch.

### 5. Shop & Orders
- Module sản phẩm, giỏ hàng, đơn hàng.
- Tính toán giá/khuyến mãi, kiểm tồn kho.
- Transaction đảm bảo đơn hàng đúng và nhất quán.

### 6. Payment
- Tích hợp payment gateway, xử lý callback.
- Mapping payment vào ticket/shop order.
- Logging & retry strategy.

### 7. Observability & Ops
- Logging chuẩn hóa, metrics, tracing.
- Health check endpoints.
- Performance optimization và cache.

### 8. Testing & Security Hardening
- Test coverage các module chính.
- Kiểm tra OWASP top 10 (sanitize, CSRF, rate limit...).
- Kiểm tra XSS/SQLi và hardening cấu hình.
