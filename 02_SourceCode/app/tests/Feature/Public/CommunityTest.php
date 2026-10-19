<?php

namespace Tests\Feature\Public;

use App\Models\Artifact;
use App\Models\ContactMessage;
use App\Models\Favorite;
use App\Models\GuestbookEntry;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use BuildsMuseumContent, RefreshDatabase;

    private const CONTACT = ['name' => 'Trần B', 'email' => 'b@example.com', 'phone' => '0901234567', 'subject' => 'Hỏi giờ mở cửa', 'body' => 'Bảo tàng mở cửa mấy giờ ạ?'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMuseum();
    }

    public function test_review_is_pending_and_only_one_per_subject(): void
    {
        $user = $this->visitor();
        $body = ['subject_type' => 'artifact', 'subject_id' => $this->trex->id, 'rating' => 5, 'title' => 'Ấn tượng', 'body' => 'Bộ xương rất đẹp'];
        $this->actingAs($user)->post('/vi/danh-gia', $body)->assertSessionHas('success');
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'status' => 'pending', 'rating' => 5]);

        $this->actingAs($user)->post('/vi/danh-gia', $body)->assertSessionHasErrors('subject_id');
        $this->assertSame(1, Review::count());
    }

    public function test_review_validation_rating_out_of_range_and_unpublished_subject(): void
    {
        $user = $this->visitor();
        $this->actingAs($user)->post('/vi/danh-gia', ['subject_type' => 'tour', 'subject_id' => $this->tour->id, 'rating' => 6, 'body' => 'abc'])
            ->assertSessionHasErrors(['rating', 'body']);
        $draft = Artifact::factory()->draft()->create();
        $this->actingAs($user)->post('/vi/danh-gia', ['subject_type' => 'artifact', 'subject_id' => $draft->id, 'rating' => 3, 'body' => 'Nội dung ổn'])
            ->assertSessionHasErrors('subject_id');
        $this->actingAs($user)->post('/vi/danh-gia', ['subject_type' => 'user', 'subject_id' => $user->id, 'rating' => 3, 'body' => 'Nội dung ổn'])
            ->assertSessionHasErrors('subject_type');
        $this->assertSame(0, Review::count());
    }

    public function test_guest_cannot_review_or_favorite(): void
    {
        $this->post('/vi/danh-gia', [])->assertRedirect(localized_route('login', ['locale' => 'vi']));
        $this->post('/vi/yeu-thich', ['type' => 'artifact', 'id' => $this->trex->id])->assertRedirect(localized_route('login', ['locale' => 'vi']));
    }

    public function test_favorite_toggle_adds_then_removes(): void
    {
        $user = $this->visitor();
        $this->actingAs($user)->post('/vi/yeu-thich', ['type' => 'tour', 'id' => $this->tour->id])->assertSessionHas('success');
        $this->assertSame(1, Favorite::count());
        $this->get('/vi/tour/dino-tour')->assertInertia(fn ($p) => $p->where('tour.is_favorite', true));
        $this->actingAs($user)->post('/vi/yeu-thich', ['type' => 'tour', 'id' => $this->tour->id]);
        $this->assertSame(0, Favorite::count());
        $this->actingAs($user)->post('/vi/yeu-thich', ['type' => 'scene', 'id' => 1])->assertSessionHasErrors('type');
    }

    public function test_contact_valid_message_is_stored(): void
    {
        $this->post('/vi/lien-he', self::CONTACT)->assertSessionHas('success');
        $this->assertDatabaseHas('contact_messages', ['email' => 'b@example.com', 'status' => 'new']);
    }

    public function test_contact_honeypot_pretends_success_but_does_not_store(): void
    {
        $this->post('/vi/lien-he', self::CONTACT + ['website' => 'http://spam.example'])->assertSessionHas('success');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_contact_validation_errors(): void
    {
        $this->post('/vi/lien-he', ['name' => 'A', 'email' => 'bad', 'phone' => 'xx', 'subject' => '', 'body' => 'short'])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'subject', 'body']);
    }

    public function test_guestbook_entry_is_pending_and_guest_needs_name(): void
    {
        $this->post('/vi/so-luu-but', ['message' => 'Chuyến tham quan tuyệt vời'])->assertSessionHasErrors('display_name');
        $this->post('/vi/so-luu-but', ['display_name' => 'Lan', 'message' => 'Chuyến tham quan tuyệt vời'])->assertSessionHas('success');
        $user = User::factory()->create(['name' => 'Minh']);
        $this->actingAs($user)->post('/vi/so-luu-but', ['message' => 'Rất hay']);
        $this->assertSame(['pending'], GuestbookEntry::distinct()->pluck('status')->all());
        $this->assertDatabaseHas('guestbook_entries', ['display_name' => 'Minh', 'user_id' => $user->id]);
        $this->post('/vi/so-luu-but', ['display_name' => 'Bot', 'message' => 'spam spam', 'website' => 'x']);
        $this->assertSame(2, GuestbookEntry::count());
    }
}
