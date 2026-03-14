# Movie Management Guide

## 1. Muc dich

Tai lieu nay dung de huong dan su dung module `Movie Management` trong moi truong local/dev.

Pham vi:

- Dang nhap admin
- Quan ly `Movies`
- Quan ly `Categories`
- Quan ly `Movie Images`
- Import/sync phim tu OPhim
- Kiem tra du lieu hien thi ben user
- Cac luu y van hanh va troubleshooting co ban

## 2. URL chinh

Admin:

- `http://localhost/web-dat-ve-xem-phim/admin/login`
- `http://localhost/web-dat-ve-xem-phim/admin/movies?section=movies`
- `http://localhost/web-dat-ve-xem-phim/admin/movies?section=categories`
- `http://localhost/web-dat-ve-xem-phim/admin/movies?section=movie-images`
- `http://localhost/web-dat-ve-xem-phim/admin/movies?section=reviews`

User:

- `http://localhost/web-dat-ve-xem-phim/movies`
- `http://localhost/web-dat-ve-xem-phim/movie-detail?slug={movie-slug}`
- `http://localhost/web-dat-ve-xem-phim/seat-selection?showtime_id={id}&slug={movie-slug}`

## 3. Tai khoan admin local

Tai khoan mac dinh cho local/dev:

- Username: `admin`
- Password: `admin`

Luu y:

- Tai khoan nay chi dung cho local/dev
- Khong duoc giu nguyen khi dua len moi truong that

## 4. Tong quan module

`Movie Management` hien tai gom 4 nhom chinh:

- `Movies`
- `Categories`
- `Movie Images`
- `Reviews`

Trang thai thuc te:

- `Movies`: da co backend that + admin UI that
- `Categories`: da co backend that + admin UI that
- `Movie Images`: da co backend that + admin UI that
- `Reviews`: backend da co, admin UI chua noi API that hoan chinh

## 5. Huong dan su dung tab Movies

### 5.1 Tao movie local thu cong

Trong tab `Movies`:

1. Bam `Add Movie`
2. Dien cac truong bat buoc:
   - `Movie Title`
   - `Slug`
   - `Primary Category`
   - `Status`
3. Neu co:
   - `Poster URL`
   - `Trailer URL`
   - `Duration`
   - `Release Date`
   - `Language`
   - `Director`
   - `Cast Summary`
4. Bam luu

Luu y:

- `slug` phai unique
- `poster_url` nen la anh doc
- `status` quyet dinh kha nang hien thi ben user

### 5.2 Sua movie

Trong bang danh sach `Movies`:

1. Bam icon `Edit`
2. Sua thong tin can thiet
3. Bam luu

Neu movie da co ben user:

- user detail se uu tien du lieu local khi `slug` trung nhau
- neu sua `title`, `summary`, `poster`, `trailer`, `status` thi ben user se thay doi theo movie local

### 5.3 Archive movie

Trong bang danh sach `Movies`:

1. Bam icon `Archive/Delete`
2. Xac nhan thao tac

Ket qua:

- movie se khong con duoc xem la movie public
- user se khong thay movie nay tren catalog neu no khong con nam trong public status

### 5.4 Y nghia status movie

Trang thai da chuan hoa:

- `draft`
- `coming_soon`
- `now_showing`
- `ended`
- `archived`

Rule van hanh:

- `draft`: chua san sang hien thi cho user
- `coming_soon`: co the hien thi ben user
- `now_showing`: co the hien thi ben user
- `ended`: co the dung cho noi bo/lich su, tuy deployment co the an tren user catalog
- `archived`: an khoi luong public

Ghi chu quan trong:

- Neu movie khong thay tren `/movies`, kiem tra lai `status` truoc

## 6. Import phim tu OPhim

### 6.1 Import tung phim

Trong tab `Movies`:

1. Bam `Import OPhim`
2. Nhap `slug` phim OPhim
3. Chon:
   - `status_override`
   - `sync_images`
   - `overwrite_existing`
4. Xac nhan import

Ket qua:

- upsert movie local theo `slug`
- tao category neu local chua co
- replace category assignments
- sync lai assets neu bat `sync_images`

### 6.2 Batch sync danh sach OPhim

Trong tab `Movies`:

1. Bam `Batch Sync OPhim`
2. Chon `list_slug`, vi du:
   - `phim-chieu-rap`
   - `phim-sap-chieu`
3. Chon `page`
4. Chon `limit`
5. Chon:
   - `status_override`
   - `sync_images`
   - `overwrite_existing`
6. Xac nhan sync

Ket qua:

- import nhieu phim cung luc
- tra tong ket `created / updated / skipped / failed`

### 6.3 Mapping anh OPhim

Luu y quan trong:

- voi nguon OPhim hien tai:
  - `thumb_url` duoc dung lam poster doc cho card phim
  - `poster_url` duoc dung lam anh ngang/banner

Vi vay:

- neu thay poster tren user bi ngang hoac crop sai, can kiem tra movie co di qua mapping dung hay khong
- cac fix gan day da uu tien `thumb_url` cho user catalog

## 7. Huong dan su dung tab Categories

Trong tab `Categories` co the:

- Tao category moi
- Sua category
- Deactivate category
- Search/filter/pagination

Rule:

- `slug` phai unique
- category chinh cua movie duoc luu trong `movies.primary_category_id`
- movie van co the co them category phu qua bang assignment

Khi nao can tao category:

- Truoc khi tao local movie thu cong
- Hoac de import/sync movie OPhim map category cho sach du lieu

## 8. Huong dan su dung tab Movie Images

Trong tab `Movie Images` co the:

- Tao asset moi
- Sua asset
- Archive asset
- Chon `movie_id`
- Chon `asset_type`
- Set `is_primary`

Loai asset:

- `poster`
- `banner`
- `gallery`

Rule:

- cung `movie_id + asset_type` chi nen co 1 asset `is_primary = 1`
- backend da co transaction de giu consistency cho primary asset
- asset `archived` se khong duoc giu `is_primary`

Khuyen nghi:

- `poster`: anh doc
- `banner`: anh ngang hero/banner
- `gallery`: anh bo sung cho detail

## 9. Reviews

Trang thai hien tai:

- backend moderation da co
- admin UI tab `Reviews` chua hoan thien ket noi that

Status review:

- `pending`
- `approved`
- `rejected`

Visibility:

- review khong `approved` thi khong nen hien thi cong khai

## 10. User side dang hoat dong nhu the nao

### 10.1 Trang `/movies`

Trang nay hien co:

- search
- filter category
- filter rating
- sort
- status tabs
- pagination

Nguon du lieu:

- neu co movie public local thi catalog uu tien local
- neu search theo keyword thi goi OPhim `/tim-kiem`
- neu khong co local public catalog thi co the fallback sang list OPhim

### 10.2 Trang `/movie-detail`

Trang detail hien co:

- poster/banner
- trailer
- gallery
- related movies
- available showtimes

Rule quan trong:

- user chi duoc xem `trailer`
- public API khong expose source xem phim day du

### 10.3 Trang `/seat-selection`

Trang nay doc du lieu that theo:

- `showtime_id`

Noi dung co:

- thong tin suat chieu
- so do ghe
- ghe da dat / ghe trong
- tong ket so ghe chon

Luu y:

- flow nay hien moi dung o muc read/select seat va chuyen tiep
- `checkout` transaction that chua hoan tat

## 11. Luu y dong bo du lieu admin -> user

### 11.1 Neu sua movie local

Khi sua movie local bang admin:

- user detail voi cung `slug` se uu tien movie local
- cac thay doi nhu `title`, `summary`, `poster`, `trailer`, `status` se anh huong ben user

### 11.2 Neu import movie tu OPhim

Sau khi import:

- movie se vao local DB
- category co the duoc tao moi
- assets co the duoc sync lai
- user co the thay movie neu no nam trong status public hop le

### 11.3 Neu movie khong hien tren user

Kiem tra lan luot:

1. `status` co phai `now_showing` hoac `coming_soon` khong
2. `slug` co dung khong
3. movie dang local hay dang fallback OPhim
4. poster/trailer URL co hop le khong
5. co can refresh lai trang de lay du lieu moi khong

## 12. Troubleshooting nhanh

### 12.1 Movie khong xuat hien tren `/movies`

Kiem tra:

- movie dang `draft` hoac `archived`
- category/filter dang loc mat movie
- search keyword khong dung

### 12.2 Poster bi sai ti le

Kiem tra:

- movie local co dang luu anh ngang vao `poster_url` hay khong
- neu la movie OPhim, uu tien poster doc tu `thumb_url`

### 12.3 Detail khong dong bo sau khi sua admin

Kiem tra:

- movie co trung `slug`
- refresh lai trang
- movie detail co dang doc local hay fallback OPhim

### 12.4 Khong co showtimes de dat ve

Kiem tra:

- local DB da co du lieu `showtimes`
- da chay script seed mau neu moi local:
  - `C:\xampp\php\php.exe scripts/ensure_sample_showtimes.php`

## 13. Checklist su dung hang ngay

Khi van hanh module, co the di theo checklist nay:

1. Dang nhap admin
2. Tao/sync category neu can
3. Tao movie local hoac import movie tu OPhim
4. Kiem tra `status`
5. Kiem tra `poster_url` va `trailer_url`
6. Kiem tra `Movie Images` neu can banner/gallery
7. Reload `/movies`
8. Mo `/movie-detail?slug=...` de verify
9. Kiem tra `Available Showtimes` neu movie can dat ve

## 14. Ghi chu cho production

Truoc khi dua len production, can bo sung:

- doi tai khoan `admin/admin`
- bo sung media storage that neu upload file
- hoan thien `Reviews` admin UI
- hoan thien `checkout` va booking transaction that
- bo sung test end-to-end va monitoring
