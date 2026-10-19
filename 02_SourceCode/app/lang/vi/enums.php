<?php

return [
    'roles' => ['admin' => 'Quản trị viên', 'curator' => 'Người phụ trách nội dung', 'visitor' => 'Khách tham quan'],
    'statuses' => ['draft' => 'Bản nháp', 'published' => 'Đã công bố'],
    'hotspot_types' => ['info' => 'Thông tin', 'scene' => 'Chuyển phòng', 'artifact' => 'Hiện vật'],
    'difficulties' => ['de' => 'Dễ', 'vua' => 'Vừa', 'kho' => 'Khó'],
    'tour_modes' => ['free' => 'Tự do', 'guided' => 'Có hướng dẫn', 'both' => 'Tự do và có hướng dẫn'],
    'package_types' => ['single' => 'Vé lượt', 'membership' => 'Hội viên'],
    'visit_day_statuses' => ['open' => 'Còn vé', 'sold_out' => 'Hết vé', 'closed' => 'Đóng cửa'],
    'order_statuses' => [
        'pending' => 'Chờ thanh toán', 'paid' => 'Đã thanh toán', 'cancelled' => 'Đã hủy',
        'expired' => 'Hết hạn giữ chỗ', 'refunded' => 'Đã hoàn tiền',
    ],
    'payment_statuses' => [
        'initiated' => 'Khởi tạo', 'succeeded' => 'Thành công', 'failed' => 'Thất bại', 'cancelled' => 'Đã hủy', 'refunded' => 'Đã hoàn tiền',
    ],
    'payment_methods' => ['card' => 'Thẻ ngân hàng', 'bank' => 'Chuyển khoản', 'ewallet' => 'Ví điện tử'],
    'ticket_statuses' => ['valid' => 'Còn hiệu lực', 'used' => 'Đã sử dụng', 'void' => 'Đã hủy'],
    'membership_statuses' => ['active' => 'Đang hiệu lực', 'expired' => 'Hết hạn', 'cancelled' => 'Đã hủy'],
    'review_statuses' => ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'],
    'guestbook_statuses' => ['pending' => 'Chờ duyệt', 'approved' => 'Hiển thị', 'hidden' => 'Đã ẩn'],
    'contact_statuses' => ['new' => 'Mới', 'read' => 'Đã đọc', 'replied' => 'Đã trả lời'],
    'blog_categories' => ['kham_pha' => 'Khám phá', 'su_kien' => 'Sự kiện', 'khoa_hoc' => 'Khoa học'],
    'tag_groups' => ['chat_lieu' => 'Chất liệu', 'nguon_goc' => 'Nguồn gốc', 'nhom_sinh_vat' => 'Nhóm sinh vật', 'chu_de' => 'Chủ đề', 'phong' => 'Phòng trưng bày'],
    'media_types' => ['image' => 'Hình ảnh', 'audio' => 'Âm thanh', 'video' => 'Video', 'panorama' => 'Ảnh 360°', 'document' => 'Tài liệu'],
    'devices' => ['mobile' => 'Điện thoại', 'tablet' => 'Máy tính bảng', 'desktop' => 'Máy tính'],
    'tiles_states' => ['none' => 'Chưa sinh', 'pending' => 'Đang chờ', 'running' => 'Đang sinh', 'done' => 'Hoàn tất', 'error' => 'Lỗi'],
    'event_types' => [
        'scene_enter' => 'Vào phòng', 'scene_leave' => 'Rời phòng', 'hotspot_click' => 'Bấm điểm nóng', 'tour_start' => 'Bắt đầu tour',
        'tour_step' => 'Bước tour', 'tour_complete' => 'Hoàn thành tour', 'tour_abandon' => 'Bỏ tour', 'artifact_view' => 'Xem hiện vật',
        'audio_play' => 'Nghe thuyết minh',
    ],
    'taxa' => [
        'dinosauria' => 'Khủng long', 'mammalia' => 'Thú', 'aves' => 'Chim', 'reptilia' => 'Bò sát', 'amphibia' => 'Lưỡng cư',
        'pisces' => 'Cá', 'insecta' => 'Côn trùng', 'arthropoda' => 'Chân khớp', 'mollusca' => 'Thân mềm', 'plantae' => 'Thực vật',
        'fossil_plant' => 'Hóa thạch thực vật', 'mineral' => 'Khoáng vật', 'meteorite' => 'Thiên thạch', 'hominini' => 'Họ người', 'other' => 'Khác',
        'crustacea' => 'Giáp xác', 'fossil_invertebrate' => 'Hóa thạch không xương sống', 'fossil_vertebrate' => 'Hóa thạch có xương sống',
        'fungi' => 'Nấm', 'human' => 'Con người', 'rock' => 'Đá', 'invertebrata' => 'Động vật không xương sống',
    ],
    'licenses' => [
        'cc_by_sa_3' => 'CC BY-SA 3.0', 'cc_by_sa_4' => 'CC BY-SA 4.0', 'cc_by_sa_2' => 'CC BY-SA 2.0', 'cc_by_3' => 'CC BY 3.0',
        'cc_by_4' => 'CC BY 4.0', 'cc_by_2' => 'CC BY 2.0', 'cc0' => 'CC0 (phạm vi công cộng)', 'public_domain' => 'Phạm vi công cộng',
        'cc_by' => 'CC BY', 'cc_by_sa' => 'CC BY-SA', 'cc_by_nc' => 'CC BY-NC', 'all_rights_reserved' => 'Bảo lưu mọi quyền',
        'simulated' => 'Minh họa mô phỏng (do nhóm tạo)', 'other' => 'Khác',
    ],
    'audio_locales' => ['vi' => 'Tiếng Việt', 'en' => 'English', 'de' => 'Deutsch', 'nl' => 'Nederlands'],
];
