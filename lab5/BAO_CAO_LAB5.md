# BÁO CÁO BÀI THỰC HÀNH LAB 05
## LƯU, TRUY VẤN VÀ THỐNG KÊ ĐĂNG KÝ VỚI POSTGRESQL

- **Học phần**: Phát triển phần mềm mã nguồn mở
- **Lớp**: CNTT 18-10
- **Nhóm**: Nhóm 10
- **Dự án**: CommunityPass — Phần mềm đăng ký tham dự sự kiện

### 👥 Danh sách thành viên thực hiện
| STT | Họ và tên | Mã sinh viên | Vai trò |
| :---: | :--- | :---: | :--- |
| 1 | **Nguyễn Văn An** | `1871020003` | Trưởng nhóm |
| 2 | **Lê Đình Huy** | `1871020307` | Thành viên |
| 3 | **Đào Thị Tươi** | `1871020639` | Thành viên |

---

## I. MỤC TIÊU BÀI THỰC HÀNH
1. Cài đặt, cấu hình và vận hành hệ quản trị cơ sở dữ liệu quan hệ mã nguồn mở PostgreSQL trên hệ điều hành Linux (Ubuntu/WSL).
2. Thiết kế lược đồ cơ sở dữ liệu quan hệ (`schema.sql`) chuẩn hóa với khóa chính (`PRIMARY KEY`), khóa ngoại (`FOREIGN KEY / REFERENCES`), ràng buộc toàn vẹn dữ liệu (`CHECK`) và ràng buộc chống trùng lặp (`UNIQUE(event_id, email)`).
3. Kết nối PHP với PostgreSQL thông qua extension `pgsql`, áp dụng kỹ thuật truy vấn tham số hóa (`pg_query_params`) nhằm triệt tiêu hoàn toàn nguy cơ tấn công SQL Injection.
4. Xử lý bài toán ghi dữ liệu đồng thời và chống trùng lặp bằng cú pháp `INSERT INTO ... ON CONFLICT (event_id, email) DO NOTHING RETURNING id` để phân biệt chính xác trạng thái `CREATED` và `DUPLICATE`.
5. Viết câu truy vấn thống kê dữ liệu nâng cao sử dụng phép kết nối ngoài `LEFT JOIN` và hàm tổng hợp `COUNT(r.id)` để thống kê chính xác cả những sự kiện chưa có người đăng ký nào.

---

## II. CHUẨN BỊ MÔI TRƯỜNG VÀ CÀI ĐẶT POSTGRESQL

### 1. Cài đặt công cụ trên Ubuntu/WSL
```bash
sudo apt update
sudo apt install -y postgresql postgresql-client php-pgsql
sudo service postgresql start
psql --version
php -m | grep pgsql
```
Kết quả kiểm tra thực tế:
```
psql (PostgreSQL) 18.6 (Ubuntu 18.6-0ubuntu0.26.04.1)
pdo_pgsql
pgsql
```

### 2. Khởi tạo Role và Database
Thực hiện trong terminal:
```bash
sudo -u postgres psql
```
```sql
CREATE ROLE communitypass_lab_v5 LOGIN PASSWORD 'lab5pass123';
CREATE DATABASE communitypass_lab_v5 OWNER communitypass_lab_v5;
\q
```

### 3. Cấu hình kết nối cục bộ
Tạo tệp cấu hình bảo mật `lab5/Ma_nguon/.local/db.json` (được đưa vào `.gitignore`, không đưa lên Git):
```json
{
  "PGHOST": "127.0.0.1",
  "PGPORT": "5432",
  "PGDATABASE": "communitypass_lab_v5",
  "PGUSER": "communitypass_lab_v5",
  "PGPASSWORD": "lab5pass123"
}
```

Kiểm tra kết nối bằng script:
```bash
bash lab.sh check
```
Kết quả:
```
CSDL=communitypass_lab_v5 | USER=communitypass_lab_v5
MOI_TRUONG=SAN_SANG | Lab 5
Đây không phải kết luận bài tập đã hoàn thành.
```

---

## III. QUÁ TRÌNH THỰC HIỆN VÀ MÃ NGUỒN

### 1. Lược đồ cơ sở dữ liệu: `database/schema.sql`
```sql
CREATE TABLE IF NOT EXISTS events (
    id integer PRIMARY KEY,
    title varchar(160) NOT NULL,
    category varchar(30) NOT NULL,
    capacity integer NOT NULL CHECK (capacity > 0)
);

CREATE TABLE IF NOT EXISTS registrations (
    id bigserial PRIMARY KEY,
    event_id integer NOT NULL REFERENCES events(id),
    name varchar(120) NOT NULL CHECK (length(trim(name)) > 0),
    email varchar(200) NOT NULL CHECK (email = lower(trim(email))),
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (event_id, email)
);

INSERT INTO events (id,title,category,capacity) VALUES
(1,'PHP Open Day','tech',10),(2,'Design Workshop','design',5),
(3,'Git Clinic','tech',20) ON CONFLICT (id) DO NOTHING;
```

### 2. Module kết nối cơ sở dữ liệu: `src/Database.php`
- Đọc biến môi trường do `.local/db.json` thiết lập.
- Mở kết nối thông qua hàm `pg_connect()` có cấu hình `connect_timeout=3`.
- Thực thi truy vấn tham số hóa bằng `pg_query_params()` để bảo vệ an toàn trước SQL Injection.

### 3. Lớp Repository lưu trữ và truy vấn đăng ký: `src/RegistrationRepository.php`
- Phương thức `save(int $eventId, string $name, string $email): string`:
  - Chuẩn hóa: `trim` họ tên, chuyển email về chữ thường.
  - Thực thi câu lệnh `INSERT ... ON CONFLICT (event_id, email) DO NOTHING RETURNING id`.
  - Nếu số hàng trả về bằng 1 $\rightarrow$ trả về `'CREATED'`.
  - Nếu trùng lặp cặp khóa (không có hàng nào được chèn) $\rightarrow$ trả về `'DUPLICATE'`.
- Phương thức `listForEvent(int $eventId): array`:
  - Truy vấn danh sách đăng ký theo `event_id` với tham số hóa an toàn.

### 4. Module thống kê đăng ký: `src/RegistrationStats.php` (Bài tập mở rộng)
Yêu cầu: Nhận ID sự kiện, trả về `['event_id' => int, 'title' => string, 'total' => int]`.
- Sự kiện không tồn tại (hoặc ID $\le 0$): Trả về mảng rỗng `[]`.
- Sự kiện chưa có người đăng ký: Phải trả về `total = 0`.
- Sử dụng `LEFT JOIN` và `COUNT(r.id)` (không dùng `COUNT(*)` vì `COUNT(*)` sẽ đếm 1 ngay cả khi không có đăng ký nào).

**Mã nguồn hoàn thiện:**
```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/Database.php';

function registrationStats(PgSql\Connection $connection, int $eventId): array
{
    if ($eventId <= 0) {
        return [];
    }

    $sql = 'SELECT e.id AS event_id, e.title, COUNT(r.id)::int AS total
            FROM events e
            LEFT JOIN registrations r ON r.event_id = e.id
            WHERE e.id = $1
            GROUP BY e.id, e.title';

    $result = queryParams($connection, $sql, [$eventId]);
    $row = pg_fetch_assoc($result);
    if (!$row) {
        return [];
    }

    return [
        'event_id' => (int)$row['event_id'],
        'title' => (string)$row['title'],
        'total' => (int)$row['total'],
    ];
}
```

### 5. Tích hợp lưu trữ vào luồng Web: `public/complete.php`
- Khi nhận yêu cầu xác nhận `POST`, gọi `$repo->save(...)` lưu dữ liệu vào PostgreSQL thật.
- Ghi nhận kết quả `CREATED` hoặc `DUPLICATE` vào `$_SESSION['completed']`.
- Nếu xảy ra lỗi kết nối cơ sở dữ liệu, giữ lại bản nháp và trả mã lỗi `503 Service Unavailable` để người dùng có thể gửi lại yêu cầu khi hệ thống phục hồi.

---

## IV. KẾT QUẢ KIỂM THỬ THỰC TẾ TRÊN TERMINAL

### 1. Kiểm tra môi trường kết nối CSDL (`bash lab.sh check`)
```
CSDL=communitypass_lab_v5 | USER=communitypass_lab_v5
MOI_TRUONG=SAN_SANG | Lab 5
Đây không phải kết luận bài tập đã hoàn thành.
```

### 2. Kiểm thử phần mẫu và kiểm thử tích hợp CSDL (`bash lab.sh test`)
```
CSDL=communitypass_lab_v5 | USER=communitypass_lab_v5
MOI_TRUONG=SAN_SANG | Lab 5
Đây không phải kết luận bài tập đã hoàn thành.
PASS saved
PASS before-expiry
PASS at-expiry
PASS cancelled
PASS unrelated-state-kept
PASS no-draft
TESTS=6 FAILED=0
PASS create
PASS normalized-duplicate
PASS one-row
PASS literal-data
PASS two-people
PASS quote-preserved
PASS empty-event
TESTS=7 FAILED=0
```
-> **Đạt cả 2 bộ kiểm thử: 6/6 ca unit test cơ bản và 7/7 ca tích hợp trực tiếp trên PostgreSQL.**

### 3. Kiểm thử phần mở rộng thống kê (`bash lab.sh extend`)
```
CSDL=communitypass_lab_v5 | USER=communitypass_lab_v5
MOI_TRUONG=SAN_SANG | Lab 5
Đây không phải kết luận bài tập đã hoàn thành.
BÀI TỰ MỞ RỘNG: có ca chưa đạt trước khi bạn hoàn thiện là bình thường.
PASS zero-included
PASS one
PASS duplicate-no-change
PASS missing
TESTS=4 FAILED=0
```
-> **4/4 ca kiểm thử mở rộng đạt (bao gồm cả ca biên sự kiện 0 người đăng ký và sự kiện không tồn tại).**

### 4. Kiểm chứng tính lưu trữ bền vững và chống trùng lặp (`demo-db.php`)
- **Lần chạy 1**:
  ```
  CREATED
  [
      {
          "id": "6",
          "event_id": "1",
          "name": "O'Neil Demo",
          "email": "demo@example.test"
      }
  ]
  ```
- **Lần chạy 2 (cùng thông tin)**:
  ```
  DUPLICATE
  [
      {
          "id": "6",
          "event_id": "1",
          "name": "O'Neil Demo",
          "email": "demo@example.test"
      }
  ]
  ```
- Khởi động lại máy chủ Web PHP và kiểm tra lại bằng lệnh `SELECT`: Dữ liệu vẫn được lưu trữ nguyên vẹn trên đĩa cứng qua PostgreSQL.

---

## V. MINH CHỨNG VÀ HÌNH ẢNH
1. `lab5/Hinh_anh/hinh_01.png`: Sơ đồ quy trình thực hiện Lab 05.
2. *Ảnh chụp màn hình đề xuất*: Sinh viên chụp màn hình terminal chạy `bash lab.sh check && bash lab.sh test && bash lab.sh extend` đạt `TESTS=7 FAILED=0` và `TESTS=4 FAILED=0`, kèm ảnh kết quả truy vấn `SELECT * FROM registrations;` trong `psql`.

---

## VI. BÀI HỌC KINH NGHIỆM
- Việc sử dụng `ON CONFLICT DO NOTHING` kết hợp ràng buộc `UNIQUE` ở cấp cơ sở dữ liệu giải quyết triệt để lỗi chạy đua (Race Condition) khi có nhiều request đăng ký đồng thời.
- Phân biệt rõ hàm đếm `COUNT(cột)` và `COUNT(*)` khi kết hợp với `LEFT JOIN`: `COUNT(cột)` bỏ qua các giá trị `NULL` giúp thống kê chính xác số lượng thực tế bằng 0 cho sự kiện chưa có đăng ký.
- Tuân thủ nguyên tắc bảo mật: Không bao giờ ghép chuỗi trực tiếp vào SQL; luôn sử dụng Prepared Statements và phân quyền tối thiểu (Least Privilege) cho tài khoản kết nối CSDL của ứng dụng.
