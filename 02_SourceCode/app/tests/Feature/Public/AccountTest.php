<?php

namespace Tests\Feature\Public;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use BuildsMuseumContent, RefreshDatabase;

    public function test_dashboard_has_contract_props_including_membership(): void
    {
        $this->buildMuseum();
        $user = $this->premium();
        Favorite::create(['user_id' => $user->id, 'subject_type' => 'artifact', 'subject_id' => $this->trex->id]);
        $this->actingAs($user)->post('/vi/tour/dino-tour/bat-dau');

        $this->actingAs($user)->get('/vi/tai-khoan')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Account/Dashboard')
            ->has('orders')->where('membership.package_code', 'premium')->where('favorites.artifacts.0.slug', 'trix-t-rex')
            ->has('favorites.tours', 0)->has('reviews')->where('tourProgress.0.tour.slug', 'dino-tour')->where('tourProgress.0.percent', 0)
            ->where('auth.user.membership.is_active', true));
    }

    public function test_guest_is_redirected_from_account(): void
    {
        $this->get('/vi/tai-khoan')->assertRedirect(localized_route('login', ['locale' => 'vi']));
    }

    public function test_profile_update_with_avatar_and_validation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/vi/tai-khoan/ho-so', ['name' => 'Tên Mới', 'phone' => '0987654321', 'avatar' => UploadedFile::fake()->image('a.png', 100, 100)])
            ->assertSessionHas('success');
        $user->refresh();
        $this->assertSame('Tên Mới', $user->name);
        Storage::disk('public')->assertExists($user->avatar_path);

        $this->actingAs($user)->patch('/vi/tai-khoan/ho-so', ['phone' => 'abc', 'avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['phone', 'avatar']);
        $this->actingAs($user)->patch('/vi/tai-khoan/ho-so', ['avatar' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertSessionHasErrors('avatar');
    }

    public function test_personal_token_created_once_listed_and_revoked(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/vi/tai-khoan/token', ['name' => 'CLI'])->assertSessionHas('new_token');
        $this->actingAs($user)->get('/vi/tai-khoan/ho-so')->assertInertia(fn (Assert $p) => $p->component('Account/Profile')
            ->has('tokens', 1)->where('tokens.0.name', 'CLI')->missing('tokens.0.token'));
        $id = $user->tokens()->first()->id;
        $this->actingAs($user)->delete('/vi/tai-khoan/token/'.$id)->assertSessionHas('success');
        $this->assertSame(0, $user->tokens()->count());
    }
}
