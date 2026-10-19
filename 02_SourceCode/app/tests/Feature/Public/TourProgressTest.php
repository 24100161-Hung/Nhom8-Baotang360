<?php

namespace Tests\Feature\Public;

use App\Models\TourProgress;
use App\Models\User;
use App\Models\VisitEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TourProgressTest extends TestCase
{
    use BuildsMuseumContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 503)]);
        $this->buildMuseum();
    }

    public function test_start_creates_progress_and_redirects_to_first_step_guided(): void
    {
        $this->post('/vi/tour/dino-tour/bat-dau')
            ->assertRedirect(localized_route('scenes.show', ['locale' => 'vi', 'scene' => 'main-hall', 'tour' => 'dino-tour', 'step' => 1, 'mode' => 'guided']));
        $progress = TourProgress::firstOrFail();
        $this->assertSame(1, $progress->current_step);
        $this->assertNull($progress->user_id);
        $this->assertSame(1, VisitEvent::where('event_type', 'tour_start')->where('tour_id', $this->tour->id)->count());
    }

    public function test_progress_next_then_complete_updates_percent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/vi/tour/dino-tour/bat-dau');

        // next/prev: step = bước đích
        $this->postJson('/vi/tour/dino-tour/tien-trinh', ['step' => 2, 'action' => 'next'])->assertOk()
            ->assertJsonPath('progress.current_step', 2)->assertJsonPath('progress.percent', 33)->assertJsonPath('progress.total_steps', 3);
        $this->postJson('/vi/tour/dino-tour/tien-trinh', ['step' => 3, 'action' => 'next'])->assertJsonPath('progress.percent', 67)
            ->assertJsonPath('progress.current_step', 3);
        $this->postJson('/vi/tour/dino-tour/tien-trinh', ['step' => 3, 'action' => 'complete'])
            ->assertJsonPath('progress.percent', 100)->assertJsonPath('progress.completed_steps', [1, 2, 3]);

        $this->assertNotNull(TourProgress::where('user_id', $user->id)->first()->completed_at);
        $this->assertSame(1, VisitEvent::where('event_type', 'tour_complete')->count());
        $this->assertSame(2, VisitEvent::where('event_type', 'tour_step')->count());
    }

    public function test_progress_prev_and_abandon(): void
    {
        $this->post('/vi/tour/dino-tour/bat-dau');
        $this->postJson('/vi/tour/dino-tour/tien-trinh', ['step' => 3, 'action' => 'next'])->assertJsonPath('progress.current_step', 3);
        $this->postJson('/vi/tour/dino-tour/tien-trinh', ['step' => 2, 'action' => 'prev'])->assertJsonPath('progress.current_step', 2);
        $this->postJson('/vi/tour/dino-tour/tien-trinh', ['step' => 1, 'action' => 'prev'])->assertJsonPath('progress.current_step', 1);
        $this->postJson('/vi/tour/dino-tour/tien-trinh', ['step' => 1, 'action' => 'abandon'])->assertOk();
        $this->assertSame(1, VisitEvent::where('event_type', 'tour_abandon')->where('step_order', 1)->count());
    }

    public function test_progress_validation_rejects_bad_action_and_step(): void
    {
        $this->postJson('/vi/tour/dino-tour/tien-trinh', ['step' => 0, 'action' => 'jump'])
            ->assertStatus(422)->assertJsonValidationErrors(['step', 'action']);
    }

    public function test_tour_detail_shows_progress_after_start(): void
    {
        $this->post('/vi/tour/dino-tour/bat-dau');
        $this->get('/vi/tour/dino-tour')->assertInertia(fn ($p) => $p->where('tour.progress.current_step', 1)->where('tour.progress.percent', 0));
    }
}
