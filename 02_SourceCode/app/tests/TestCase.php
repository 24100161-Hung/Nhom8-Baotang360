<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase xóa sạch CSDL: chỉ cho phép chạy trên CSDL có hậu tố _test (chống xóa nhầm dữ liệu thật)
        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_contains($database, '_test')) {
            throw new RuntimeException("Refusing to run tests on non-test database [{$database}].");
        }

        // Không cần build Vite và không gọi mạng thật khi test
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function curator(): User
    {
        return User::factory()->curator()->create();
    }

    protected function visitor(): User
    {
        return User::factory()->visitor()->create();
    }

    protected function premium(): User
    {
        return User::factory()->premium()->create();
    }
}
