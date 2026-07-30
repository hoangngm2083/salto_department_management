# Department Management

Hệ thống quản lý phòng ban và nhân sự: RESTful API viết bằng Laravel 13 (PHP 8.4), xác thực bằng Sanctum (token abilities theo vai trò), kèm chức năng import hàng loạt bằng CSV chạy nền (queue), và một SPA React (Vite) làm giao diện người dùng.

## Mục lục

- [Kiến trúc](#kiến-trúc)
- [Chức năng chính](#chức-năng-chính)
- [Phân quyền](#phân-quyền)
- [Import CSV hàng loạt](#import-csv-hàng-loạt)
- [Điểm hay / đáng chú ý](#điểm-hay--đáng-chú-ý)
- [Cấu trúc thư mục](#cấu-trúc-thư-mục)
- [Yêu cầu hệ thống](#yêu-cầu-hệ-thống)
- [Hướng dẫn cài đặt & chạy](#hướng-dẫn-cài-đặt--chạy)
- [Chạy test](#chạy-test)
- [Tài liệu API](#tài-liệu-api)

## Kiến trúc

- **Backend**: Laravel 13, PHP 8.4. Toàn bộ API nằm dưới `routes/api.php`, namespace `App\Http\Controllers\Api\V1`.
- **Auth**: Laravel Sanctum — đăng nhập bằng email/password trả về personal access token, mỗi token được gắn **abilities** tương ứng với vai trò (`admin` / `manager` / `employee`) tại thời điểm cấp token (`App\Services\AuthService::abilitiesFor()`).
- **Authorization**: Laravel Policies (`DepartmentPolicy`, `EmployeePolicy`, `ImportPolicy`) kết hợp với middleware `abilities:<ability>` áp riêng cho từng action của `apiResource` (`->middlewareFor()`).
- **Import**: Upload file → lưu file + tạo bản ghi `Import` (status `queued`) → dispatch job lên queue (`ProcessImportJob`) → job chia file thành các chunk và dispatch `Bus::batch()` các `ImportChunkJob` chạy song song trên nhiều queue worker → mỗi chunk tự parse, validate, upsert theo transaction riêng, ghi lỗi từng dòng vào `import_errors`, chống trùng key trong cùng 1 lần import bằng bảng `import_seen_keys`.
- **Frontend**: SPA React 19 + Vite (`fe/vite-project`), gọi API qua Axios, dùng `react-router-dom` cho routing và Context API cho auth state — độc lập hoàn toàn với Laravel views/Inertia (project scaffold có sẵn Inertia nhưng không được dùng ở phần frontend thực tế này).
- **Queue/Job store**: mặc định dùng `database` queue driver — không cần cài Redis để chạy được, chỉ cần `php artisan queue:work`.
- **DB**: MySQL (khuyến nghị cho production, vì các cơ chế khoá/atomic upsert được thiết kế và kiểm chứng cho InnoDB), test suite mặc định chạy trên SQLite in-memory để nhanh và không phụ thuộc môi trường.

## Chức năng chính

- **Quản lý Phòng ban** (`/api/v1/departments`): CRUD đầy đủ, slug tự động, soft delete, trạng thái `active`/`inactive`.
- **Quản lý Nhân viên** (`/api/v1/employees`): CRUD đầy đủ, gắn với phòng ban, vai trò (`employee`/`manager`/`admin`), ngày sinh, soft delete, index tối ưu cho tìm kiếm theo tên và lọc theo vai trò.
- **Xác thực** (`/api/v1/auth`): đăng nhập, lấy thông tin bản thân (`me`), đăng xuất (thu hồi token hiện tại).
- **Import hàng loạt bằng CSV** (`/api/v1/imports`): import phòng ban hoặc nhân viên từ file CSV, xử lý bất đồng bộ, theo dõi tiến độ và lỗi theo từng dòng qua endpoint `show`.

## Phân quyền

Vai trò nhân viên (`position`): `admin`, `manager`, `employee`. Xem chi tiết ở [auth-business-rules.md](auth-business-rules.md).

| | Admin | Manager | Employee |
|---|---|---|---|
| **Phòng ban** | Full CRUD | Xem danh sách/chi tiết; chỉ sửa được phòng ban của mình | Chỉ đọc |
| **Nhân viên** | Full CRUD, gán phòng ban, đổi vai trò | Xem/tạo/sửa nhân viên trong phòng ban của mình; không xoá, không đổi vai trò | Xem danh sách; chỉ xem/sửa hồ sơ của chính mình |
| **Import** | Toàn quyền tạo & xem import | Theo ability được cấp | Theo ability được cấp |

Việc phân quyền được thực hiện ở 2 lớp:

1. **Token abilities** (Sanctum) — giới hạn ngay từ lúc cấp token, ví dụ `manager` không bao giờ có ability `employees:delete`.
2. **Policy** (`DepartmentPolicy`, `EmployeePolicy`, `ImportPolicy`) — kiểm tra logic nghiệp vụ chi tiết hơn (VD: manager chỉ sửa được phòng ban của chính họ, employee chỉ xem/sửa hồ sơ của chính mình).

## Import CSV hàng loạt

Chi tiết đầy đủ: [batch_processing_requirements.md](batch_processing_requirements.md), [csv_reader_explains.md](csv_reader_explains.md), [import_concurrency_strategy.md](import_concurrency_strategy.md).

Luồng xử lý:

1. Client gọi `POST /api/v1/imports` (multipart file + `type` = `department`|`employee`) → file được lưu vào disk, bản ghi `Import` tạo với status `queued`, trả về `202 Accepted` ngay lập tức (không xử lý file inline trong request).
2. `ProcessImportJob` đọc file, chia thành các chunk theo `IMPORT_CHUNK_SIZE` (mặc định 1000 dòng), dispatch song song bằng `Bus::batch()` — tận dụng nhiều queue worker.
3. Mỗi `ImportChunkJob` xử lý 1 chunk trong 1 transaction: parse CSV, validate từng dòng, `upsert()` atomic theo business key (VD: `slug` cho Department), ghi lỗi dòng vào `import_errors`, chống trùng key trong cùng import bằng `import_seen_keys` (unique constraint + `insertOrIgnore`).
4. Job có cấu hình `$tries`/`backoff` tăng dần để tự phục hồi khi gặp lock-wait/deadlock hiếm gặp giữa các import chạy đồng thời (xem phân tích trong `import_concurrency_strategy.md`).
5. Client theo dõi tiến độ qua `GET /api/v1/imports/{import}` — trả về `total`, `created_count`, `updated_count`, `failed_count`, danh sách lỗi theo dòng.

Cấu hình import nằm ở [config/imports.php](config/imports.php), điều khiển qua biến môi trường: `IMPORT_ALLOWED_EXTENSIONS`, `IMPORT_MAX_FILE_SIZE_MB`, `IMPORT_MAX_RECORDS`, `IMPORT_CHUNK_SIZE`, `IMPORT_QUEUE`, `IMPORT_MAX_RETRY`, `IMPORT_TIMEOUT`.

## Điểm hay / đáng chú ý

- **Không xử lý import trong request** — luôn queue, tránh timeout với file lớn (tối đa 100.000 dòng/file).
- **Handler pattern mở rộng được**: thêm loại import mới chỉ cần tạo Handler mới implement từ `AbstractImportHandler` và đăng ký trong `config/imports.php`, không cần sửa `ImportController`/`ImportStrategyResolver`.
- **Chống trùng key trong cùng 1 lần import** bằng bảng phụ `import_seen_keys` (unique constraint) thay vì lock ở tầng ứng dụng — rẻ và không tranh chấp với các import khác.
- **Quyết định có cân nhắc kỹ giữa optimistic vs pessimistic locking** cho race condition giữa các import khác nhau chạm cùng business key — chọn giữ nguyên `upsert()` atomic + retry có backoff ở tầng Job thay vì `lockForUpdate()`, đánh đổi rủi ro lệch nhẹ số liệu `created`/`updated` (xác suất <1%) để tránh deadlock khi nhiều chunk 1000 dòng chạy song song. Xem phân tích đầy đủ ở [import_concurrency_strategy.md](import_concurrency_strategy.md).
- **Test race condition thật trên MySQL**: ngoài test rollback bằng SQLite mặc định, có bộ test riêng (`tests/Feature/ImportMysqlConcurrencyTest.php`) tự dựng 2 connection MySQL thật, xen kẽ statement để tái hiện lock-wait/deadlock xác định (không phụ thuộc timing), tự `skip` nếu môi trường không có MySQL nên không phá CI mặc định.
- **Phân quyền 2 lớp** (Sanctum token abilities + Policy) giúp giới hạn quyền ngay từ tầng xác thực, giảm bề mặt tấn công so với chỉ kiểm tra ở tầng Controller.
- **API tự sinh tài liệu** qua Scramble — không cần viết OpenAPI spec tay.

## Cấu trúc thư mục

```
app/
├── Enums/                  # ImportStatus, ImportType
├── Http/
│   ├── Controllers/Api/V1/ # AuthController, DepartmentController, EmployeeController, ImportController
│   ├── Requests/           # Form Request validation theo từng nghiệp vụ
│   └── Resources/          # API Resource transformer
├── Jobs/Import/            # ProcessImportJob, ImportChunkJob
├── Models/                 # Department, Employee, Import, ImportError
├── Policies/               # DepartmentPolicy, EmployeePolicy, ImportPolicy
└── Services/
    ├── AuthService.php
    ├── DepartmentService.php
    ├── EmployeeService.php
    ├── ImportService.php
    └── Import/              # CsvReader, ImportStrategyResolver, AbstractImportHandler, Handlers/
fe/vite-project/            # SPA React + Vite (giao diện người dùng)
tests/
├── Feature/                 # Test luồng API end-to-end
└── Unit/                    # Test đơn vị cho Service/Job/Handler
```

## Yêu cầu hệ thống

- PHP >= 8.3 (dự án dùng 8.4)
- Composer
- Node.js >= 18 + npm
- MySQL (khuyến nghị) hoặc SQLite

## Hướng dẫn cài đặt & chạy

### 1. Backend (Laravel API)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Cấu hình kết nối database trong `.env` (mặc định MySQL, database `department_management`):

```bash
php artisan migrate
```

Chạy server và queue worker (import chạy nền, bắt buộc phải chạy worker để import được xử lý):

```bash
php artisan serve
```

```bash
php artisan queue:work --queue=imports
```

Hoặc dùng script tổng hợp có sẵn (chạy song song server + queue + log + vite) nếu đã cài `concurrently`:

```bash
composer run dev
```

### 2. Frontend (React SPA)

```bash
cd fe/vite-project
npm install
npm run dev
```

Frontend gọi API tới backend Laravel — kiểm tra biến base URL trong `src/lib/http.js` khớp với địa chỉ `php artisan serve` (mặc định `http://localhost:8000`).

## Chạy test

```bash
php artisan test --compact
```

Test suite mặc định dùng SQLite in-memory (`phpunit.xml`), chạy nhanh và không cần MySQL. Hai test race-condition thật trên MySQL (`tests/Feature/ImportMysqlConcurrencyTest.php`) tự động `skip` nếu không có MySQL server đang chạy đúng theo cấu hình `DB_HOST`/`DB_PORT`/`DB_USERNAME`/`DB_PASSWORD` trong `.env`.

## Tài liệu API

Dự án dùng [Dedoc Scramble](https://scramble.dedoc.co/) để tự sinh tài liệu OpenAPI từ code. Sau khi chạy `php artisan serve`, truy cập:

```
http://localhost:8000/docs/api
```
