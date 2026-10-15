<?php
declare(strict_types=1);

/**
 * Kiểm tra xem bản nháp trong phiên có đủ điều kiện để hoàn tất đăng ký hay không
 *
 * @param array $session Mảng phiên ($_SESSION)
 * @param int $requestedEventId ID sự kiện đang yêu cầu hoàn tất
 * @param int $now Thời điểm hiện tại (timestamp dạng giây)
 * @param int $ttl Thời gian sống tối đa của bản nháp (Time To Live, tính bằng giây)
 * @return bool True nếu bản nháp hợp lệ và đủ điều kiện, ngược lại False
 */
function canComplete(array $session, int $requestedEventId, int $now, int $ttl): bool {
    // 1. Thời gian sống TTL phải là số dương
    if ($ttl <= 0) {
        return false;
    }

    // 2. Phải có bản nháp 'draft' tồn tại và có cấu trúc hợp lệ trong Session
    if (!isset($session['draft']) || !is_array($session['draft'])) {
        return false;
    }

    $draft = $session['draft'];
    if (!isset($draft['data']) || !is_array($draft['data']) || !isset($draft['created_at'])) {
        return false;
    }

    // 3. ID sự kiện yêu cầu phải trùng khớp với ID sự kiện trong bản nháp
    $draftEventId = (int)($draft['data']['event_id'] ?? 0);
    if ($draftEventId !== $requestedEventId) {
        return false;
    }

    // 4. Thời điểm tạo không được ở tương lai so với thời điểm kiểm tra
    $createdAt = (int)$draft['created_at'];
    if ($createdAt > $now) {
        return false;
    }

    // 5. Tuổi của bản nháp ($now - $createdAt) phải nhỏ hơn thời gian sống TTL.
    // Đúng ngưỡng hết hạn ($now - $createdAt >= $ttl) phải trả về false.
    if (($now - $createdAt) >= $ttl) {
        return false;
    }

    return true;
}
