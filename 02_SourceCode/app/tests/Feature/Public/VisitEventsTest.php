<?php

namespace Tests\Feature\Public;

use App\Models\VisitEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitEventsTest extends TestCase
{
    use BuildsMuseumContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMuseum();
    }

    public function test_batch_is_stored_with_device_and_without_pii(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile'])->postJson('/vi/su-kien', ['events' => [
            ['type' => 'scene_enter', 'scene_id' => $this->hall->id],
            ['type' => 'hotspot_click', 'scene_id' => $this->hall->id, 'meta' => ['mode' => 'free', 'email' => 'x@y.z', 'yaw' => 12.5]],
            ['type' => 'scene_leave', 'scene_id' => $this->hall->id, 'duration_ms' => 4200],
            ['type' => 'artifact_view', 'artifact_id' => 999999],
        ]])->assertNoContent();

        $this->assertSame(4, VisitEvent::count());
        $this->assertSame(['mobile'], VisitEvent::distinct()->pluck('device')->all());
        $this->assertEquals(['mode' => 'free', 'yaw' => 12.5], VisitEvent::where('event_type', 'hotspot_click')->first()->meta);
        $this->assertNull(VisitEvent::where('event_type', 'artifact_view')->first()->artifact_id); // id rác bị bỏ
        $this->assertFalse(Schema::hasColumn('visit_events', 'ip'));
        $this->assertFalse(Schema::hasColumn('visit_events', 'user_agent'));
        $this->assertSame(40, strlen(VisitEvent::first()->session_key));
    }

    public function test_form_encoded_beacon_batch_is_accepted(): void
    {
        // Body x-www-form-urlencoded như navigator.sendBeacon: số dạng chuỗi, device từ frontend
        $body = [
            '_token' => 'cu', 'session_key' => str_repeat('a', 40), 'locale' => 'vi', 'device' => 'tablet',
            'events' => [
                ['type' => 'scene_enter', 'scene_id' => (string) $this->hall->id, 'meta' => ['resumed' => '1', 'tour_id' => '3']],
                ['type' => 'scene_leave', 'scene_id' => (string) $this->hall->id, 'duration_ms' => '1500'],
            ],
        ];
        $this->post('/vi/su-kien', $body, ['Content-Type' => 'application/x-www-form-urlencoded'])->assertNoContent();

        $this->assertSame(2, VisitEvent::count());
        $this->assertSame(['tablet'], VisitEvent::distinct()->pluck('device')->all());
        $this->assertSame(1500, (int) VisitEvent::where('event_type', 'scene_leave')->value('duration_ms'));
        $this->assertEquals(['resumed' => '1', 'tour_id' => '3'], VisitEvent::where('event_type', 'scene_enter')->first()->meta);
    }

    public function test_more_than_fifty_events_is_rejected(): void
    {
        $events = array_fill(0, 51, ['type' => 'scene_enter', 'scene_id' => $this->hall->id]);
        $this->postJson('/vi/su-kien', ['events' => $events])->assertStatus(422)->assertJsonValidationErrors('events');
        $this->assertSame(0, VisitEvent::count());
    }

    public function test_invalid_type_and_empty_batch_rejected_even_without_json_accept(): void
    {
        $this->post('/vi/su-kien', ['events' => [['type' => 'delete_all']]])->assertStatus(422);
        $this->postJson('/vi/su-kien', ['events' => []])->assertStatus(422);
    }

    public function test_events_endpoint_is_throttled(): void
    {
        RateLimiter::for('events', fn ($r) => Limit::perMinute(3)->by('events:'.$r->ip()));
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/vi/su-kien', ['events' => [['type' => 'scene_enter']]])->assertNoContent();
        }
        $this->postJson('/vi/su-kien', ['events' => [['type' => 'scene_enter']]])->assertStatus(429);
    }
}
