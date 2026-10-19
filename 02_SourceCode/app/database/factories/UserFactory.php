<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\Role;
use App\Models\TicketPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    private const ROLE_NAMES = [
        Role::ADMIN => 'Quản trị viên',
        Role::CURATOR => 'Người phụ trách nội dung',
        Role::VISITOR => 'Khách tham quan',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => fn () => self::roleId(Role::VISITOR),
            'locale' => 'vi',
            'theme' => 'light',
            'phone' => '09'.fake()->numerify('########'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public static function roleId(string $code): int
    {
        return Role::firstOrCreate(['code' => $code], ['name' => self::ROLE_NAMES[$code] ?? $code])->id;
    }

    public function withRole(string $code): static
    {
        return $this->state(fn () => ['role_id' => self::roleId($code)]);
    }

    public function admin(): static
    {
        return $this->withRole(Role::ADMIN);
    }

    public function curator(): static
    {
        return $this->withRole(Role::CURATOR);
    }

    public function visitor(): static
    {
        return $this->withRole(Role::VISITOR);
    }

    /** Khách có hội viên Premium còn hạn 300 ngày. */
    public function premium(): static
    {
        return $this->visitor()->afterCreating(function (User $user) {
            $package = TicketPackage::query()->where('code', 'premium')->first()
                ?? TicketPackage::factory()->premium()->create();
            Membership::create([
                'user_id' => $user->id, 'package_id' => $package->id,
                'starts_at' => now()->subDays(65), 'ends_at' => now()->addDays(300), 'status' => 'active',
            ]);
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
