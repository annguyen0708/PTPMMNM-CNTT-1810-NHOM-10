<?php
declare(strict_types=1);

/**
 * Lọc danh sách sự kiện theo từ khóa, loại sự kiện và trạng thái còn chỗ
 *
 * @param array $events Mảng danh sách sự kiện
 * @param string $query Từ khóa tìm kiếm theo tên sự kiện (không phân biệt hoa thường)
 * @param string $category Thể loại sự kiện (để trống nếu muốn lấy tất cả)
 * @param bool $availableOnly Chỉ lấy các sự kiện còn chỗ (capacity > registered)
 * @return array Mảng sự kiện sau khi lọc (mảng mới, không thay đổi mảng gốc)
 */
function filterEvents(array $events, string $query, string $category = '',
    bool $availableOnly = false): array
{
    $result = [];
    foreach ($events as $event) {
        // 1. Điều kiện từ khóa: rỗng hoặc xuất hiện trong tiêu đề sự kiện
        $matchesQuery = ($query === '') || (stripos($event['title'], $query) !== false);

        // 2. Điều kiện thể loại: rỗng hoặc trùng khớp với loại sự kiện
        $matchesCategory = ($category === '') || ($event['category'] === $category);

        // 3. Điều kiện còn chỗ: không yêu cầu lọc còn chỗ HOẶC số chỗ còn lại > 0
        $matchesAvailability = !$availableOnly || (($event['capacity'] - $event['registered']) > 0);

        // Kết hợp cả 3 điều kiện bằng toán tử logic AND
        if ($matchesQuery && $matchesCategory && $matchesAvailability) {
            $result[] = $event;
        }
    }
    return $result;
}
