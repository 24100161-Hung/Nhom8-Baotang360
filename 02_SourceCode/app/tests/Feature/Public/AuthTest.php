<?php

namespace Tests\Feature\Public;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const VALID = ['name' => 'Nguyễn Văn A', 'email' => 'new@baotang360.test', 'phone' => '0912345678', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'];

    public function test_login_page_renders(): void
    {
        $this->get('/vi/dang-nhap')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/Login')->where('auth.user', null));
    }

    public function test_login_success_regenerates_session_and_merges_guest_cart_key(): void
    {
        $user = User::factory()->visitor()->create(['email' => 'user@baotang360.test']);
        $this->get('/vi/dang-nhap');
        $old = session()->getId();

        $this->post('/vi/dang-nhap', ['email' => 'USER@baotang360.test', 'password' => 'password'])
            ->assertRedirect(localized_route('account.dashboard', ['locale' => 'vi']));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($old, session()->getId());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_failed_login_writes_nghiepvu_log_without_email_or_password(): void
    {
        $dir = storage_path('logs/test-'.uniqid());
        config(['logging.channels.nghiepvu.path' => $dir.'/nghiepvu.log']);
        Log::forgetChannel('nghiepvu');
        User::factory()->create(['email' => 'secret.person@baotang360.test']);

        $this->post('/vi/dang-nhap', ['email' => 'secret.person@baotang360.test', 'password' => 'WrongPass99'])->assertSessionHasErrors('email');

        $files = glob($dir.'/nghiepvu-*.log');
        $this->assertCount(1, $files, 'daily file nghiepvu-YYYY-MM-DD.log');
        $content = (string) file_get_contents($files[0]);
        File::deleteDirectory($dir);
        $this->assertStringContainsString('WARNING: auth.login_failed', $content);
        $this->assertStringNotContainsString('secret.person', $content);
        $this->assertStringNotContainsString('WrongPass99', $content);
        $this->assertStringNotContainsString('secret.person', (string) json_encode(AuditLog::where('action', 'auth.login_failed')->first()->meta));
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        User::factory()->create(['email' => 'user@baotang360.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/vi/dang-nhap', ['email' => 'user@baotang360.test', 'password' => 'wrong'.$i]);
        }
        $this->post('/vi/dang-nhap', ['email' => 'user@baotang360.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'locked@baotang360.test']);
        $this->post('/vi/dang-nhap', ['email' => 'locked@baotang360.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->post('/vi/dang-xuat')->assertRedirect(localized_route('home', ['locale' => 'vi']));
        $this->assertGuest();
    }

    public function test_register_creates_visitor_and_ignores_role_input(): void
    {
        $this->post('/vi/dang-ky', self::VALID + ['role' => 'admin', 'role_id' => 1])->assertRedirect();
        $user = User::where('email', 'new@baotang360.test')->firstOrFail();
        $this->assertSame(Role::VISITOR, $user->role->code);
        $this->assertSame('0912345678', $user->phone);
        $this->assertAuthenticatedAs($user);
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->post('/vi/dang-ky', array_merge(self::VALID, ['password' => 'password', 'password_confirmation' => 'password']))
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_register_validates_email_phone_and_duplicates(): void
    {
        User::factory()->create(['email' => 'taken@baotang360.test']);
        $this->post('/vi/dang-ky', array_merge(self::VALID, ['email' => 'taken@baotang360.test', 'phone' => 'abc']))
            ->assertSessionHasErrors(['email', 'phone']);
        $this->post('/vi/dang-ky', array_merge(self::VALID, ['email' => 'not-an-email', 'name' => '']))
            ->assertSessionHasErrors(['email', 'name']);
    }
}
