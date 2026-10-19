<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoCommunitySeeder;
use Database\Seeders\Demo\DemoOrderSeeder;
use Database\Seeders\Demo\DemoVisitEventSeeder;
use Illuminate\Database\Seeder;

/** Hoạt động mẫu: đơn hàng + vé + hội viên, đánh giá/sổ lưu bút/liên hệ, sự kiện tham quan 30 ngày. */
class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DemoOrderSeeder::class, DemoCommunitySeeder::class, DemoVisitEventSeeder::class]);
    }
}
