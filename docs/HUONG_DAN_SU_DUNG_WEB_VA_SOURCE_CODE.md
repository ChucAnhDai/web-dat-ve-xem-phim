# Hướng Dẫn Sử Dụng Website Và Source Code

Tài liệu này được viết dựa trên mã nguồn hiện có trong dự án `web-dat-ve-xem-phim` tại thời điểm ngày 21/03/2026. Mục tiêu là giúp bạn:

- Cài và chạy dự án từ đầu trên XAMPP.
- Sử dụng toàn bộ website phía khách hàng.
- Sử dụng khu quản trị admin.
- Hiểu cấu trúc source code để biết muốn sửa chức năng nào thì vào đúng thư mục, đúng file.
- Biết phần nào đang chạy thật, phần nào mới là giao diện mẫu hoặc preview.

## 1. Tổng quan nhanh

Đây là một dự án web đặt vé xem phim kết hợp bán sản phẩm shop. Hệ thống có 3 phần chính:

- Website khách hàng: xem phim, xem suất chiếu, chọn ghế, thanh toán vé, mua sản phẩm, giỏ hàng, checkout shop, tra cứu đơn.
- API backend: xử lý đăng nhập, dữ liệu phim, suất chiếu, ghế, giỏ hàng, thanh toán, đơn hàng, vé, admin API.
- Khu quản trị admin: quản lý phim, danh mục, hình ảnh, rạp, phòng, ghế, suất chiếu, sản phẩm, đơn hàng, thanh toán, người dùng.

Công nghệ chính đang dùng:

- PHP thuần theo kiểu MVC tự xây.
- MySQL/MariaDB.
- JavaScript thuần cho frontend.
- XAMPP để chạy Apache + PHP + MySQL.
- Composer để autoload và PHPUnit cho test.

## 2. Cài đặt dự án từ đầu trên XAMPP

### 2.1. Yêu cầu

- XAMPP đã cài sẵn.
- Apache và MySQL đang chạy.
- Thư mục dự án đặt tại:
  - `C:\xampp\htdocs\web-dat-ve-xem-phim`

### 2.2. Tạo cơ sở dữ liệu

File cấu hình database hiện tại:

- `config/database.php`

Thông số mặc định:

- Host: `127.0.0.1`
- Database: `movie_shop`
- User: `root`
- Password: để trống
- Charset: `utf8mb4`

Cách làm:

1. Mở phpMyAdmin.
2. Tạo database tên `movie_shop`.
3. Import file:
   - `database/movie_shop.sql`

Lưu ý:

- File `database/movie_shop.sql` là nguồn schema chính hiện tại.
- Thư mục `database/patches/` là lịch sử nâng cấp schema cho các bản cũ. Nếu bạn import mới từ `movie_shop.sql` thì thường không cần chạy lại patch cũ.

### 2.3. Cấu hình local

File mẫu:

- `config/local.example.php`

Bạn nên tạo file mới:

- `config/local.php`

Bằng cách copy nội dung từ `config/local.example.php`, rồi sửa các giá trị:

- `APP_URL`
- `VNPAY_TMN_CODE`
- `VNPAY_HASH_SECRET`
- `VNPAY_PAY_URL`
- `VNPAY_RETURN_URL`
- `VNPAY_IPN_URL`

Giá trị local mẫu hiện tại:

- `APP_URL = http://localhost/web-dat-ve-xem-phim`
- `VNPAY_PAY_URL = https://sandbox.vnpayment.vn/paymentv2/vpcpay.html`

Lưu ý:

- `config/autoloader.php` sẽ tự nạp `config/local.php` nếu file này tồn tại.
- Không nên hard-code khóa thanh toán vào code.

### 2.4. Tạo tài khoản mặc định

Mở PowerShell tại thư mục dự án và chạy:

```powershell
C:\xampp\php\php.exe scripts\ensure_default_admin.php
```

Tài khoản admin mặc định:

- Tên đăng nhập: `admin`
- Mật khẩu: `admin`

Tạo tài khoản member mặc định:

```powershell
C:\xampp\php\php.exe scripts\ensure_default_member.php
```

Hoặc truyền tham số riêng:

```powershell
C:\xampp\php\php.exe scripts\ensure_default_member.php member@example.com member123 "Local Member" 0900000001
```

### 2.5. Nạp dữ liệu demo phim, rạp, phòng, ghế, suất chiếu

Chạy:

```powershell
C:\xampp\php\php.exe scripts\ensure_sample_showtimes.php
```

Nếu muốn tạo thêm đơn vé demo:

```powershell
C:\xampp\php\php.exe scripts\ensure_sample_showtimes.php --with-demo-tickets
```

Script này sẽ:

- Tạo hoặc cập nhật payment methods.
- Tạo category phim.
- Tạo phim mẫu.
- Tạo rạp, phòng, sơ đồ ghế.
- Tạo suất chiếu.
- Có thể tạo đơn vé demo nếu bật cờ.

### 2.6. Truy cập website

URL chính:

- `http://localhost/web-dat-ve-xem-phim`

Khu admin:

- `http://localhost/web-dat-ve-xem-phim/admin/login`

Rewrite URL hoạt động nhờ:

- `.htaccess`
- `public/index.php`

## 3. Các tài khoản và cookie/session đang dùng

### 3.1. Người dùng thường

- Token đăng nhập khách hàng được lưu ở:
  - `localStorage` hoặc `sessionStorage`
  - Key: `cinemax_token`

### 3.2. Admin

- Admin đăng nhập trang `/admin/login` theo kiểu server-rendered.
- Token admin được lưu bằng cookie:
  - `cinemax_admin_token`
- JS admin cũng có key client-side:
  - `cinemax_admin_token`
- Nhưng luồng vào dashboard hiện tại chủ yếu dùng cookie bảo vệ route admin page.

### 3.3. Giỏ hàng shop cho khách chưa đăng nhập

- Cookie giỏ hàng:
  - `cinemax_cart`

### 3.4. Phiên giữ ghế đặt vé

- Cookie giữ session vé:
  - `cinemax_ticket_session`

## 4. Hướng dẫn sử dụng website khách hàng

## 4.1. Trang chủ

URL:

- `/`
- `/home`

Mục đích:

- Hiển thị hero phim nổi bật.
- Hiển thị thống kê tổng quan.
- Hiển thị phim đang chiếu.
- Hiển thị phim sắp chiếu.
- Hiển thị sản phẩm shop nổi bật.

API liên quan:

- `GET /api/public/home-data`

File chính:

- `views/pages/home.php`
- `public/assets/js/app.js`
- `app/Services/HomeService.php`

## 4.2. Đăng ký và đăng nhập

Trang:

- `/login`
- `/register`

Chức năng:

- Đăng nhập bằng email hoặc số điện thoại.
- Chọn ghi nhớ đăng nhập.
- Đăng ký tài khoản mới.
- Sau đăng nhập, token được lưu vào browser storage.

API:

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/profile`
- `POST /api/auth/logout`
- `POST /api/auth/update-password`

File chính:

- `views/auth/login.php`
- `views/auth/register.php`
- `views/auth/login-complete.php`
- `app/Controllers/Auth/CustomerAuthPageController.php`
- `app/Controllers/Auth/AuthController.php`
- `app/Services/AuthService.php`

Lưu ý:

- Form login/register được JavaScript chặn submit và gọi API.
- Nếu deploy thật, nên thay JWT secret trong:
  - `app/Core/Auth.php`

## 4.3. Danh sách phim

URL:

- `/movies`

Chức năng:

- Tìm kiếm phim.
- Lọc theo thể loại.
- Lọc theo điểm đánh giá.
- Sắp xếp theo phổ biến, mới nhất, rating.
- Chuyển giữa `now_showing` và `coming_soon`.

API:

- `GET /api/movies`

Nguồn dữ liệu:

- Ưu tiên dữ liệu local trong database.
- Nếu local chưa có catalog công khai, service có thể fallback sang OPhim.

File chính:

- `views/pages/movies.php`
- `public/assets/js/movie-catalog.js`
- `app/Services/MovieCatalogService.php`
- `app/Clients/OphimClient.php`

## 4.4. Chi tiết phim

URL:

- `/movie-detail?slug=...`

Chức năng:

- Xem poster, banner, mô tả, đạo diễn, diễn viên, trailer.
- Xem gallery ảnh phim.
- Xem suất chiếu của riêng phim đó.
- Xem phim liên quan.

API:

- `GET /api/movies/{slug}`

File chính:

- `views/pages/movie-detail.php`
- `public/assets/js/movie-detail.js`
- `app/Services/MovieCatalogService.php`

## 4.5. Lịch chiếu

URL:

- `/showtimes`

Chức năng:

- Tìm theo tên phim hoặc rạp.
- Lọc theo phim.
- Lọc theo rạp.
- Lọc theo thành phố.
- Lọc theo ngày chiếu.
- Đi tới chọn ghế nếu suất còn chỗ.

API:

- `GET /api/showtimes`

File chính:

- `views/pages/showtimes.php`
- `public/assets/js/showtimes.js`
- `app/Services/ShowtimeCatalogService.php`

## 4.6. Chọn ghế

URL:

- `/seat-selection?showtime_id=...&slug=...`

Chức năng:

- Tải sơ đồ ghế theo suất chiếu.
- Xem trạng thái ghế:
  - available
  - booked
  - pending payment
  - held
  - maintenance
  - disabled
  - vip
  - couple
- Chọn tối đa 6 ghế mỗi lần hold.
- Tính phụ thu ghế:
  - VIP: +15.000 VND
  - Couple: +30.000 VND
- Tạo hold ghế trước khi sang checkout.

API:

- `GET /api/showtimes/{id}/seat-map`
- `POST /api/tickets/holds`
- `DELETE /api/tickets/holds/{showtimeId}`
- `GET /api/ticket-orders/active-checkout`

Quy tắc:

- Hold ghế mặc định 5 phút theo `config/tickets.php`.
- Nếu có checkout pending chưa xong thì hệ thống có thể chặn tạo hold mới.

File chính:

- `views/pages/seat-selection.php`
- `public/assets/js/seat-selection.js`
- `app/Services/ShowtimeCatalogService.php`
- `app/Services/TicketHoldService.php`
- `app/Support/TicketSessionManager.php`

## 4.7. Checkout vé

URL:

- `/checkout?showtime_id=...&seat_ids=...`

Chức năng:

- Nhập họ tên, email, số điện thoại.
- Chọn hình thức nhận vé:
  - `e_ticket`
  - `counter_pickup`
- Chọn phương thức thanh toán:
  - `momo`
  - `vnpay`
  - `paypal`
  - `cash`
- Xem tổng tiền và thời gian giữ chỗ còn lại.
- Nếu chọn VNPay, hệ thống tạo payment intent và chuyển hướng qua gateway.
- Nếu không chọn VNPay, hệ thống tạo đơn và snapshot payment ngay trong hệ thống.

API:

- `POST /api/ticket-orders/preview`
- `POST /api/ticket-orders`
- `GET /api/ticket-orders/active-checkout`
- `POST /api/payments/ticket-intents`

File chính:

- `views/pages/checkout.php`
- `public/assets/js/checkout.js`
- `app/Services/TicketCheckoutService.php`
- `app/Services/PaymentService.php`

## 4.8. Kết quả thanh toán

URL:

- `/payment-result`

Chức năng:

- Hiển thị trạng thái thanh toán sau khi VNPay return.
- Phân biệt đơn vé và đơn shop.
- Gợi ý đường dẫn tiếp theo:
  - mở `My Tickets`
  - mở `My Orders`
  - quay lại `Movies` hoặc `Shop`

API backend liên quan:

- `GET /api/payments/vnpay/return`
- `GET /api/payments/vnpay/ipn`

File chính:

- `views/pages/payment-result.php`
- `app/Services/PaymentService.php`

Lưu ý:

- `return_url` là callback trên trình duyệt.
- `ipn_url` là callback server-to-server.
- Với `localhost`, luồng IPN thật từ VNPay sẽ không gọi vào được nếu không dùng tunnel như `ngrok`.

## 4.9. Shop

URL:

- `/shop`

Chức năng:

- Xem category sản phẩm.
- Tìm kiếm sản phẩm.
- Lọc theo giá.
- Sắp xếp theo nhiều tiêu chí.
- Hiển thị trạng thái tồn kho.

API:

- `GET /api/shop/categories`
- `GET /api/shop/products`

File chính:

- `views/pages/shop.php`
- `public/assets/js/app.js`
- `app/Services/ShopCatalogService.php`

## 4.10. Chi tiết sản phẩm

URL:

- `/shop/product-detail?slug=...`

Chức năng:

- Xem ảnh chính và gallery.
- Xem mô tả và thông tin nổi bật.
- Chọn số lượng.
- Thêm vào giỏ.
- Mua ngay.
- Copy link sản phẩm.

API:

- `GET /api/shop/products/{slug}`
- Thêm vào giỏ dùng API cart bên dưới.

File chính:

- `views/pages/product-detail.php`
- `public/assets/js/app.js`
- `app/Services/ShopCatalogService.php`

## 4.11. Giỏ hàng

URL:

- `/cart`

Chức năng:

- Xem danh sách sản phẩm trong giỏ.
- Tăng giảm số lượng.
- Xóa từng sản phẩm.
- Xóa toàn bộ giỏ.
- Tự đồng bộ lại nếu giá hoặc tồn kho đã thay đổi.
- Gộp giỏ khách vào tài khoản khi người dùng đăng nhập.

API:

- `GET /api/shop/cart`
- `POST /api/shop/cart/items`
- `PUT /api/shop/cart/items/{productId}`
- `DELETE /api/shop/cart/items/{productId}`
- `DELETE /api/shop/cart`

Quy tắc:

- TTL giỏ hàng: 7 ngày theo `config/shop.php`.
- Số dòng tối đa: 50.
- Số lượng tối đa mỗi sản phẩm: 10.

File chính:

- `views/pages/cart.php`
- `public/assets/js/shop-cart.js`
- `app/Services/ShopCartService.php`

## 4.12. Checkout shop

URL:

- `/shop/checkout`

Chức năng:

- Lấy snapshot giỏ hàng trước khi tạo đơn.
- Nhập thông tin liên hệ.
- Chọn hình thức nhận hàng:
  - `pickup`
  - `delivery`
- Nếu chọn `delivery`, phải nhập địa chỉ, thành phố, quận/huyện.
- Chọn phương thức thanh toán phù hợp với fulfillment:
  - `pickup`: `cash` hoặc `vnpay`
  - `delivery`: `vnpay`
- Tạo đơn hàng.
- Nếu là khách, sau checkout hệ thống có thể xóa session cart cookie.

API:

- `GET /api/shop/checkout`
- `POST /api/shop/checkout`

Quy tắc:

- Đơn pending shop mặc định hết hạn sau 5 phút.
- Tồn kho được trừ/reserve trong transaction checkout.
- Checkout dùng idempotency key để tránh tạo trùng.

File chính:

- `views/pages/shop-checkout.php`
- `public/assets/js/shop-checkout.js`
- `app/Services/ShopCheckoutService.php`
- `app/Services/ShopOrderLifecycleService.php`

## 4.13. Đơn hàng của tôi

URL:

- `/my-orders`

Chức năng:

- Xem đơn shop của tài khoản đã đăng nhập.
- Lọc theo trạng thái.
- Mở chi tiết từng đơn.
- Hủy đơn nếu còn ở trạng thái cho phép.
- Tra cứu đơn guest bằng:
  - mã đơn
  - email checkout
  - số điện thoại checkout

API:

- `GET /api/me/shop-orders`
- `GET /api/me/shop-orders/{id}`
- `POST /api/me/shop-orders/{id}/cancel`
- `POST /api/shop/orders/lookup`
- `POST /api/shop/orders/lookup/cancel`

Lưu ý:

- UI hiện tại ưu tiên tra cứu guest bằng tay để an toàn.
- Không nên kỳ vọng guest order tự hiện ra chỉ bằng session trình duyệt.

File chính:

- `views/pages/my-orders.php`
- `public/assets/js/my-orders.js`
- `app/Services/UserShopOrderService.php`

## 4.14. Vé của tôi

URL:

- `/my-tickets`

Chức năng:

- Xem lịch sử vé đã mua.
- Xem mã vé, mã QR payload, ghế, suất chiếu, rạp, trạng thái.
- Lọc theo paid, pending, used, issue.

API:

- `GET /api/me/tickets`
- `GET /api/me/ticket-orders`

File chính:

- `views/pages/my-tickets.php`
- `public/assets/js/my-tickets.js`
- `app/Services/UserTicketService.php`

## 4.15. Hồ sơ cá nhân

URL:

- `/profile`

Chức năng:

- Xem thông tin tài khoản.
- Xem thống kê số vé, đơn hàng, tổng chi tiêu.
- Đổi mật khẩu.
- Xem lịch sử đơn gần đây.

API:

- `GET /api/auth/profile`
- `POST /api/auth/update-password`

Lưu ý:

- Tính năng cập nhật thông tin hồ sơ đang mới ở mức giao diện, chưa nối backend đầy đủ.

File chính:

- `views/pages/profile.php`
- `public/assets/js/app.js`

## 5. Hướng dẫn sử dụng khu admin

## 5.1. Đăng nhập admin

URL:

- `/admin/login`

Tài khoản local mặc định:

- `admin / admin`

Luồng:

- Form POST trực tiếp về backend.
- Backend set cookie `cinemax_admin_token`.
- `AdminPageMiddleware` bảo vệ toàn bộ route admin page.

File chính:

- `views/admin/auth/login.php`
- `app/Controllers/Admin/AdminAuthController.php`
- `app/Middlewares/AdminPageMiddleware.php`

## 5.2. Dashboard

URL:

- `/admin`
- `/admin/dashboard`

Dữ liệu đang chạy thật:

- thống kê phim
- thống kê người dùng
- vé bán hôm nay
- doanh thu shop
- đơn pending
- top movie
- low stock
- recent ticket orders
- recent shop orders

API:

- `GET /api/admin/dashboard/stats`

File chính:

- `views/admin/pages/dashboard/sections/dashboard.php`
- `app/Repositories/DashboardRepository.php`

## 5.3. Movie Management

URL:

- `/admin/movies?section=movies`
- `/admin/movies?section=categories`
- `/admin/movies?section=movie-images`
- `/admin/movies?section=reviews`

Phần chạy thật:

- quản lý movie
- quản lý category phim
- quản lý asset ảnh phim
- import phim từ OPhim

API live:

- `GET/POST/PUT/DELETE /api/admin/movies`
- `POST /api/admin/movies/import-ophim`
- `POST /api/admin/movies/import-ophim-list`
- `GET/POST/PUT/DELETE /api/admin/movie-categories`
- `GET/POST/PUT/DELETE /api/admin/movie-assets`

Phần chưa nối persistence hoàn chỉnh:

- `reviews` hiện là màn hình preview/mock theo dữ liệu cứng trong view.

File chính:

- `public/assets/admin/movie-management-movies.js`
- `public/assets/admin/movie-management-categories.js`
- `public/assets/admin/movie-management-movie-images.js`
- `views/admin/pages/movies/sections/reviews.php`
- `app/Services/MovieManagementService.php`

## 5.4. Cinema Management

URL:

- `/admin/cinemas?section=cinemas`
- `/admin/cinemas?section=rooms`
- `/admin/seats`
- `/admin/showtimes`

Phần chạy thật:

- quản lý rạp
- quản lý phòng
- chỉnh sơ đồ ghế theo phòng
- quản lý suất chiếu

API live:

- `GET/POST/PUT/DELETE /api/admin/cinemas`
- `GET/POST/PUT/DELETE /api/admin/rooms`
- `GET /api/admin/rooms/{id}/seats`
- `PUT /api/admin/rooms/{id}/seats`
- `GET/POST/PUT/DELETE /api/admin/showtimes`

Quy tắc đáng chú ý:

- Lưu sơ đồ ghế là replace toàn bộ layout trong 1 transaction.
- Không cho sửa layout nếu phòng có suất chiếu published tương lai hoặc đã có vé/đơn ảnh hưởng.
- `end_time` của suất chiếu được tính ở backend từ thời lượng phim + buffer dọn phòng.

File chính:

- `public/assets/admin/cinema-management-cinemas.js`
- `public/assets/admin/cinema-management-rooms.js`
- `public/assets/admin/cinema-management-seats.js`
- `public/assets/admin/showtime-management.js`
- `app/Services/CinemaManagementService.php`
- `app/Services/ShowtimeManagementService.php`

## 5.5. Ticket System

URL:

- `/admin/ticket-orders?section=ticket-orders`
- `/admin/ticket-orders?section=ticket-details`

Phần chạy thật:

- xem đơn vé
- xem chi tiết vé
- xem hold ghế đang active

API live:

- `GET /api/admin/ticket-orders`
- `GET /api/admin/ticket-orders/{id}`
- `GET /api/admin/ticket-details`
- `GET /api/admin/ticket-details/{id}`
- `GET /api/admin/ticket-holds`

File chính:

- `public/assets/admin/ticket-orders-preview.js`
- `public/assets/admin/ticket-details-preview.js`
- `app/Services/AdminTicketManagementService.php`

## 5.6. Shop Management

URL:

- `/admin/products?section=products`
- `/admin/products?section=product-categories`
- `/admin/products?section=product-images`

Phần chạy thật:

- quản lý category sản phẩm
- quản lý sản phẩm
- quản lý ảnh sản phẩm
- upload nhiều ảnh sản phẩm

API live:

- `GET/POST/PUT/DELETE /api/admin/product-categories`
- `GET/POST/PUT/DELETE /api/admin/products`
- `GET/POST/PUT/DELETE /api/admin/product-images`
- `POST /api/admin/product-images/bulk`

File chính:

- `public/assets/admin/product-management-categories.js`
- `public/assets/admin/product-management-products.js`
- `public/assets/admin/product-management-images.js`
- `app/Services/ProductManagementService.php`
- `app/Services/ProductImageUploadService.php`

## 5.7. Shop Orders

URL:

- `/admin/shop-orders?section=shop-orders`
- `/admin/shop-orders?section=order-details`

Phần chạy thật:

- xem danh sách đơn shop
- xem order details
- cập nhật trạng thái đơn

API live:

- `GET /api/admin/shop-orders`
- `GET /api/admin/shop-orders/{id}`
- `PUT /api/admin/shop-orders/{id}/status`
- `GET /api/admin/order-details`

File chính:

- `public/assets/admin/shop-orders-admin.js`
- `app/Services/AdminShopOrderManagementService.php`

## 5.8. Payments

URL:

- `/admin/payments?section=payments`
- `/admin/payments?section=payment-methods`

Phần chạy thật:

- xem lịch sử payment
- xem chi tiết payment
- quản lý payment methods
- bật/tắt hoặc chuyển trạng thái method

API live:

- `GET /api/admin/payments`
- `GET /api/admin/payments/{id}`
- `GET /api/admin/payment-methods`
- `GET /api/admin/payment-methods/{id}`
- `POST /api/admin/payment-methods`
- `PUT /api/admin/payment-methods/{id}`
- `DELETE /api/admin/payment-methods/{id}`

File chính:

- `public/assets/admin/payments-admin.js`
- `app/Services/AdminPaymentManagementService.php`

## 5.9. User Management

URL:

- `/admin/users?section=users`
- `/admin/users?section=user-addresses`
- `/admin/users?section=roles`

Phần chạy thật:

- quản lý user
- quản lý địa chỉ user
- quản lý role

API live:

- `GET/POST/PUT/DELETE /api/admin/users`
- `GET /api/admin/users/stats`
- `GET/POST/PUT/DELETE /api/admin/addresses`
- `GET /api/admin/addresses/stats`
- `GET/POST/PUT/DELETE /api/admin/roles`

File chính:

- `views/admin/pages/users/sections/users.php`
- `views/admin/pages/users/sections/user-addresses.php`
- `views/admin/pages/users/sections/roles.php`
- `app/Services/Admin/AdminUserService.php`
- `app/Services/Admin/AdminAddressService.php`
- `app/Services/Admin/AdminRoleService.php`

## 5.10. Những màn admin hiện là preview/mock

Các khu sau hiện chủ yếu là giao diện mẫu hoặc preview, chưa nối persistence backend đầy đủ:

- Dashboard section `banners`
- Dashboard section `notifications`
- Dashboard section `system-settings`
- Dashboard section `admin-profile`
- Promotions
- Product Promotions
- Movie Reviews
- Trang `/admin/test`

File liên quan:

- `views/admin/pages/dashboard/sections/banners.php`
- `views/admin/pages/dashboard/sections/notifications.php`
- `views/admin/pages/dashboard/sections/system-settings.php`
- `views/admin/pages/dashboard/sections/admin-profile.php`
- `views/admin/pages/promotions/sections/promotions.php`
- `views/admin/pages/promotions/sections/product-promotions.php`
- `views/admin/pages/movies/sections/reviews.php`
- `views/admin/pages/test.php`

## 6. Luồng request trong source code

Luồng chạy cơ bản:

1. Apache nhận request.
2. `.htaccess` rewrite tất cả route về `public/index.php`.
3. `public/index.php` khởi tạo `Application`, load route API và route web.
4. `Router` match URL + HTTP method.
5. Middleware chạy nếu route có bảo vệ.
6. Controller nhận request.
7. Controller gọi Service.
8. Service gọi Repository để đọc/ghi DB.
9. `Response` trả JSON hoặc render view.

Các file lõi:

- `public/index.php`
- `config/routes.php`
- `app/Core/Application.php`
- `app/Core/Router.php`
- `app/Core/Request.php`
- `app/Core/Response.php`

## 7. Vai trò từng thư mục trong dự án

## 7.1. Thư mục gốc

| Thư mục/file | Vai trò |
| --- | --- |
| `app/` | Toàn bộ logic ứng dụng: controller, service, repository, validator, middleware, support. |
| `config/` | Cấu hình database, routes, autoload, shop, ticket, payment. |
| `database/` | SQL schema chính và patch nâng cấp DB. |
| `docs/` | Tài liệu kỹ thuật, setup VNPay, shop, cinema, và tài liệu hướng dẫn này. |
| `public/` | Front controller, CSS, JS, admin assets, uploads public. |
| `scripts/` | Script hỗ trợ local/dev: tạo tài khoản, seed data, cleanup demo. |
| `storage/` | Log runtime chính, nhất là `storage/logs/app.log`. |
| `template/` | HTML mẫu hoặc reference UI, không phải luồng runtime chính. |
| `tests/` | Bộ test Unit, Integration, Feature. |
| `tmp/` | File tạm, fallback log, script test tạm. |
| `vendor/` | Dependency do Composer quản lý, không sửa tay. |
| `views/` | Giao diện PHP render phía server cho client và admin. |
| `.htaccess` | Rewrite URL về `public/index.php`. |
| `composer.json` | Cấu hình autoload và dependency dev. |
| `phpunit.xml` | Cấu hình chạy test. |

## 7.2. Bên trong `app/`

| Thư mục | Vai trò |
| --- | --- |
| `app/Clients/` | Client gọi hệ thống ngoài, hiện có `OphimClient.php`. |
| `app/Controllers/` | Controller xử lý request. Chia thành web, auth, api, admin. |
| `app/Core/` | Hạ tầng lõi: app, router, request, response, database, auth, logger, validator. |
| `app/Middlewares/` | Middleware xác thực user/admin và bảo vệ admin page. |
| `app/Models/` | Model đơn giản, hiện dùng ít. |
| `app/Repositories/` | Truy vấn DB theo từng bảng/ngữ cảnh nghiệp vụ. |
| `app/Services/` | Xử lý business logic chính. |
| `app/Support/` | Helper nghiệp vụ: session vé, slug, asset URL, VNPay gateway. |
| `app/Validators/` | Chuẩn hóa input và validate rule theo module. |
| `app/Services/Admin/` | Service cho quản trị người dùng, role, địa chỉ. |
| `app/Repositories/Concerns/` | Reuse một số logic paginate query. |
| `app/Services/Concerns/` | Reuse logic format dữ liệu vé và đơn hàng shop. |

Lưu ý:

- Các thư mục sau hiện đang tồn tại nhưng trống:
  - `app/Controllers/Cinema`
  - `app/Controllers/Movie`
  - `app/Controllers/Order`
  - `app/Controllers/Payment`
  - `app/Controllers/Shop`
  - `app/Controllers/System`

## 7.3. Bên trong `views/`

| Thư mục | Vai trò |
| --- | --- |
| `views/layouts/` | Layout chính cho website khách. |
| `views/partials/` | Header, sidebar, footer của website khách. |
| `views/pages/` | Từng màn hình client: home, movies, movie-detail, showtimes, cart, checkout, profile... |
| `views/auth/` | Trang login/register khách hàng. |
| `views/admin/layouts/` | Layout admin. |
| `views/admin/partials/` | Header, sidebar, footer admin. |
| `views/admin/pages/` | Từng trang/section admin. |
| `views/admin/auth/` | Trang login admin. |

## 7.4. Bên trong `public/`

| Thư mục | Vai trò |
| --- | --- |
| `public/index.php` | Front controller của toàn bộ hệ thống. |
| `public/assets/css/` | CSS website khách. |
| `public/assets/js/` | JS website khách theo từng màn hình/chức năng. |
| `public/assets/admin/` | JS và CSS admin. |
| `public/uploads/products/` | Ảnh sản phẩm upload public. |

## 7.5. Bên trong `config/`

| File | Vai trò |
| --- | --- |
| `config/autoloader.php` | Nạp timezone, `local.php`, và autoload `App\`. |
| `config/database.php` | Kết nối MySQL. |
| `config/routes.php` | Tất cả API route. |
| `config/shop.php` | Rule shop, cart, upload, checkout, promotion. |
| `config/tickets.php` | Rule giữ ghế và pending ticket checkout. |
| `config/payments.php` | Cấu hình app URL và VNPay. |
| `config/local.example.php` | Mẫu config local. |
| `config/local.php` | File local thực tế, tự tạo khi chạy local. |

## 7.6. Bên trong `database/`

| Thư mục/file | Vai trò |
| --- | --- |
| `database/movie_shop.sql` | Schema và dữ liệu cấu trúc chính hiện tại. |
| `database/patches/` | Các file patch nâng cấp từng giai đoạn. |

## 7.7. Bên trong `scripts/`

| File | Vai trò |
| --- | --- |
| `scripts/ensure_default_admin.php` | Tạo hoặc cập nhật admin mặc định `admin/admin`. |
| `scripts/ensure_default_member.php` | Tạo hoặc cập nhật member mặc định. |
| `scripts/ensure_sample_showtimes.php` | Seed dữ liệu demo phim, rạp, phòng, ghế, suất chiếu, payment method, có thể cả đơn vé demo. |
| `scripts/cleanup_legacy_cinema_demo.php` | Dọn dữ liệu demo rạp cũ. |
| `scripts/cleanup_demo_tickets.php` | Dọn đơn vé demo. |

## 7.8. Bên trong `tests/`

Hiện có:

- `28` file Unit test
- `19` file Integration test
- `22` file Feature test

Ý nghĩa:

- `tests/Unit/`: test validator, helper, config, service nhỏ.
- `tests/Integration/`: test DB/service theo module.
- `tests/Feature/`: test API/controller theo endpoint.

## 8. Bản đồ sửa code nhanh theo nhu cầu

### 8.1. Muốn sửa giao diện website khách

Vào:

- `views/pages/`
- `views/partials/`
- `views/layouts/main.php`
- `public/assets/css/app.css`
- `public/assets/js/`

Ví dụ:

- sửa header khách: `views/partials/header.php`
- sửa sidebar khách: `views/partials/sidebar.php`
- sửa CSS tổng thể: `public/assets/css/app.css`
- sửa logic trang giỏ hàng: `public/assets/js/shop-cart.js`

### 8.2. Muốn sửa giao diện admin

Vào:

- `views/admin/pages/`
- `views/admin/partials/`
- `views/admin/layouts/`
- `public/assets/admin/`

Ví dụ:

- sửa sidebar admin: `views/admin/partials/sidebar.php`
- sửa dashboard admin: `views/admin/pages/dashboard/sections/dashboard.php`
- sửa JS payment admin: `public/assets/admin/payments-admin.js`

### 8.3. Muốn thêm route page mới

Sửa:

- `public/index.php`
- controller tương ứng trong `app/Controllers/`
- view tương ứng trong `views/`

### 8.4. Muốn thêm API mới

Sửa:

- `config/routes.php`
- controller API trong `app/Controllers/Api/` hoặc `app/Controllers/Admin/`
- service tương ứng
- repository tương ứng nếu có DB

### 8.5. Muốn đổi rule nghiệp vụ

Sửa ở service hoặc config:

- rule giỏ hàng/shop: `config/shop.php`, `app/Services/ShopCartService.php`, `app/Services/ShopCheckoutService.php`
- rule vé/hold: `config/tickets.php`, `app/Services/TicketHoldService.php`, `app/Services/TicketCheckoutService.php`
- rule thanh toán: `config/payments.php`, `app/Services/PaymentService.php`

### 8.6. Muốn đổi validate input

Sửa:

- `app/Validators/`

Ví dụ:

- validate checkout shop: `app/Validators/ShopCheckoutValidator.php`
- validate hold ghế: `app/Validators/TicketHoldValidator.php`
- validate quản lý phim: `app/Validators/MovieManagementValidator.php`

### 8.7. Muốn đổi query DB

Sửa:

- `app/Repositories/`

Nguyên tắc:

- Repository lo truy vấn.
- Service lo business rule.
- Controller chỉ nhận request và trả response.

## 9. Các bảng dữ liệu chính trong database

Nhóm người dùng:

- `users`
- `addresses`
- `user_roles`

Nhóm phim:

- `movie_categories`
- `movies`
- `movie_category_assignments`
- `movie_images`
- `movie_reviews`

Nhóm rạp và lịch chiếu:

- `cinemas`
- `rooms`
- `seats`
- `showtimes`

Nhóm vé:

- `ticket_orders`
- `ticket_details`
- `ticket_seat_holds`

Nhóm shop:

- `product_categories`
- `products`
- `product_images`
- `product_details`
- `carts`
- `cart_items`
- `shop_orders`
- `order_details`

Nhóm thanh toán:

- `payment_methods`
- `payments`

Nhóm nội dung/marketing:

- `banners`
- `promotions`
- `product_promotions`
- `notifications`

## 10. Các service quan trọng nhất bạn nên biết

| Service | Vai trò |
| --- | --- |
| `AuthService` | Đăng ký, đăng nhập, profile, đổi mật khẩu. |
| `HomeService` | Dữ liệu trang chủ. |
| `MovieCatalogService` | Catalog phim công khai, chi tiết phim, fallback OPhim. |
| `MovieManagementService` | CRUD phim, category, image, import OPhim. |
| `ShowtimeCatalogService` | Lấy lịch chiếu công khai, seat map. |
| `ShowtimeManagementService` | CRUD suất chiếu admin. |
| `CinemaManagementService` | CRUD rạp, phòng, ghế. |
| `TicketHoldService` | Hold và release ghế. |
| `TicketCheckoutService` | Preview checkout vé và tạo đơn vé non-VNPay. |
| `PaymentService` | Tạo VNPay intent và xử lý return/IPN cho cả vé và shop. |
| `ShopCatalogService` | Catalog sản phẩm công khai. |
| `ShopCartService` | Giỏ hàng, merge giỏ, đồng bộ tồn kho/giá. |
| `ShopCheckoutService` | Snapshot cart, tạo đơn shop, reserve stock, tạo payment. |
| `UserTicketService` | Vé và đơn vé của user. |
| `UserShopOrderService` | Đơn shop của user/guest lookup/cancel. |
| `AdminPaymentManagementService` | Admin payment history và payment methods. |
| `AdminShopOrderManagementService` | Admin quản lý đơn shop. |
| `AdminTicketManagementService` | Admin quản lý đơn vé và ticket details. |

## 11. Chạy script và test

### 11.1. Chạy test

Nếu `vendor/` đã có sẵn:

```powershell
C:\xampp\php\php.exe vendor\bin\phpunit
```

Nếu cần cài dependency dev trước:

```powershell
composer install
```

### 11.2. Các script hữu ích

```powershell
C:\xampp\php\php.exe scripts\ensure_default_admin.php
C:\xampp\php\php.exe scripts\ensure_default_member.php
C:\xampp\php\php.exe scripts\ensure_sample_showtimes.php
C:\xampp\php\php.exe scripts\ensure_sample_showtimes.php --with-demo-tickets
C:\xampp\php\php.exe scripts\cleanup_legacy_cinema_demo.php
C:\xampp\php\php.exe scripts\cleanup_demo_tickets.php
```

## 12. Những lưu ý quan trọng khi bàn giao hoặc phát triển tiếp

### 12.1. Phần đang chạy thật

Các khối đã có backend + DB + API khá đầy đủ:

- auth
- home data
- movie catalog
- movie management
- cinema management
- seat management
- showtime management
- ticket hold
- ticket checkout
- VNPay payment flow
- shop catalog
- shop cart
- shop checkout
- user tickets
- user shop orders
- admin payments
- admin ticket/shop orders
- admin users/addresses/roles

### 12.2. Phần đang là preview/mock hoặc chưa nối persistence đầy đủ

- banners
- notifications
- system settings
- admin profile
- promotions
- product promotions
- movie reviews admin section
- một số nút phụ như watchlist, đổi avatar, sửa hồ sơ

### 12.3. Encoding

Trong source hiện có một số file cũ bị lỗi hiển thị tiếng Việt do mã hóa không đồng nhất. Khi sửa file, nên thống nhất lưu bằng:

- UTF-8

### 12.4. Bảo mật cần lưu ý nếu đưa lên production

- JWT secret trong `app/Core/Auth.php` đang là:
  - `change_me_secret_key`
- Cần đổi secret trước khi deploy thật.
- Nên tách secret ra biến môi trường hoặc `config/local.php`.

### 12.5. Không nên sửa tay

- `vendor/`
- file upload thật trong `public/uploads/` nếu không cần
- dữ liệu demo đang dùng cho test/manual demo nếu chưa backup

### 12.6. Log

Log ghi chính vào:

- `storage/logs/app.log`

Fallback log:

- `tmp/logs/app.log`

## 13. Gợi ý tìm file theo nhu cầu cực nhanh

Muốn làm gì, vào đâu:

- Sửa route API: `config/routes.php`
- Sửa route page: `public/index.php`
- Sửa login/register user: `views/auth/`, `app/Services/AuthService.php`
- Sửa admin login: `views/admin/auth/login.php`, `app/Controllers/Admin/AdminAuthController.php`
- Sửa trang chủ: `views/pages/home.php`, `app/Services/HomeService.php`
- Sửa trang phim: `views/pages/movies.php`, `public/assets/js/movie-catalog.js`
- Sửa chi tiết phim: `views/pages/movie-detail.php`, `public/assets/js/movie-detail.js`
- Sửa lịch chiếu: `views/pages/showtimes.php`, `public/assets/js/showtimes.js`
- Sửa chọn ghế: `views/pages/seat-selection.php`, `public/assets/js/seat-selection.js`
- Sửa checkout vé: `views/pages/checkout.php`, `public/assets/js/checkout.js`
- Sửa VNPay: `app/Services/PaymentService.php`, `app/Support/VnpayGateway.php`, `config/payments.php`
- Sửa shop catalog: `app/Services/ShopCatalogService.php`, `views/pages/shop.php`
- Sửa giỏ hàng: `app/Services/ShopCartService.php`, `public/assets/js/shop-cart.js`
- Sửa checkout shop: `app/Services/ShopCheckoutService.php`, `public/assets/js/shop-checkout.js`
- Sửa đơn shop của user: `app/Services/UserShopOrderService.php`, `public/assets/js/my-orders.js`
- Sửa vé của user: `app/Services/UserTicketService.php`, `public/assets/js/my-tickets.js`
- Sửa admin phim: `public/assets/admin/movie-management-*.js`
- Sửa admin rạp/phòng/ghế/suất chiếu: `public/assets/admin/cinema-management-*.js`, `public/assets/admin/showtime-management.js`
- Sửa admin payment: `public/assets/admin/payments-admin.js`
- Sửa admin sản phẩm: `public/assets/admin/product-management-*.js`
- Sửa admin shop orders: `public/assets/admin/shop-orders-admin.js`

## 14. Kết luận

Nếu bạn cần hiểu dự án theo kiểu “web dùng ra sao”, hãy đọc mục 4 và 5 trước. Nếu bạn cần sửa code, hãy đọc mục 6 đến 13 trước. Nếu bạn đang cài lại máy hoặc bàn giao cho người khác, hãy làm đúng thứ tự:

1. tạo DB
2. import `database/movie_shop.sql`
3. tạo `config/local.php`
4. tạo tài khoản mặc định
5. seed dữ liệu demo nếu cần
6. mở web và admin để kiểm tra

Tài liệu này nên được xem là bản hướng dẫn tổng hợp chính cho dự án ở thời điểm hiện tại.
