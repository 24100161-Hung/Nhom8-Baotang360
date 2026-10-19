<?php

namespace Tests\Feature\Shop;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase, ShopHelpers;

    public function test_guest_can_add_regular_ticket_and_see_cart_page(): void
    {
        $package = $this->regular();
        $date = $this->futureDate();
        $this->day($date);

        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $date, 'quantity' => 2])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->get('/vi/gio-hang')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Cart/Index')
            ->has('items', 1)->where('items.0.quantity', 2)->where('items.0.visit_date', $date)
            ->where('subtotal_vnd', 300000)->where('canCheckout', false)->has('calendar', 60)
            ->where('cart.count', 2));
    }

    public function test_adding_same_package_and_date_merges_quantity(): void
    {
        $package = $this->regular();
        $date = $this->futureDate();
        $this->day($date);
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $date, 'quantity' => 2]);
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $date, 'quantity' => 3]);

        $this->assertSame(1, CartItem::count());
        $this->assertSame(5, CartItem::first()->quantity);
    }

    public function test_update_and_remove_cart_item(): void
    {
        $package = $this->regular();
        $date = $this->futureDate();
        $this->day($date);
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $date, 'quantity' => 1]);
        $item = CartItem::first();

        $this->patch("/vi/gio-hang/{$item->id}", ['quantity' => 4])->assertSessionHasNoErrors();
        $this->assertSame(4, $item->fresh()->quantity);
        $this->delete("/vi/gio-hang/{$item->id}")->assertSessionHas('success');
        $this->assertSame(0, CartItem::count());
    }

    public function test_cannot_modify_someone_elses_cart_item(): void
    {
        $other = User::factory()->create();
        $date = $this->futureDate();
        $this->day($date);
        $cart = $this->cartFor($other, $this->regular(), $date, 1);

        $this->patch('/vi/gio-hang/'.$cart->items()->first()->id, ['quantity' => 3])->assertNotFound();
        $this->assertSame(1, $cart->items()->first()->quantity);
    }

    public function test_single_ticket_requires_visit_date_and_valid_quantity(): void
    {
        $package = $this->regular();
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'quantity' => 2])->assertSessionHasErrors('visit_date');
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $this->futureDate(), 'quantity' => 0])->assertSessionHasErrors('quantity');
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $this->futureDate(), 'quantity' => 11])->assertSessionHasErrors('quantity');
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => '2026/12/01', 'quantity' => 1])->assertSessionHasErrors('visit_date');
        $this->assertSame(0, CartItem::count());
    }

    public function test_closed_monday_sold_out_and_past_dates_are_rejected(): void
    {
        $package = $this->regular();
        $monday = today()->addDays(7)->startOfWeek()->toDateString();
        $this->day($monday, 300, 0, 'closed');
        $full = $this->futureDate(10);
        $this->day($full, 50, 50, 'sold_out');
        $past = today()->subDay()->toDateString();
        $this->day($past);

        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $monday, 'quantity' => 1])->assertSessionHasErrors('visit_date');
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $full, 'quantity' => 1])->assertSessionHasErrors('visit_date');
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $past, 'quantity' => 1])->assertSessionHasErrors('visit_date');
        $this->assertSame(0, CartItem::count());
    }

    public function test_quantity_above_remaining_tickets_is_rejected(): void
    {
        $date = $this->futureDate();
        $this->day($date, 10, 8);
        $this->post('/vi/gio-hang', ['package_id' => $this->regular()->id, 'visit_date' => $date, 'quantity' => 3])
            ->assertSessionHasErrors(['quantity' => 'Chỉ còn 2 vé cho ngày này.']);
    }

    public function test_guest_cart_is_merged_into_user_cart_after_login(): void
    {
        $package = $this->regular();
        $date = $this->futureDate();
        $this->day($date);
        $user = User::factory()->create();
        $this->cartFor($user, $package, $date, 1);
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $date, 'quantity' => 2]);

        $request = Request::create('/vi/gio-hang');
        $request->setLaravelSession($this->app['session.store']);
        app(CartService::class)->mergeGuestCart($request, $user);

        $userCart = Cart::query()->where('user_id', $user->id)->where('status', 'active')->first();
        $this->assertSame(3, $userCart->items()->first()->quantity);
        $this->assertSame(1, Cart::query()->where('status', 'active')->count());
    }

    public function test_membership_package_ignores_visit_date(): void
    {
        $package = $this->premiumPackage();
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->assertNull(CartItem::first()->visit_date);
    }
}
