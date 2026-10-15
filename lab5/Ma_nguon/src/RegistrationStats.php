<?php

declare(strict_types=1);
require_once __DIR__ . '/Database.php';

/**
 * Thống kê số lượng đăng ký theo sự kiện bằng câu truy vấn LEFT JOIN
 *
 * @param PgSql\Connection $connection Kết nối PostgreSQL
 * @param int $eventId ID của sự kiện cần thống kê
 * @return array Mảng kết quả ['event_id' => int, 'title' => string, 'total' => int] hoặc [] nếu không tìm thấy
 */
function registrationStats(PgSql\Connection $connection, int $eventId): array
{
    // Nếu eventId không hợp lệ (<= 0), trả về mảng rỗng
    if ($eventId <= 0) {
        return [];
    }

    // Sử dụng LEFT JOIN để giữ lại sự kiện ngay cả khi chưa có lượt đăng ký nào.
    // Đếm theo COUNT(r.id) để trả về 0 khi không có đăng ký (nếu dùng COUNT(*) sẽ đếm 1 vì hàng bên events vẫn tồn tại).
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
