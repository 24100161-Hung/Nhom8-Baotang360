<?php

namespace Tests\Feature\Admin;

use App\Models\Artifact;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\GuestbookEntry;
use App\Models\Review;
use App\Models\User;
use Database\Factories\BlogPostFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_curator_approves_review(): void
    {
        $review = Review::create(['user_id' => User::factory()->create()->id, 'subject_type' => 'artifact',
            'subject_id' => Artifact::factory()->create()->id, 'rating' => 5, 'body' => 'Tuyệt vời', 'status' => 'pending']);
        $curator = $this->curator();
        $this->actingAs($curator)->get('/vi/quan-tri/danh-gia')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Admin/Reviews/Index')->where('reviews.data.0.id', $review->id));

        $this->actingAs($curator)->patch("/vi/quan-tri/danh-gia/{$review->id}", ['status' => 'approved', 'review_note' => 'OK'])->assertSessionHasNoErrors();
        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame($curator->id, $review->reviewed_by);
        $this->actingAs($curator)->patch("/vi/quan-tri/danh-gia/{$review->id}", ['status' => 'deleted'])->assertSessionHasErrors('status');
    }

    public function test_admin_marks_contact_replied_and_curator_is_forbidden(): void
    {
        $message = ContactMessage::create(['name' => 'An', 'email' => 'an@example.test', 'subject' => 'Vé nhóm', 'body' => 'Xin báo giá']);
        $this->actingAs($this->curator())->patch("/vi/quan-tri/lien-he/{$message->id}", ['status' => 'read'])->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->patch("/vi/quan-tri/lien-he/{$message->id}", ['status' => 'replied'])->assertSessionHasErrors('reply_note');
        $this->actingAs($admin)->patch("/vi/quan-tri/lien-he/{$message->id}", ['status' => 'replied', 'reply_note' => 'Đã gửi email'])->assertSessionHasNoErrors();
        $message->refresh();
        $this->assertSame('replied', $message->status);
        $this->assertSame($admin->id, $message->replied_by);
        $this->assertNotNull($message->replied_at);
    }

    public function test_guestbook_moderation(): void
    {
        $entry = GuestbookEntry::create(['display_name' => 'Khách', 'message' => 'Rất hay', 'status' => 'pending']);
        $curator = $this->curator();
        $this->actingAs($curator)->patch("/vi/quan-tri/so-luu-but/{$entry->id}", ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $entry->fresh()->status);
        $this->actingAs($curator)->delete("/vi/quan-tri/so-luu-but/{$entry->id}")->assertRedirect();
        $this->assertDatabaseMissing('guestbook_entries', ['id' => $entry->id]);
    }

    public function test_blog_post_crud(): void
    {
        $category = BlogPostFactory::category('khoa_hoc');
        $curator = $this->curator();
        $data = ['category_id' => $category->id, 'slug' => 'trix-den-leiden', 'status' => 'published',
            'title_vi' => 'Trix đến Leiden', 'title_en' => 'Trix arrives in Leiden', 'body_vi' => 'Nội dung', 'body_en' => 'Body'];
        $this->actingAs($curator)->post('/vi/quan-tri/blog', $data)->assertSessionHasNoErrors();
        $post = BlogPost::query()->where('slug', 'trix-den-leiden')->firstOrFail();
        $this->assertNotNull($post->published_at);
        $this->assertSame($curator->id, $post->author_id);

        $this->actingAs($curator)->put("/vi/quan-tri/blog/{$post->id}", array_merge($data, ['title_en' => 'Trix in Leiden']))->assertSessionHasNoErrors();
        $this->assertSame('Trix in Leiden', $post->fresh()->tr('title', 'en'));
        $this->actingAs($curator)->delete("/vi/quan-tri/blog/{$post->id}")->assertRedirect();
        $this->assertSoftDeleted('blog_posts', ['id' => $post->id]);
    }
}
