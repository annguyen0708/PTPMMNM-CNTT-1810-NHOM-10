# BÁO CÁO BÀI THỰC HÀNH LAB 04
## QUẢN LÝ BẢN NHÁP BẰNG SESSION VÀ COOKIE

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
1. Phân biệt rõ ràng giữa cơ chế lưu trữ **Session** (lưu dữ liệu tạm thời, nhạy cảm phía máy chủ, định danh qua Session ID) và **Cookie** (lưu trữ phía trình duyệt client, dùng cho các cấu hình hiển thị phi nhạy cảm).
2. Xây dựng hoàn chỉnh luồng xử lý biểu mẫu nhiều bước theo mô hình PRG (Post/Redirect/Get):
   - Bước 1: Nhập thông tin và kiểm tra hợp lệ $\rightarrow$ Lưu bản nháp vào Session $\rightarrow$ Chuyển hướng sang trang xác nhận.
   - Bước 2: Xem lại thông tin xác nhận (cho phép quay lại sửa hoặc hủy bỏ bản nháp).
   - Bước 3: Hoàn tất mô phỏng (`SIMULATED_OK`) $\rightarrow$ Xóa bản nháp khỏi Session.
3. Cài đặt các cơ chế bảo mật quan trọng:
   - Phòng chống tấn công CSRF (Cross-Site Request Forgery) bằng token ngẫu nhiên gắn trong Session.
   - Kiểm soát vòng đời và thời hạn hiệu lực của bản nháp (TTL - Time To Live).
4. Xây dựng hàm kiểm soát bảo vệ `canComplete` (`DraftGuard.php`) để ngăn chặn việc gửi yêu cầu xác nhận sai sự kiện, nháp hết hạn, hoặc nháp tạo ở tương lai.

---

## II. MÔI TRƯỜNG VÀ CÔNG CỤ THỰC HÀNH
- **Hệ điều hành**: Ubuntu 24.04 LTS trên WSL2.
- **PHP CLI & Web Server**: PHP 8.5.4.
- **Git**: Git 2.53.0.
- **Thư mục làm việc**: `lab4/Ma_nguon`.
- **Cổng Web**: `http://127.0.0.1:8104`.

---

## III. QUÁ TRÌNH THỰC HIỆN VÀ MÃ NGUỒN

### 1. Quản lý bản nháp Session: `src/Draft.php`
Bản nháp trong Session gồm thông tin đăng ký (`data`) và thời điểm tạo (`created_at`).
```php
<?php
declare(strict_types=1);

function saveDraft(array &$session, array $data, int $now): void {
    $session['draft'] = ['data'=>$data, 'created_at'=>$now];
    unset($session['completed']);
}

function draftExpired(array $session, int $now, int $ttl): bool {
    if (!isset($session['draft'])) { return true; }
    return $now - $session['draft']['created_at'] >= $ttl;
}

function cancelDraft(array &$session): void {
    unset($session['draft']);
}
```

### 2. Kiểm soát bảo vệ bản nháp: `src/DraftGuard.php` (Bài tập mở rộng)
Hàm `canComplete` kiểm tra nghiêm ngặt 5 điều kiện tiên quyết trước khi cho phép người dùng hoàn tất đăng ký:
1. `ttl > 0` (Thời gian sống phải là số dương).
2. Tồn tại bản nháp `draft` hợp lệ trong Session.
3. ID sự kiện gửi lên từ Form phải trùng khớp với ID sự kiện đã lưu trong bản nháp.
4. Thời điểm tạo `created_at` không được nằm ở tương lai so với thời điểm hiện tại `now`.
5. Tuổi của bản nháp ($now - created\_at$) phải nhỏ hơn TTL. Tại đúng ngưỡng ($now - created\_at \ge ttl$) được tính là đã hết hạn.

```php
<?php
declare(strict_types=1);

function canComplete(array $session, int $requestedEventId, int $now, int $ttl): bool {
    if ($ttl <= 0) {
        return false;
    }
    if (!isset($session['draft']) || !is_array($session['draft'])) {
        return false;
    }
    $draft = $session['draft'];
    if (!isset($draft['data']) || !is_array($draft['data']) || !isset($draft['created_at'])) {
        return false;
    }
    $draftEventId = (int)($draft['data']['event_id'] ?? 0);
    if ($draftEventId !== $requestedEventId) {
        return false;
    }
    $createdAt = (int)$draft['created_at'];
    if ($createdAt > $now) {
        return false;
    }
    if (($now - $createdAt) >= $ttl) {
        return false;
    }
    return true;
}
```

### 3. Tích hợp kiểm soát vào luồng xác nhận và hoàn tất
- **Tại `public/confirm.php`**: Bổ sung trường ẩn `event_id` vào Form xác nhận để truyền lên máy chủ đối chiếu:
  ```html
  <input type="hidden" name="event_id" value="<?= (int)$data['event_id'] ?>">
  ```
- **Tại `public/complete.php`**: Gọi hàm `canComplete` trước khi hoàn tất đăng ký. Nếu phát hiện vi phạm, hủy bản nháp và chuyển hướng người dùng về trang đăng ký ban đầu:
  ```php
  if (!canComplete($_SESSION, (int)field($_POST, 'event_id'), time(), 1800)) {
      cancelDraft($_SESSION);
      header('Location: /register.php', true, 303);
      exit;
  }
  ```

### 4. Quản lý tùy chọn hiển thị bằng Cookie: `public/preferences.php`
Lưu tùy chọn xem giao diện (`compact` hoặc `full`) vào Cookie với thời hạn 1 giờ và cờ an toàn (`httponly`, `samesite=Lax`):
- Cookie hoàn toàn không lưu thông tin cá nhân hay quyền hạn nhạy cảm.

---

## IV. KẾT QUẢ KIỂM THỬ THỰC TẾ

### 1. Kiểm tra môi trường (`bash lab.sh check`)
```
MOI_TRUONG=SAN_SANG | Lab 4
Đây không phải kết luận bài tập đã hoàn thành.
```

### 2. Kiểm thử phần bài mẫu (`bash lab.sh test`)
```
MOI_TRUONG=SAN_SANG | Lab 4
Đây không phải kết luận bài tập đã hoàn thành.
PASS saved
PASS before-expiry
PASS at-expiry
PASS cancelled
PASS unrelated-state-kept
PASS no-draft
TESTS=6 FAILED=0
```
-> **6/6 ca kiểm thử đạt.**

### 3. Kiểm thử phần mở rộng bảo vệ bản nháp (`bash lab.sh extend`)
```
MOI_TRUONG=SAN_SANG | Lab 4
Đây không phải kết luận bài tập đã hoàn thành.
BÀI TỰ MỞ RỘNG: có ca chưa đạt trước khi bạn hoàn thiện là bình thường.
PASS valid
PASS missing
PASS wrong-event
PASS before-expiry
PASS at-expiry
PASS future-created-at
PASS invalid-ttl
TESTS=7 FAILED=0
```
-> **7/7 ca kiểm thử đạt.**

### 4. Luồng người dùng trên Web (`http://127.0.0.1:8104`)
- Đăng ký hợp lệ $\rightarrow$ chuyển hướng sang `confirm.php` hiển thị đúng thông tin.
- Nhấn "Sửa lại" $\rightarrow$ quay về `register.php`, dữ liệu đã nhập được giữ nguyên trong các ô input.
- Nhấn "Hủy bản nháp" $\rightarrow$ xóa sạch dữ liệu nháp trong Session.
- Nhấn "Xác nhận" $\rightarrow$ chuyển sang `complete.php` hiển thị `SIMULATED_OK`.
- Tải lại trang hoàn tất $\rightarrow$ vẫn giữ trạng thái `SIMULATED_OK` mà không bị submit trùng lặp.

---

## V. MINH CHỨNG VÀ HÌNH ẢNH
1. `lab4/Hinh_anh/hinh_01.png`: Màn hình giao diện xác nhận thông tin đăng ký của hệ thống.
2. `lab4/Hinh_anh/hinh_02.png`: Sơ đồ quy trình thực hiện Lab 04.
3. *Ảnh chụp màn hình đề xuất*: Sinh viên mở trình duyệt, thực hiện đầy đủ luồng Nhập $\rightarrow$ Xác nhận $\rightarrow$ Hoàn tất `SIMULATED_OK` và chụp lại màn hình kết quả kèm tab Cookies/Session trong DevTools.

---

## VI. BÀI HỌC KINH NGHIỆM
- Nắm vững mô hình Post/Redirect/Get (PRG) để tránh tình trạng người dùng vô tình gửi lại dữ liệu khi nhấn F5/Refresh trình duyệt.
- Quản lý phiên Session an toàn: luôn kiểm tra CSRF token cho các thao tác POST và kiểm tra thời hạn sống của dữ liệu (TTL).
- Không lạm dụng `session_destroy()` khi người dùng chỉ muốn hủy một bản nháp đơn lẻ, nhằm bảo toàn các trạng thái phiên khác (như CSRF token hay thông tin đăng nhập).
