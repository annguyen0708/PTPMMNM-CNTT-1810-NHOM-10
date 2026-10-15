# BÁO CÁO BÀI THỰC HÀNH LAB 02
## XÂY DỰNG MODULE PHP KIỂM TRA ĐIỀU KIỆN ĐĂNG KÝ

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
1. Hiểu và áp dụng kiến thức lập trình hướng đối tượng (OOP) căn bản trong PHP (khai báo lớp, thuộc tính, phương thức, kiểu dữ liệu nghiêm ngặt `declare(strict_types=1)`).
2. Xây dựng các module nghiệp vụ độc lập hoàn toàn với tầng giao diện HTML/CSS và tầng lưu trữ CSDL/Session để tăng khả năng tái sử dụng và kiểm thử tự động.
3. Cài đặt các quy tắc kiểm tra logic điều kiện đăng ký tham gia sự kiện dựa trên sức chứa (`CapacityPolicy`) và trạng thái sự kiện (`RegistrationPolicy`).
4. Viết và mở rộng bộ kiểm thử tự động (Unit Test đơn giản) để bao phủ các trường hợp đúng, sai và giá trị biên (edge cases).

---

## II. MÔI TRƯỜNG VÀ CÔNG CỤ THỰC HÀNH
- **Hệ điều hành**: Ubuntu 24.04 LTS trên WSL2.
- **PHP CLI**: PHP 8.5.4.
- **Git**: Git 2.53.0.
- **Thư mục làm việc**: `lab2/Ma_nguon`.

Lệnh kiểm tra môi trường:
```bash
php -v
bash lab.sh check
```
Kết quả thực tế:
```
PHP 8.5.4 (cli) (built: Sep  2 2026 14:42:18) (NTS)
MOI_TRUONG=SAN_SANG | Lab 2
Đây không phải kết luận bài tập đã hoàn thành.
```

---

## III. QUÁ TRÌNH THỰC HIỆN VÀ MÃ NGUỒN

### 1. Module kiểm tra sức chứa: `src/CapacityPolicy.php`
Lớp `CapacityPolicy` có phương thức `canRegister(int $capacity, int $registered): bool`:
- Kiểm tra tính hợp lệ: Sức chứa phải $> 0$ và số lượng đã đăng ký phải $\ge 0$.
- Điều kiện còn chỗ: Số lượng đã đăng ký phải nhỏ hơn sức chứa (`$registered < $capacity`).

```php
<?php
declare(strict_types=1);

final class CapacityPolicy
{
    public function canRegister(int $capacity, int $registered): bool
    {
        if ($capacity <= 0 || $registered < 0) {
            return false;
        }
        return $registered < $capacity;
    }
}
```

### 2. Chương trình minh họa: `demo.php`
Chương trình khởi tạo đối tượng `CapacityPolicy`, gọi phương thức `canRegister(10, 9)` và in kết quả:
```php
<?php
require __DIR__ . '/src/CapacityPolicy.php';

$policy = new CapacityPolicy();
$available = $policy->canRegister(10, 9);
if ($available) {
    echo "AVAILABLE\n";
} else {
    echo "FULL\n";
}
```

### 3. Module mở rộng kiểm tra 3 trạng thái: `src/RegistrationPolicy.php`
Phương thức `decide(int $capacity, int $registered, bool $open, bool $waitingEnabled): string` đưa ra một trong ba quyết định:
1. `'rejected'`: Khi dữ liệu sai ($capacity \le 0$, $registered < 0$, $registered > capacity$), hoặc khi sự kiện đóng (`!$open`), hoặc khi hết chỗ nhưng không bật danh sách chờ (`!$waitingEnabled`).
2. `'confirmed'`: Khi sự kiện mở và vẫn còn chỗ ($registered < capacity$).
3. `'waiting'`: Khi sự kiện mở, đã đầy chỗ ($registered == capacity$) và có bật danh sách chờ (`$waitingEnabled == true`).

**Mã nguồn hoàn thiện:**
```php
<?php
declare(strict_types=1);

final class RegistrationPolicy {
    public function decide(int $capacity, int $registered,
        bool $open, bool $waitingEnabled): string {
        // Kiểm tra tính hợp lệ của dữ liệu đầu vào
        if ($capacity <= 0 || $registered < 0 || $registered > $capacity) {
            return 'rejected';
        }

        // Sự kiện đã đóng
        if (!$open) {
            return 'rejected';
        }

        // Sự kiện mở và còn chỗ
        if ($registered < $capacity) {
            return 'confirmed';
        }

        // Sự kiện mở, hết chỗ, xét danh sách chờ
        if ($waitingEnabled) {
            return 'waiting';
        }

        return 'rejected';
    }
}
```

### 4. Bổ sung các ca kiểm thử: `tests/extension.php`
Theo yêu cầu đề bài, nhóm đã bổ sung thêm 2 ca kiểm thử đặc thù:
1. `same($p->decide(-5, 0, true, true), 'rejected', 'negative-capacity');`:
   - **Mục đích**: Kiểm tra trường hợp sức chứa bị gán giá trị âm để đảm bảo hệ thống không bị lỗi logic và lập tức từ chối yêu cầu.
2. `same($p->decide(10, 10, false, true), 'rejected', 'closed-and-full');`:
   - **Mục đích**: Xác minh thứ tự ưu tiên nghiệp vụ — nếu sự kiện đã đóng thì phải từ chối ngay lập tức, không được phép chuyển vào danh sách chờ dù cờ `waitingEnabled` là `true`.

---

## IV. KẾT QUẢ CHẠY THỰC TẾ TRÊN TERMINAL

### 1. Kiểm tra môi trường (`bash lab.sh check`)
```
MOI_TRUONG=SAN_SANG | Lab 2
Đây không phải kết luận bài tập đã hoàn thành.
```

### 2. Chạy chương trình mẫu (`bash lab.sh run`)
```
AVAILABLE
```

### 3. Chạy bộ kiểm thử bài mẫu (`bash lab.sh test`)
```
MOI_TRUONG=SAN_SANG | Lab 2
Đây không phải kết luận bài tập đã hoàn thành.
PASS one-seat-left
PASS full
PASS over-capacity
PASS invalid-capacity
PASS invalid-count
TESTS=5 FAILED=0
```
-> **5/5 ca kiểm thử đạt.**

### 4. Chạy bộ kiểm thử mở rộng (`bash lab.sh extend`)
```
MOI_TRUONG=SAN_SANG | Lab 2
Đây không phải kết luận bài tập đã hoàn thành.
BÀI TỰ MỞ RỘNG: có ca chưa đạt trước khi bạn hoàn thiện là bình thường.
PASS available
PASS full-wait
PASS full-no-wait
PASS closed
PASS invalid-capacity
PASS invalid-count
PASS corrupt-over-count
PASS first-seat
PASS negative-capacity
PASS closed-and-full
TESTS=10 FAILED=0
```
-> **10/10 ca kiểm thử đạt (gồm 8 ca gốc + 2 ca bổ sung).**

---

## V. MINH CHỨNG VÀ HÌNH ẢNH
1. `lab2/Hinh_anh/hinh_01.png`: Sơ đồ quy trình thực hiện Lab 02.
2. *Ảnh chụp màn hình terminal*: Sinh viên chạy lệnh `bash lab.sh run && bash lab.sh test && bash lab.sh extend` trên Ubuntu/WSL, chụp toàn bộ output đạt `TESTS=10 FAILED=0` để bổ sung vào báo cáo.

---

## VI. BÀI HỌC KINH NGHIỆM
- Thiết kế module nghiệp vụ tách biệt hoàn toàn với HTML giúp kiểm thử hồi quy dễ dàng mà không cần giả lập môi trường web phức tạp.
- Sử dụng `strict_types=1` giúp kiểm soát chặt chẽ kiểu dữ liệu đầu vào, tránh lỗi ngầm do ép kiểu tự động của PHP.
- Phân tích cẩn thận thứ tự ưu tiên của điều kiện (kiểm tra tính hợp lệ dữ liệu $\rightarrow$ kiểm tra trạng thái sự kiện $\rightarrow$ kiểm tra sức chứa và danh sách chờ) để tránh lỗi logic nghiệp vụ.
