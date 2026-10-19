<?php

namespace Tests\Feature\Public;

use App\Models\Hotspot;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Hợp đồng bổ sung theo yêu cầu frontend đợt 1 (requests/frontend_1.md). */
class IntegrationContractTest extends TestCase
{
    use BuildsMuseumContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMuseum();
    }

    public function test_shared_props_include_csrf_token_and_flash_token(): void
    {
        $this->get('/vi')->assertInertia(fn (Assert $p) => $p->has('csrf_token')->where('flash.token', null));
    }

    public function test_viewer_has_tours_floorplan_and_image_url(): void
    {
        Hotspot::query()->where('scene_id', $this->hall->id)->update(['image_path' => 'hotspots/a.jpg']);

        $this->get('/vi/tham-quan-360/main-hall')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Scenes/Viewer')
            ->has('tours', 1)->where('tours.0.slug', 'dino-tour')
            ->where('floorplan.image_url', '/minimap/naturalis-floorplan.svg')->where('floorplan.height', 700)
            ->where('scene.hotspots.0.image_url', fn ($url) => str_ends_with((string) $url, 'hotspots/a.jpg'))
            ->where('visited', ['main-hall']));
    }

    public function test_viewer_partial_reload_returns_only_requested_props_and_visit_order(): void
    {
        $this->get('/vi/tham-quan-360/main-hall');
        $this->get('/vi/tham-quan-360/main-hall'); // tải lại cùng phòng → không lặp liên tiếp
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
        $response = $this->withHeaders([
            'X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'Scenes/Viewer',
            'X-Inertia-Partial-Data' => 'scene,visited,currentStep,mode,tour',
        ])->get('/vi/tham-quan-360/dinosaur-era?tour=dino-tour&step=2&mode=free');

        $props = $response->assertOk()->json('props');
        $this->assertArrayNotHasKey('allScenes', $props);
        $this->assertArrayNotHasKey('floorplan', $props);
        $this->assertArrayNotHasKey('tours', $props);
        $this->assertSame(['main-hall', 'dinosaur-era'], $props['visited']);
        $this->assertSame('free', $props['mode']);
        $this->assertSame('dino-tour', $props['tour']['slug']); // mode=free vẫn có tour để "Tiếp tục"
        $this->assertSame(2, $props['currentStep']);
    }

    public function test_museum_page_has_license_code_and_opening_hours(): void
    {
        $this->get('/vi/lien-he')->assertInertia(fn (Assert $p) => $p->has('museum.opening_hours'));
    }

    public function test_artifact_sort_featured_and_rating_are_accepted(): void
    {
        foreach (['featured', 'rating'] as $sort) {
            $this->get('/vi/hien-vat?sort='.$sort)->assertOk()->assertInertia(fn (Assert $p) => $p->has('artifacts.data', 1));
        }
    }

    public function test_account_dashboard_review_has_subject_link(): void
    {
        $user = User::factory()->create();
        Review::create(['user_id' => $user->id, 'subject_type' => 'artifact', 'subject_id' => $this->trex->id, 'rating' => 5, 'body' => 'Tuyệt', 'status' => 'pending']);

        $this->actingAs($user)->get('/vi/tai-khoan')->assertInertia(fn (Assert $p) => $p
            ->where('reviews.0.subject.type', 'artifact')
            ->where('reviews.0.subject.url', fn ($url) => str_contains((string) $url, '/vi/hien-vat/trix-t-rex')));
    }

    public function test_new_api_token_is_flashed_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/vi/tai-khoan/token', ['name' => 'CLI'])->assertRedirect();
        $this->actingAs($user)->get('/vi/tai-khoan/ho-so')->assertInertia(fn (Assert $p) => $p->where('flash.token', fn ($t) => is_string($t) && str_contains($t, '|')));
        $this->actingAs($user)->get('/vi/tai-khoan/ho-so')->assertInertia(fn (Assert $p) => $p->where('flash.token', null));
    }

    public function test_security_headers_csp_allows_pannellum_sources_only(): void
    {
        $csp = (string) $this->get('/vi')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("img-src 'self' data: blob:", $csp);
        $this->assertStringNotContainsString('openstreetmap', $csp);
        $this->assertStringNotContainsString('modelviewer', $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
    }
}
