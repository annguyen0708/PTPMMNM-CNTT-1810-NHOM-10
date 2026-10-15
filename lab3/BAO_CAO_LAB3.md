# BÁO CÁO BÀI THỰC HÀNH LAB 03
## HIỂN THỊ SỰ KIỆN, TÌM KIẾM VÀ XỬ LÝ FORM

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
1. Nắm vững kỹ thuật duyệt và xử lý mảng kết hợp (Associative Array) trong PHP, phân tách rõ ràng giữa tầng dữ liệu, hàm xử lý nghiệp vụ và tầng hiển thị giao diện.
2. Hiểu rõ sự khác biệt giữa hai phương thức truyền nhận dữ liệu HTTP:
   - **GET**: Dùng cho chức năng tìm kiếm, lọc dữ liệu (các tham số hiển thị trên URL, có thể đánh dấu trang/chia sẻ).
   - **POST**: Dùng cho chức năng gửi dữ liệu biểu mẫu (Form đăng ký), dữ liệu được đóng gói an toàn trong HTTP Request Body.
3. Áp dụng kỹ thuật làm sạch dữ liệu đầu vào (`trim`, `is_string`) và mã hóa ký tự đặc biệt (`htmlspecialchars`) để phòng chống tấn công XSS (Cross-Site Scripting).
4. Xây dựng bộ lọc đa tiêu chí (kết hợp từ khóa, thể loại và trạng thái còn chỗ bằng toán tử logic `AND`) và duy trì trạng thái người dùng (persist form state) sau khi gửi yêu cầu.

---

## II. MÔI TRƯỜNG VÀ CÔNG CỤ THỰC HÀNH
- **Hệ điều hành**: Ubuntu 24.04 LTS trên WSL2.
- **PHP CLI & Built-in Web Server**: PHP 8.5.4.
- **Git**: Git 2.53.0.
- **Thư mục làm việc**: `lab3/Ma_nguon`.
- **Cổng Web**: `http://127.0.0.1:8103`.

---

## III. QUÁ TRÌNH THỰC HIỆN VÀ MÃ NGUỒN

### 1. Hàm lọc đa tiêu chí: `src/EventFilter.php`
Hàm nhận 4 tham số: danh sách sự kiện `$events`, từ khóa `$query`, thể loại `$category` và cờ lọc còn chỗ `$availableOnly`. Cả 3 điều kiện được kết hợp bằng toán tử `AND`:

```php
<?php
declare(strict_types=1);

function filterEvents(array $events, string $query, string $category = '',
    bool $availableOnly = false): array
{
    $result = [];
    foreach ($events as $event) {
        // 1. Điều kiện từ khóa: rỗng hoặc xuất hiện trong tiêu đề (không phân biệt hoa/thường)
        $matchesQuery = ($query === '') || (stripos($event['title'], $query) !== false);

        // 2. Điều kiện thể loại: rỗng hoặc trùng khớp với loại sự kiện
        $matchesCategory = ($category === '') || ($event['category'] === $category);

        // 3. Điều kiện còn chỗ: không yêu cầu HOẶC số chỗ còn lại > 0
        $matchesAvailability = !$availableOnly || (($event['capacity'] - $event['registered']) > 0);

        // Kết hợp cả 3 điều kiện bằng toán tử logic AND
        if ($matchesQuery && $matchesCategory && $matchesAvailability) {
            $result[] = $event;
        }
    }
    return $result;
}
```

### 2. Hàm an toàn dữ liệu: `src/Input.php`
- `field(array $input, string $key): string`: Lấy giá trị chuỗi an toàn từ `$_GET` hoặc `$_POST`, loại bỏ khoảng trắng thừa hai đầu và ngăn chặn lỗi khi nhận đối số dạng mảng lồng nhau.
- `e(string $value): string`: Sử dụng `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` để mã hóa các ký tự nhạy cảm (`<`, `>`, `&`, `"`, `'`), bảo vệ an toàn trước các cuộc tấn công XSS.

### 3. Giao diện danh sách và bộ lọc: `public/index.php`
- Đọc các tham số tìm kiếm từ `$_GET` thông qua hàm `field()`.
- Hiển thị Form tìm kiếm có đủ 3 trường: Ô nhập từ khóa `name="q"`, Ô chọn thể loại `name="category"` và Checkbox còn chỗ `name="available"`.
- Duy trì trạng thái các lựa chọn sau khi submit form (`value`, `selected`, `checked`).

### 4. Giao diện và xử lý Form đăng ký: `public/register.php`
- Nhận dữ liệu gửi lên qua phương thức `POST`.
- Gọi hàm `validateRegistration($_POST, $events)` để xác thực:
  - Kiểm tra `event_id` có tồn tại trong danh sách sự kiện hay không.
  - Họ tên không được rỗng và độ dài tối đa 120 ký tự.
  - Email phải có định dạng hợp lệ (`filter_var(..., FILTER_VALIDATE_EMAIL)`).
- Khi dữ liệu hợp lệ: Hiển thị thông báo xem trước `PREVIEW_OK: Họ tên — email` (chưa lưu vào CSDL hoặc Session).
- Khi dữ liệu không hợp lệ: Hiển thị danh sách lỗi chi tiết trong thẻ `role="alert"`.

---

## IV. KẾT QUẢ KIỂM THỬ THỰC TẾ

### 1. Kiểm tra môi trường (`bash lab.sh check`)
```
MOI_TRUONG=SAN_SANG | Lab 3
Đây không phải kết luận bài tập đã hoàn thành.
```

### 2. Kiểm thử phần bài mẫu (`bash lab.sh test`)
```
MOI_TRUONG=SAN_SANG | Lab 3
Đây không phải kết luận bài tập đã hoàn thành.
PASS all
PASS keyword-ignore-case
PASS not-found
PASS array-input
PASS escape-html
PASS valid-form
PASS invalid-form
TESTS=7 FAILED=0
```
-> **7/7 ca kiểm thử đạt.**

### 3. Kiểm thử phần mở rộng lọc đa tiêu chí (`bash lab.sh extend`)
```
MOI_TRUONG=SAN_SANG | Lab 3
Đây không phải kết luận bài tập đã hoàn thành.
BÀI TỰ MỞ RỘNG: có ca chưa đạt trước khi bạn hoàn thiện là bình thường.
PASS all
PASS keyword
PASS category
PASS available
PASS combined
PASS full-category
PASS unknown-category
PASS input-unchanged
TESTS=8 FAILED=0
```
-> **8/8 ca kiểm thử đạt.**

### 4. Thử nghiệm trên máy chủ Web cục bộ (`http://127.0.0.1:8103`)
- Lọc kết hợp: `?q=Git&category=tech&available=1` trả về đúng 1 sự kiện `Git Clinic` (ID: 3).
- Gửi form đăng ký POST hợp lệ: Trả về kết quả `PREVIEW_OK: Nguyen Van An — an@example.com`.

---

## V. MINH CHỨNG VÀ HÌNH ẢNH
1. `lab3/Hinh_anh/hinh_01.png`: Giao diện danh sách sự kiện ban đầu của hệ thống CommunityPass.
2. `lab3/Hinh_anh/hinh_02.png`: Sơ đồ quy trình thực hiện Lab 03.
3. `lab3/Hinh_anh/hinh_03.png`: Giao diện kết quả tìm kiếm với từ khóa `php`.
4. *Ảnh chụp màn hình đề xuất*: Sinh viên chụp giao diện trang chủ sau khi chọn bộ lọc kết hợp `Git` + `tech` + `còn chỗ` và giao diện Form đăng ký tại `register.php` hiển thị `PREVIEW_OK`.

---

## VI. BÀI HỌC KINH NGHIỆM
- Phân biệt rõ ngữ cảnh sử dụng GET (truy vấn, lọc thông tin) và POST (thay đổi trạng thái, gửi biểu mẫu).
- Luôn kiểm tra và làm sạch dữ liệu ở phía máy chủ (Server-side Validation); không phụ thuộc hoàn toàn vào kiểm tra ở trình duyệt (Client-side HTML5 validation).
- Escape ký tự HTML đúng chuẩn bằng `htmlspecialchars` khi đưa bất kỳ dữ liệu do người dùng nhập vào mã nguồn trang web.
