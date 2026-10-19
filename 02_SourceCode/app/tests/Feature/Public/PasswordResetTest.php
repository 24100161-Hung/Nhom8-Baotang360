<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/vi/quen-mat-khau')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/ForgotPassword'));
    }

    public function test_reset_link_is_sent_with_localized_url(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@baotang360.test', 'locale' => 'en']);

        $this->post('/vi/quen-mat-khau', ['email' => 'reset@baotang360.test'])->assertSessionHas('status', __('passwords.sent_generic'));

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_contains($url, '/en/dat-lai-mat-khau/'.$n->token);
        });
    }

    public function test_unknown_email_gets_same_generic_message(): void
    {
        Notification::fake();
        $this->post('/vi/quen-mat-khau', ['email' => 'nobody@baotang360.test'])->assertSessionHas('status', __('passwords.sent_generic'));
        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@baotang360.test']);
        $token = Password::createToken($user);

        $this->get('/vi/dat-lai-mat-khau/'.$token.'?email=reset@baotang360.test')
            ->assertInertia(fn (Assert $p) => $p->component('Auth/ResetPassword')->where('token', $token)->where('email', 'reset@baotang360.test'));
        $this->post('/vi/dat-lai-mat-khau', ['token' => $token, 'email' => 'reset@baotang360.test', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertRedirect(localized_route('login', ['locale' => 'vi']));
        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }

    public function test_invalid_token_or_weak_password_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'reset@baotang360.test']);
        $this->post('/vi/dat-lai-mat-khau', ['token' => 'bogus', 'email' => 'reset@baotang360.test', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasErrors('email');
        $this->post('/vi/dat-lai-mat-khau', ['token' => Password::createToken($user), 'email' => 'reset@baotang360.test', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
