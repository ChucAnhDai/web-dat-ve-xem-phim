# Movie Management Summary

## 1. Muc tieu da thuc hien

Da chuan hoa va trien khai module `Movie Management` theo huong backend truoc, admin UI noi API sau:

- Dong bo schema SQL voi admin UI va user UI
- Chuan hoa mo hinh du lieu movie/category/asset/review
- Trien khai backend `Phase 1` va `Phase 2` cho admin API
- Trien khai mot phan lon `Phase 3` cho admin UI:
  - `Movies`
  - `Categories`
  - `Movie Images`
- Bo sung chuc nang import/sync movie tu OPhim vao admin local DB
- Bo sung chuc nang batch sync movie list tu OPhim vao admin local DB
- Bo sung admin auth/login/logout de bao ve admin pages va admin API
- Bo sung validation, transaction, logging, error handling, test artifacts
- Noi public user movie pages vao OPhim-backed API de user doc catalog/detail that

## 2. Schema du lieu da chuan hoa

### 2.1 Bang `movie_categories`

Da bo sung:

- `slug`
- `display_order`
- `is_active`
- `created_at`
- `updated_at`

Y nghia:

- Quan ly category theo slug on dinh
- Ho tro sap xep hien thi
- Ho tro bat/tat category ma khong can xoa data

### 2.2 Bang `movies`

Da chuan hoa cac cot:

- `primary_category_id`
- `slug`
- `title`
- `summary`
- `duration_minutes`
- `release_date`
- `poster_url`
- `trailer_url`
- `age_rating`
- `language`
- `director`
- `writer`
- `cast_text`
- `studio`
- `average_rating`
- `review_count`
- `status`
- `created_at`
- `updated_at`

Trang thai movie duoc chuan hoa:

- `draft`
- `coming_soon`
- `now_showing`
- `ended`
- `archived`

### 2.3 Bang `movie_category_assignments`

Da them bang trung gian:

- Quan ly movie co nhieu category
- `movies.primary_category_id` la nguon chuan cho category chinh
- Bang assignment dung cho category phu

### 2.4 Bang `movie_images`

Da chuan hoa:

- `asset_type`
- `image_url`
- `alt_text`
- `sort_order`
- `is_primary`
- `status`
- `created_at`
- `updated_at`

Loai asset:

- `poster`
- `banner`
- `gallery`

Trang thai asset:

- `draft`
- `active`
- `archived`

### 2.5 Bang `movie_reviews`

Da bo sung metadata moderation:

- `status`
- `is_visible`
- `moderation_note`
- `updated_at`

Trang thai review:

- `pending`
- `approved`
- `rejected`

## 3. Dong bo admin UI va user UI

### 3.1 Admin Movie Management

Da cap nhat cac section:

- `views/admin/pages/movies/sections/movies.php`
- `views/admin/pages/movies/sections/categories.php`
- `views/admin/pages/movies/sections/movie-images.php`
- `views/admin/pages/movies/sections/reviews.php`

Noi dung da dong bo:

- Ten field theo schema moi
- Status vocabulary theo schema moi
- Form movie/category/asset/review khong con dung field demo khong co cho luu that
- Filter va list du lieu dung chung mot ngon ngu du lieu

Trang thai ket noi:

- `Movies`: da noi backend that voi file `public/assets/admin/movie-management-movies.js`
- `Movies`: da co them chuc nang `Import OPhim` vao local DB qua route `/api/admin/movies/import-ophim`
- `Movies`: da co them `Batch Sync OPhim` qua route `/api/admin/movies/import-ophim-list`
- `Categories`: da noi backend that voi file `public/assets/admin/movie-management-categories.js`
- `Movie Images`: da noi backend that voi file `public/assets/admin/movie-management-movie-images.js`
- `Reviews`: moi o muc schema-aligned preview, chua noi backend that

### 3.2 Shared admin helpers

Da cap nhat:

- `public/assets/admin/shared.js`
- `public/assets/admin/shared.css`

Noi dung:

- Chuan hoa status badge
- Chuan hoa `buildOptions`
- Them helper `slugifyValue`
- Them helper `formatMovieDuration`
- Them helper goi admin API va parse loi theo contract JSON
- Ho tro modal async, apply form errors, export UI states
- Cap nhat form movie theo schema moi

### 3.3 User UI

Da cap nhat:

- `public/assets/js/app.js`
- `public/assets/js/movie-catalog.js`
- `public/assets/js/movie-detail.js`
- `views/pages/home.php`
- `views/pages/movies.php`
- `views/pages/movie-detail.php`

Noi dung:

- Trang `/movies` va `/movie-detail` da doc du lieu that qua OPhim-backed public API
- Local endpoints cong khai giu on dinh contract FE:
  - `GET /api/movies`
  - `GET /api/movies/{slug}`
- Catalog user map status theo OPhim:
  - `phim-chieu-rap` -> `now_showing`
  - `phim-sap-chieu` -> `coming_soon`
- Movie detail user render gallery, trailer, playback groups tu du lieu OPhim

## 4. Backend Phase 1 da trien khai

### 4.1 Nang cap ha tang request/router

Da cap nhat:

- `app/Core/Request.php`
- `app/Core/Router.php`

Noi dung:

- Ho tro route param dang `/resource/{id}`
- Ho tro method `PUT`
- Ho tro method `DELETE`
- Ho tro `_method` override qua form POST
- Ho tro lay `route param`
- Chuan hoa doc body cho `GET`, `POST`, `JSON`
- Sanitize input nhat quan hon trong web va smoke test

### 4.2 Middleware phan quyen admin

Da them:

- `app/Middlewares/AdminMiddleware.php`

Noi dung:

- Reuse `AuthMiddleware`
- Xac thuc bearer token
- Bat buoc role `admin`

### 4.3 Helper ho tro slug

Da them:

- `app/Support/Slugger.php`

### 4.4 Admin auth va bao ve admin web

Da them/cap nhat:

- `app/Controllers/Admin/AdminAuthController.php`
- `app/Middlewares/AdminPageMiddleware.php`
- `app/Services/DefaultAdminProvisioningService.php`
- `scripts/ensure_default_admin.php`
- `views/admin/auth/login.php`
- `views/admin/partials/header.php`
- `app/Controllers/Auth/AuthController.php`
- `app/Core/Response.php`
- `public/index.php`

Noi dung:

- Dang nhap admin backend that thay cho preview-only form
- Dang xuat admin va clear auth state
- Bao ve toan bo admin pages bang session/cookie flow
- Redirect ve `/admin/login` neu truy cap admin page khi chua xac thuc
- Seed/provision tai khoan admin mac dinh cho moi truong dev

Ghi chu:

- Da tao san tai khoan admin dev mac dinh qua SQL seed va script provision
- Tai khoan nay chi phu hop cho moi truong local/dev, can doi mat khau truoc khi deploy that

## 5. Backend Phase 2 da trien khai

### 5.1 Validator chuyen biet cho Movie Management

Da them:

- `app/Validators/MovieManagementValidator.php`

Bao gom:

- Validate movie payload
- Validate category payload
- Validate asset payload
- Validate review moderation payload
- Normalize filters cho list API

Rule noi bat:

- Validate slug
- Validate status enum
- Validate URL chi cho phep `http/https`
- Validate integer/float
- Validate category assignments
- Force asset `archived` thi `is_primary = 0`
- Tu dong force review khong approved thi `is_visible = 0`

### 5.2 Repository layer

Da them:

- `app/Repositories/MovieRepository.php`
- `app/Repositories/MovieCategoryRepository.php`
- `app/Repositories/MovieCategoryAssignmentRepository.php`
- `app/Repositories/MovieImageRepository.php`
- `app/Repositories/MovieReviewRepository.php`
- `app/Repositories/Concerns/PaginatesQueries.php`

Noi dung:

- Paginate data
- Tim kiem theo filter
- CRUD cho movie/category/asset
- Summary cho `movies`, `movie_categories`, `movie_images`
- Moderate review
- Tinh lai `average_rating` va `review_count`

### 5.3 Service orchestration

Da them:

- `app/Services/MovieManagementService.php`
- `app/Services/MovieOphimSyncService.php`

Trach nhiem:

- Validate input
- Kiem tra uniqueness
- Kiem tra FK ton tai
- Thuc hien transaction
- Logging cho create/update/archive/moderate
- Mapping output data ra contract API on dinh
- Tra summary data cho admin stat cards
- Import/upsert movie tu OPhim vao local DB, tu dong tao category va sync assets
- Batch import movie list tu OPhim vao local DB, tong hop ket qua `created/updated/skipped/failed`

Transaction duoc ap dung cho:

- `create movie + category assignments`
- `update movie + category assignments`
- `create/update asset + primary flag consistency`
- `moderate review + recompute movie summary`
- `OPhim import/update + category assignment replace + asset archive/resync`

### 5.4 Admin API controller

Da them:

- `app/Controllers/Admin/MovieManagementController.php`

Trach nhiem:

- Nhan request
- Goi service
- Tra JSON response
- Lay `actor_id` tu auth payload

### 5.5 Route API admin

Da cap nhat:

- `config/routes.php`

API da co:

- `GET /api/admin/movies`
- `POST /api/admin/movies/import-ophim`
- `POST /api/admin/movies/import-ophim-list`
- `GET /api/admin/movies/{id}`
- `POST /api/admin/movies`
- `PUT /api/admin/movies/{id}`
- `DELETE /api/admin/movies/{id}`

- `GET /api/admin/movie-categories`
- `GET /api/admin/movie-categories/{id}`
- `POST /api/admin/movie-categories`
- `PUT /api/admin/movie-categories/{id}`
- `DELETE /api/admin/movie-categories/{id}`

- `GET /api/admin/movie-assets`
- `GET /api/admin/movie-assets/{id}`
- `POST /api/admin/movie-assets`
- `PUT /api/admin/movie-assets/{id}`
- `DELETE /api/admin/movie-assets/{id}`

- `GET /api/admin/movie-reviews`
- `GET /api/admin/movie-reviews/{id}`
- `PUT /api/admin/movie-reviews/{id}/moderate`

Tat ca route admin movie API hien tai deu di qua:

- `AdminMiddleware`

### 5.6 Admin UI Phase 3 da noi backend that

Da trien khai:

- `public/assets/admin/movie-management-movies.js`
- `public/assets/admin/movie-management-categories.js`
- `public/assets/admin/movie-management-movie-images.js`

Noi dung:

- `Movies`
  - list/search/filter/pagination
  - view detail
  - create/update/archive
  - import/sync movie tu OPhim vao local DB
  - batch sync list OPhim vao local DB
  - export CSV
  - load category options that
- `Categories`
  - list/search/filter/pagination
  - view detail
  - create/update/deactivate
  - export CSV
  - stat cards lay summary that
- `Movie Images`
  - list/search/filter/pagination
  - view detail
  - create/update/archive
  - export CSV
  - load movie options that
  - dong bo rule primary asset theo BE

Trang thai:

- Ca 3 tab tren da fetch data that tu admin API
- `Reviews` la tab con lai chua noi backend that

## 6. Logging, bao mat, tinh nhat quan

### 6.1 Logging

Da su dung:

- `App\Core\Logger`

Log cho:

- Movie created
- Movie updated
- Movie archived
- Movie imported/synced from OPhim
- Movie list batch synced from OPhim
- Category created/updated/deactivated
- Asset created/updated/archived
- Review moderated
- Loi transaction / persistence

### 6.2 Bao mat

Da ap dung:

- Xac thuc bearer token
- Role-based access cho admin API
- Sanitize input trong request
- Validate status/ID/URL/slug o service boundary

### 6.3 Tinh nhat quan du lieu

Da ap dung:

- Transaction cho thao tac nhieu bang
- Rollback khi assignment fail
- Recompute `average_rating` va `review_count` sau moderation
- Dam bao asset primary khong bi xung dot trong cung `movie_id + asset_type`

## 7. Test da bo sung

### 7.1 Unit tests

Da them:

- `tests/Unit/MovieManagementValidatorTest.php`
- `tests/Unit/MovieManagementServiceTest.php`

Kiem tra:

- Validation movie payload
- Slug/category normalization
- Asset validation va archived-primary rule
- Review moderation rules
- OPhim import payload validation
- OPhim batch import payload validation
- Conflict slug
- Create movie + assignment
- List category summary
- List asset summary
- Update summary sau moderation
- Service wrapper tra movie + sync metadata sau OPhim import
- Service wrapper tra batch summary sau OPhim list import

### 7.2 Feature tests

Da them:

- `tests/Feature/MovieManagementControllerTest.php`

Kiem tra:

- Contract JSON response cua controller
- Response created
- Response validation error
- Response import OPhim thanh cong va message dung ngu nghia
- Response batch sync OPhim thanh cong va message dung ngu nghia

Ngoai ra da bo sung them test lien quan admin auth:

- `tests/Feature/AdminAuthControllerTest.php`
- `tests/Unit/AdminPageMiddlewareTest.php`
- `tests/Unit/DefaultAdminProvisioningServiceTest.php`

### 7.3 Integration tests

Da them:

- `tests/Integration/MovieManagementServiceIntegrationTest.php`

Kiem tra:

- Create movie luu du lieu that vao SQLite memory
- Rollback transaction khi assignment fail
- List category summary tren du lieu that
- List asset summary tren du lieu that
- Create asset va dam bao chi con 1 primary asset trong cung `movie_id + asset_type`
- Moderate review cap nhat review va summary movie
- OPhim import update local movie, tao category moi, replace assignments, archive asset cu va sync asset moi
- OPhim batch import tao nhieu movie local tu list endpoint

## 8. Verification da thuc hien

### 8.1 Da pass syntax lint

Da lint thanh cong cac file PHP moi/sua bang:

- `C:\\xampp\\php\\php.exe -l ...`

Da syntax check thanh cong JS bang:

- `node --check public/assets/admin/shared.js`
- `node --check public/assets/admin/movie-management-movies.js`
- `node --check public/assets/admin/movie-management-categories.js`
- `node --check public/assets/admin/movie-management-movie-images.js`
- `node --check public/assets/js/app.js`

### 8.2 Da chay smoke test bang PHP CLI

Da verify thanh cong:

- Create movie
- Admin auth provision script tao admin dev account
- Moderate review
- Update `average_rating`
- Update `review_count`
- Rollback transaction khi assignment fail
- Asset summary va asset primary consistency
- Router support `PUT` + route param `{id}`
- Admin login + import OPhim + list movie sau import qua HTTP endpoint that
- Admin login + batch sync OPhim list + tang total movie trong DB that

### 8.3 Han che hien tai khi run test

Chua the chay `phpunit` theo cach chuan trong may hien tai vi:

- `C:\\xampp\\php\\phpunit.bat` la ban qua cu
- Runner loi voi `each()` tren PHP hien tai

Tuy nhien:

- File test da duoc viet xong
- Syntax da lint pass
- Logic quan trong da duoc smoke verify bang PHP CLI

## 9. File chinh da them/sua trong module Movie Management

### 9.1 File backend moi

- `app/Controllers/Admin/AdminAuthController.php`
- `app/Controllers/Admin/MovieManagementController.php`
- `app/Middlewares/AdminMiddleware.php`
- `app/Middlewares/AdminPageMiddleware.php`
- `app/Repositories/Concerns/PaginatesQueries.php`
- `app/Repositories/MovieCategoryAssignmentRepository.php`
- `app/Repositories/MovieCategoryRepository.php`
- `app/Repositories/MovieImageRepository.php`
- `app/Repositories/MovieRepository.php`
- `app/Repositories/MovieReviewRepository.php`
- `app/Services/DefaultAdminProvisioningService.php`
- `app/Services/MovieManagementService.php`
- `app/Services/MovieOphimSyncService.php`
- `app/Support/Slugger.php`
- `app/Validators/MovieManagementValidator.php`
- `scripts/ensure_default_admin.php`

### 9.2 File test moi

- `tests/Feature/AdminAuthControllerTest.php`
- `tests/Feature/MovieManagementControllerTest.php`
- `tests/Integration/MovieManagementServiceIntegrationTest.php`
- `tests/Unit/AdminPageMiddlewareTest.php`
- `tests/Unit/DefaultAdminProvisioningServiceTest.php`
- `tests/Unit/MovieManagementServiceTest.php`
- `tests/Unit/MovieManagementValidatorTest.php`

### 9.3 File da cap nhat

- `app/Controllers/Auth/AuthController.php`
- `app/Core/Request.php`
- `app/Core/Response.php`
- `app/Core/Router.php`
- `app/Services/AuthService.php`
- `config/routes.php`
- `database/movie_shop.sql`
- `public/assets/admin/movie-management-categories.js`
- `public/assets/admin/movie-management-movie-images.js`
- `public/assets/admin/movie-management-movies.js`
- `public/assets/admin/shared.css`
- `public/assets/admin/shared.js`
- `public/assets/js/app.js`
- `public/index.php`
- `views/admin/auth/login.php`
- `views/admin/pages/movies/index.php`
- `views/admin/pages/movies/sections/categories.php`
- `views/admin/pages/movies/sections/movie-images.php`
- `views/admin/pages/movies/sections/movies.php`
- `views/admin/pages/movies/sections/reviews.php`
- `views/admin/partials/header.php`
- `views/pages/home.php`
- `views/pages/movie-detail.php`
- `views/pages/movies.php`

## 10. Phan chua lam tiep

Trang thai hien tai:

- `Movies`: da xong backend + admin UI that
- `Movies`: da co import/sync OPhim vao local DB
- `Movies`: da co batch sync OPhim list vao local DB
- `Categories`: da xong backend + admin UI that
- `Movie Images`: da xong backend + admin UI that
- `Reviews`: backend da co, admin UI chua noi API that

Con lai:

- Noi tab `Reviews` sang backend that
- Upload file that / media storage that cho movie assets
- Dong bo/import category-asset nang cao tu OPhim neu can batch sync
- User review API cong khai neu can mo review that ben user
- Test end-to-end
- Observability nang cao

## 11. Buoc tiep theo hop ly

`Phase 3` con lai:

- Noi `Reviews` admin UI vao `/api/admin/movie-reviews`
- Them list/filter/pagination
- Them moderate approve/reject/hide
- Dong bo summary rating sau moderation

`Phase 4`:

- Hoan thien `Reviews` admin UI
- Can nhac them batch import/sync tu OPhim cho admin
- Neu can dat muc tieu dong nhat nguon du lieu, thiet ke quy trinh user doc tu local DB sau khi admin import
