<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public const ROLES = [
        Role::ADMIN => ['Quản trị viên', 'Toàn quyền: dashboard, mọi CRUD, tài khoản, gói vé, đơn hàng, nhật ký, thống kê'],
        Role::CURATOR => ['Người phụ trách nội dung', 'CRUD hiện vật, không gian 360, hotspot, tour, audio, blog; kiểm duyệt đánh giá; xem thống kê'],
        Role::VISITOR => ['Khách tham quan', 'Tham quan 360, yêu thích, đánh giá, giỏ hàng, mua vé, hội viên Premium'],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $code => [$name, $description]) {
            Role::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $description]);
        }
    }
}
