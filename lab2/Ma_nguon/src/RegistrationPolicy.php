<?php
declare(strict_types=1);

final class RegistrationPolicy {
    /**
     * Quyết định trạng thái đăng ký của người dùng
     *
     * @param int $capacity Sức chứa tối đa của sự kiện
     * @param int $registered Số người hiện đã đăng ký
     * @param bool $open Trạng thái mở/đóng của sự kiện
     * @param bool $waitingEnabled Cho phép danh sách chờ hay không
     * @return string 'confirmed' | 'waiting' | 'rejected'
     */
    public function decide(int $capacity, int $registered,
        bool $open, bool $waitingEnabled): string {
        // Kiểm tra tính hợp lệ của dữ liệu đầu vào:
        // Sức chứa <= 0, số người đã đăng ký âm hoặc vượt quá sức chứa -> rejected
        if ($capacity <= 0 || $registered < 0 || $registered > $capacity) {
            return 'rejected';
        }

        // Dữ liệu hợp lệ nhưng sự kiện đã đóng -> rejected (kể cả còn chỗ)
        if (!$open) {
            return 'rejected';
        }

        // Sự kiện đang mở và còn chỗ -> confirmed
        if ($registered < $capacity) {
            return 'confirmed';
        }

        // Sự kiện đang mở và đã hết chỗ (registered === capacity)
        if ($waitingEnabled) {
            return 'waiting';
        }

        return 'rejected';
    }
}
