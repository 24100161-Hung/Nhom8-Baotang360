<?php

namespace Tests\Feature\Shop;

use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartDateChangeTest extends TestCase
{
    use RefreshDatabase, ShopHelpers;

    public function test_visit_date_can_be_changed_in_cart_and_merges_duplicates(): void
    {
        $package = $this->regular();
        $first = $this->futureDate(3);
        $second = $this->futureDate(10);
        $this->day($first);
        $this->day($second);
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $first, 'quantity' => 2]);
        $item = CartItem::first();

        $this->patch("/vi/gio-hang/{$item->id}", ['visit_date' => $second])->assertSessionHasNoErrors();
        $this->assertSame($second, $item->fresh()->visit_date->toDateString());
        $this->assertSame(2, $item->fresh()->quantity);

        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $first, 'quantity' => 1]);
        $other = CartItem::query()->whereKeyNot($item->id)->firstOrFail();
        $this->patch("/vi/gio-hang/{$other->id}", ['visit_date' => $second])->assertSessionHasNoErrors();
        $this->assertSame(1, CartItem::count());
        $this->assertSame(3, CartItem::first()->quantity);
    }

    public function test_changing_to_closed_day_is_rejected(): void
    {
        $package = $this->regular();
        $open = $this->futureDate(3);
        $closed = $this->futureDate(12);
        $this->day($open);
        $this->day($closed, status: 'closed');
        $this->post('/vi/gio-hang', ['package_id' => $package->id, 'visit_date' => $open, 'quantity' => 1]);

        $this->patch('/vi/gio-hang/'.CartItem::first()->id, ['visit_date' => $closed])->assertSessionHasErrors('visit_date');
        $this->patch('/vi/gio-hang/'.CartItem::first()->id, [])->assertSessionHasErrors(['quantity', 'visit_date']);
    }
}
