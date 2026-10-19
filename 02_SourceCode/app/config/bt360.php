<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),

    'locales' => [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ],

    // Thông tin bảo tàng hiển thị ở footer/liên hệ (giá trị mặc định khi bảng museums trống)
    'museum' => [
        'name' => 'Naturalis Biodiversity Center',
        'license' => 'CC BY-SA 3.0',
        'attribution' => 'Dữ liệu và hình ảnh: Naturalis Biodiversity Center, giấy phép CC BY-SA 3.0',
        'website' => 'https://www.naturalis.nl',
        'address' => 'Darwinweg 2, 2333 CR Leiden, Nederland',
        'phone' => '+31 71 751 9600',
        'email' => 'info@naturalis.nl',
    ],

    // Sơ đồ minimap do frontend cung cấp (public/minimap); tọa độ 0..1 của từng phòng khớp sơ đồ này
    'floorplan' => [
        'image_url' => '/minimap/naturalis-floorplan.svg',
        'width' => 1000,
        'height' => 700,
        'rooms' => [
            'main_hall' => [0.50, 0.85], 'dinosaur' => [0.20, 0.60], 'seduction' => [0.80, 0.60],
            'death' => [0.20, 0.35], 'live_science' => [0.80, 0.35], 'earth' => [0.35, 0.12],
            'life' => [0.65, 0.12], 'ice_age' => [0.50, 0.45],
        ],
    ],

    'analytics' => [
        'url' => env('ANALYTICS_URL', 'http://analytics:8001'),
        'token' => env('ANALYTICS_TOKEN', ''),
        'timeout' => (float) env('ANALYTICS_TIMEOUT', 2),
        'status_ttl' => 60,
        // Kết quả phân tích luồng/gợi ý tour từ Python được đệm 30 phút (NFR hiệu năng)
        'cache_minutes' => (int) env('ANALYTICS_CACHE_MINUTES', 30),
    ],

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'sandbox'),
        // Không có giá trị mặc định: entrypoint Docker sinh ngẫu nhiên và lưu trong volume nếu thiếu.
        'sandbox_secret' => (string) env('PAYMENT_SANDBOX_SECRET', ''),
        'methods' => ['card', 'bank', 'ewallet'],
    ],

    'orders' => [
        'hold_minutes' => (int) env('ORDER_HOLD_MINUTES', 15),
        // Hạn dùng liên kết tra cứu đơn (token Crypt) gửi trong thư xác nhận
        'lookup_days' => (int) env('ORDER_LOOKUP_DAYS', 90),
        // Hội viên Premium đang hiệu lực được giảm giá vé Regular (phần trăm)
        'premium_discount_percent' => 20,
        'discount_package_code' => 'regular',
        // Hủy đơn đã thanh toán (hoàn tiền) chỉ khi ngày tham quan > hôm nay + N ngày
        'refund_min_days_before' => 1,
        'max_pending_per_user' => (int) env('ORDER_MAX_PENDING_PER_USER', 3),
    ],

    'cart' => [
        'ttl_days' => 7,
        'max_items' => 10,
    ],

    'visit_days' => [
        'default_capacity' => 300,
        'calendar_days' => 60,
        'closed_weekday' => 1, // thứ Hai (Carbon: 1 = Monday)
    ],

    'events' => [
        'max_per_request' => 50,
    ],

    'tickets' => [
        // Liên kết tải vé/hóa đơn ký bằng APP_KEY, hết hạn sau N phút
        'signed_url_minutes' => 60 * 24 * 7,
    ],

    'seed_demo_password' => env('SEED_DEMO_PASSWORD', 'BaoTang@2026'),
    // Thư mục dữ liệu hạt giống JSON và tài nguyên (ảnh 360, mp3); test trỏ sang thư mục tạm
    'seed_data_path' => env('SEED_DATA_PATH', database_path('data')),
    'seed_assets_path' => env('SEED_ASSETS_PATH', database_path('seed-assets')),
    // Tải ảnh hiện vật từ URL khi seed (tắt bằng SEED_DOWNLOAD_IMAGES=false → dùng external_url)
    'seed_download_images' => (bool) env('SEED_DOWNLOAD_IMAGES', true),

    // Thư mục sao lưu CSDL (mặc định storage/app/backups)
    'backup_path' => env('BACKUP_PATH'),

    'quality_cache_minutes' => 10,

    // Bộ đệm dữ liệu trang công khai (giây); store "file" để không thêm truy vấn MySQL. TTL 0 = tắt.
    'page_cache' => [
        'store' => env('PAGE_CACHE_STORE', 'file'),
        'ttl' => (int) env('PAGE_CACHE_TTL', 60),
    ],
];
