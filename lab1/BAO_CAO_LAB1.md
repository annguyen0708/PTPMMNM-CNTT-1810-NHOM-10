# BÁO CÁO BÀI THỰC HÀNH LAB 01
## VẬN HÀNH DỰ ÁN TRÊN LINUX VÀ VIẾT SHELL SCRIPT

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
1. Làm quen và sử dụng thành thạo các câu lệnh Linux trong môi trường Ubuntu/WSL để quản lý thư mục, tệp tin và môi trường phát triển mã nguồn mở.
2. Viết shell script Bash có cấu trúc hoàn chỉnh bao gồm biến, hàm, câu lệnh điều kiện (`if-else`), vòng lặp (`for`, `while`) và xử lý mã thoát (`exit code`).
3. Tự động hóa kiểm tra công cụ (`php`, `git`) và tính toàn vẹn của dự án trước khi vận hành.
4. Xây dựng công cụ quét cú pháp đệ quy cho toàn bộ tệp `.php` trong dự án (`scan-source.sh`), xử lý đúng các trường hợp đường dẫn có khoảng trắng, thư mục con, thư mục rỗng và báo cáo tổng hợp.

---

## II. MÔI TRƯỜNG VÀ CÔNG CỤ THỰC HÀNH
- **Hệ điều hành**: Ubuntu 24.04 LTS chạy trên WSL2 (Windows 11).
- **PHP CLI**: PHP 8.5.4 (cli).
- **Git**: Git 2.53.0.
- **Thư mục làm việc**: `lab1/Ma_nguon`.

Lệnh kiểm tra môi trường:
```bash
php -v
git --version
bash lab.sh check
```
Kết quả thực tế:
```
PHP 8.5.4 (cli) (built: Sep  2 2026 14:42:18) (NTS)
git version 2.53.0
MOI_TRUONG=SAN_SANG | Lab 1
Đây không phải kết luận bài tập đã hoàn thành.
```

---

## III. QUÁ TRÌNH THỰC HIỆN VÀ MÃ NGUỒN

### 1. Script kiểm tra môi trường: `scripts/check-env.sh`
Script tự động kiểm tra xem máy đã cài đặt `php` và `git` chưa, xác minh sự tồn tại của các tệp bắt buộc (`README.md`, `public/index.php`) và kiểm tra cú pháp tệp `public/index.php` bằng lệnh `php -l`.

**Nội dung mã nguồn:**
```bash
#!/usr/bin/env bash
set -u
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)" || exit 2
fail=0

check_tool() {
  if command -v "$1" >/dev/null 2>&1; then
    printf 'OK tool %s\n' "$1"
  else
    printf 'MISSING tool %s\n' "$1" >&2
    fail=$((fail + 1))
  fi
}

for tool in php git; do check_tool "$tool"; done

for file in README.md public/index.php; do
  if [[ -f "$root/$file" ]]; then
    printf 'OK file %s\n' "$file"
  else
    printf 'MISSING file %s\n' "$file" >&2
    fail=$((fail + 1))
  fi
done

if [[ "$fail" -gt 0 ]]; then exit 2; fi

if php -l "$root/public/index.php"; then
  echo 'CHECK_ENV=PASS'
else
  echo 'CHECK_ENV=FAIL' >&2
  exit 1
fi
```

**Giải thích cơ chế hoạt động:**
- `set -u`: Ngăn chặn lỗi dùng biến chưa khai báo.
- `root=...`: Xác định đường dẫn gốc của bài lab một cách độc lập với vị trí người dùng đang đứng.
- Hàm `check_tool`: Nhận tên công cụ, dùng `command -v` kiểm tra PATH.
- Kiểm tra tệp: Dùng `[[ -f "$root/$file" ]]`.
- Mã thoát quy ước:
  - Thoát `2`: Thiếu công cụ hoặc thiếu tệp bắt buộc.
  - Thoát `1`: Lỗi cú pháp PHP (`php -l` thất bại).
  - Thoát `0`: Mọi điều kiện đều đạt chuẩn (`CHECK_ENV=PASS`).

---

### 2. Script quét mã nguồn PHP: `scripts/scan-source.sh` (Bài tập mở rộng)
Script nhận đối số là một đường dẫn thư mục, tìm đệ quy tất cả các tệp có đuôi `.php` (kể cả tệp nằm trong thư mục con và tên chứa khoảng trắng), chạy `php -l` kiểm tra cú pháp và tổng hợp số lượng đạt/lỗi.

**Nội dung mã nguồn hoàn thiện:**
```bash
#!/usr/bin/env bash
set -uo pipefail

if [[ $# -ne 1 ]]; then echo 'Usage: scan-source.sh DIRECTORY' >&2; exit 2; fi
root="$1"

# TODO 1: thiếu/không đọc được thư mục hoặc thiếu php -> exit 2.
if ! command -v php >/dev/null 2>&1; then
  echo "Lỗi: Không tìm thấy công cụ php trong PATH." >&2
  exit 2
fi

if [[ ! -d "$root" || ! -r "$root" ]]; then
  echo "Lỗi: Thư mục '$root' không tồn tại hoặc không thể đọc." >&2
  exit 2
fi

list="$(mktemp)" || exit 2
trap 'rm -f -- "$list"' EXIT
if ! find "$root" -type f -name '*.php' -print0 > "$list"; then exit 2; fi

total=0; passed=0; failed=0
while IFS= read -r -d '' file; do
  # TODO 2: tăng total; php -l "$file"; tăng passed hoặc failed.
  # Không dừng vòng lặp khi một tệp bị lỗi.
  total=$((total + 1))
  if php -l "$file" >/dev/null 2>&1; then
    passed=$((passed + 1))
  else
    failed=$((failed + 1))
  fi
done < "$list"

printf 'TOTAL=%s PASS=%s FAIL=%s\n' "$total" "$passed" "$failed"

# TODO 3: rỗng -> 2; có lỗi -> 1; tất cả đạt -> 0.
if [[ "$total" -eq 0 ]]; then
  echo "Lỗi: Thư mục '$root' không chứa tệp .php nào." >&2
  exit 2
fi

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi

exit 0
```

**Giải thích 3 vị trí TODO đã hoàn thành:**
1. **TODO 1 (Kiểm tra điều kiện tiên quyết)**:
   - Dùng `command -v php` để xác nhận PHP có trong PATH.
   - Dùng `[[ ! -d "$root" || ! -r "$root" ]]` để từ chối các đường dẫn không tồn tại hoặc không đủ quyền đọc, trả về mã thoát `2`.
2. **TODO 2 (Vòng lặp duyệt tệp và đếm)**:
   - Dùng `find ... -print0` và `IFS= read -r -d '' file` để tách tệp an toàn bằng ký tự NULL, tránh lỗi với tên thư mục/tệp chứa khoảng trắng (ví dụ `sub folder/b.php`).
   - Với mỗi tệp, tăng `total`. Chạy `php -l "$file" >/dev/null 2>&1`. Nếu mã trả về là `0` thì tăng `passed`, ngược lại tăng `failed`.
   - Vòng lặp tiếp tục chạy cho đến hết danh sách, không ngắt giữa chừng khi gặp tệp hỏng.
3. **TODO 3 (Xử lý mã thoát tổng kết)**:
   - Nếu `total -eq 0` (thư mục rỗng hoặc không có tệp `.php`): in cảnh báo và trả mã thoát `2`.
   - Nếu `failed -gt 0`: trả mã thoát `1`.
   - Nếu toàn bộ tệp hợp lệ (`failed -eq 0`): trả mã thoát `0`.

---

## IV. KẾT QUẢ KIỂM THỬ THỰC TẾ

### 1. Kiểm tra môi trường (`bash lab.sh check`)
```
MOI_TRUONG=SAN_SANG | Lab 1
Đây không phải kết luận bài tập đã hoàn thành.
```

### 2. Kiểm thử phần bài mẫu (`bash lab.sh test`)
```
MOI_TRUONG=SAN_SANG | Lab 1
Đây không phải kết luận bài tập đã hoàn thành.
OK tool php
OK tool git
OK file README.md
OK file public/index.php
No syntax errors detected in /mnt/e/PTPMMNM-CNTT-1810-NHOM-10/lab1/Ma_nguon/public/index.php
CHECK_ENV=PASS
```
-> **Mã thoát: 0 (Đạt yêu cầu)**

### 3. Kiểm thử phần mở rộng (`bash lab.sh extend`)
```
MOI_TRUONG=SAN_SANG | Lab 1
Đây không phải kết luận bài tập đã hoàn thành.
BÀI TỰ MỞ RỘNG: có ca chưa đạt trước khi bạn hoàn thiện là bình thường.
PASS valid
PASS mixed
PASS missing
PASS empty
SHELL_CASES=4 FAILED=0
```
-> **Mã thoát: 0 (Đạt 4/4 ca kiểm thử)**

### 4. Khởi chạy máy chủ Web cục bộ (`bash lab.sh run`)
- Địa chỉ: `http://127.0.0.1:8101`
- Nội dung phản hồi: Trình duyệt hiển thị tiêu đề "CommunityPass" và dòng chữ "Mã nguồn PHP đã chạy trên máy của bạn."

---

## V. DANH SÁCH HÌNH ẢNH MINH CHỨNG
1. `lab1/Hinh_anh/hinh_01.png`: Giao diện ứng dụng CommunityPass kiểm tra môi trường trên trình duyệt cổng 8101.
2. `lab1/Hinh_anh/hinh_02.png`: Sơ đồ quy trình thực hiện Lab 01.
3. *Ảnh chụp màn hình terminal*: Sinh viên chụp màn hình terminal chạy `bash lab.sh check && bash lab.sh test && bash lab.sh extend` đạt `SHELL_CASES=4 FAILED=0` để bổ sung vào báo cáo nộp cho giảng viên.

---

## VI. BÀI HỌC KINH NGHIỆM
- Nắm vững cách quản lý mã thoát chuẩn trong Unix/Linux: `0` là thành công, `1` là lỗi kiểm tra nghiệp vụ, `2` là lỗi thiếu điều kiện môi trường hoặc đầu vào không hợp lệ.
- Hiểu rõ tầm quan trọng của việc xử lý ký tự NULL (`-print0` / `read -d ''`) khi làm việc với đường dẫn có khoảng trắng.
- Tự động hóa kiểm thử giúp phát hiện sớm các lỗi cú pháp mã nguồn trước khi tích hợp vào hệ thống lớn hơn.
