<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/** 6 tài khoản mẫu (mật khẩu bt360.seed_demo_password); premium@ nhận hội viên ở DemoActivitySeeder. */
class UserSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['admin@baotang360.test', 'Quản trị viên BaoTang360', Role::ADMIN, '0912000001'],
        ['curator@baotang360.test', 'Người phụ trách nội dung', Role::CURATOR, '0912000002'],
        ['visitor@baotang360.test', 'Nguyễn Văn Khách', Role::VISITOR, '0912345678'],
        ['premium@baotang360.test', 'Trần Thị Hội Viên', Role::VISITOR, '0912345679'],
        ['visitor2@baotang360.test', 'Lê Minh Tham Quan', Role::VISITOR, '0912345680'],
        ['visitor3@baotang360.test', 'Phạm Thu Bảo Tàng', Role::VISITOR, '0912345681'],
    ];

    public function run(): void
    {
        $password = (string) config('bt360.seed_demo_password', 'BaoTang@2026');
        $roles = Role::pluck('id', 'code');

        foreach (self::ACCOUNTS as [$email, $name, $role, $phone]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill([
                'name' => $name,
                'role_id' => $roles[$role],
                'phone' => $phone,
                'locale' => $user->locale ?: 'vi',
                'theme' => $user->theme ?: 'light',
                'is_active' => true,
            ]);
            if (! $user->exists) {
                $user->password = $password;
                $user->email_verified_at = now();
            }
            $user->save();
        }
    }
}
