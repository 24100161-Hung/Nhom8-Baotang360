<?php

namespace Tests\Feature\Public;

use App\Models\Artifact;
use App\Models\GuestbookEntry;
use App\Models\Review;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use BuildsMuseumContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 503)]); // Python tắt → mọi nơi dùng fallback
        $this->buildMuseum();
    }

    public function test_home_has_contract_props_and_shared_cart(): void
    {
        $this->get('/vi')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Home')
            ->has('featuredTours', 1)->has('packages', 2)->has('latestPosts', 1)->has('highlightArtifacts', 1)->has('scenes', 2)
            ->where('stats.scenes', 2)->where('museum.name', 'Naturalis Biodiversity Center')
            ->where('cart.count', 0)->where('app.name', 'BaoTang360')->has('app.museum.attribution'));
    }

    public function test_museum_page_has_floorplan_and_description(): void
    {
        $this->get('/en/bao-tang')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Museum/Show')
            ->where('museum.license', 'cc_by_sa_3')->where('museum.license_label', 'CC BY-SA 3.0')->has('museum.description_html')->where('floorplan.width', 1000)
            ->has('scenes', 2)->where('museum.jsonld.@type', 'Museum'));
    }

    public function test_scenes_index_lists_published_only(): void
    {
        Scene::factory()->draft()->create();
        $this->get('/vi/tham-quan-360')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Scenes/Index')
            ->has('scenes', 2)->has('scenes.0.minimap.x')->where('scenes.0.audio_locales', ['vi']));
    }

    public function test_viewer_returns_multires_panorama_hotspots_and_visited(): void
    {
        $this->get('/vi/tham-quan-360/main-hall');
        $this->get('/vi/tham-quan-360/dinosaur-era')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Scenes/Viewer')
            ->where('scene.panorama.type', 'multires')->where('scene.panorama.config.maxLevel', 3)
            ->where('scene.hotspots.0.type', 'artifact')->where('scene.hotspots.0.artifact.slug', 'trix-t-rex')
            ->has('allScenes', 2)->where('mode', 'free')->where('tour', null)->where('visited', ['main-hall', 'dinosaur-era'])
            ->has('sessionKey'));
    }

    public function test_viewer_in_guided_mode_with_tour_and_scene_neighbors(): void
    {
        $this->get('/vi/tham-quan-360/main-hall?tour=dino-tour&step=2&mode=guided')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('mode', 'guided')->where('currentStep', 2)->where('tour.slug', 'dino-tour')->has('tour.steps', 3)
            ->where('scene.panorama.type', 'equirectangular')->where('scene.neighbors.0.slug', 'dinosaur-era')
            ->where('scene.audios.0.mode', 'tts'));
    }

    public function test_draft_scene_and_artifact_return_404(): void
    {
        $scene = Scene::factory()->draft()->create();
        $artifact = Artifact::factory()->draft()->create();
        $this->get('/vi/tham-quan-360/'.$scene->slug)->assertNotFound();
        $this->get('/vi/hien-vat/'.$artifact->slug)->assertNotFound();
    }

    public function test_artifacts_index_filters_and_facets(): void
    {
        Artifact::factory()->create(['material' => 'Khoáng vật', 'origin_country' => 'NL', 'taxon_group' => 'mineral']);
        $this->get('/vi/hien-vat?material=Khoáng vật&sort=DROP TABLE')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Artifacts/Index')->where('artifacts.total', 1)->where('artifacts.data.0.taxon_group', 'mineral')
            ->has('materials', 2)->has('origins', 2)->has('taxa', 2)->has('periods')->has('scenes', 2));
        $this->get('/vi/hien-vat?origin=US&scene=dinosaur-era')->assertInertia(fn (Assert $p) => $p->where('artifacts.total', 1)
            ->where('artifacts.data.0.slug', 'trix-t-rex'));
    }

    public function test_artifact_detail_has_interpretation_reviews_and_jsonld(): void
    {
        Review::create(['user_id' => User::factory()->create()->id, 'subject_type' => 'artifact', 'subject_id' => $this->trex->id, 'rating' => 5, 'body' => 'Tuyệt', 'status' => 'approved']);
        Review::create(['user_id' => User::factory()->create()->id, 'subject_type' => 'artifact', 'subject_id' => $this->trex->id, 'rating' => 1, 'body' => 'Spam', 'status' => 'pending']);
        $this->get('/vi/hien-vat/trix-t-rex?depth=9')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Artifacts/Show')
            ->where('depth', 1)->where('artifact.interpretation.depth_level', 1)->has('artifact.interpretation.body_html')
            ->where('artifact.rating_avg', 5)->where('artifact.rating_count', 1)->has('artifact.reviews', 1)
            ->where('artifact.hotspots_in.0.scene.slug', 'dinosaur-era')->where('artifact.jsonld.identifier', $this->trex->catalog_number)
            ->where('artifact.is_favorite', false)->has('artifact.media.images'));
        $this->assertDatabaseHas('page_views', ['subject_type' => 'artifact', 'subject_id' => $this->trex->id]);
    }

    public function test_search_falls_back_to_mysql_when_python_is_down(): void
    {
        $this->get('/vi/tim-kiem?q=Tour')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Search/Index')
            ->where('query', 'Tour')->where('engine', fn ($e) => in_array($e, ['fulltext', 'like'], true))
            ->where('results', fn ($r) => collect($r)->contains('type', 'tour')));
        $this->get('/vi/tim-kiem?q='.urlencode($this->trex->catalog_number))->assertInertia(fn (Assert $p) => $p
            ->where('results', fn ($r) => collect($r)->contains('slug', 'trix-t-rex')));
        $this->assertDatabaseCount('search_logs', 2);
    }

    public function test_search_uses_python_results_when_available(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['*/search' => Http::response(['engine' => 'tfidf', 'results' => [['subject_type' => 'artifact', 'subject_id' => $this->trex->id, 'score' => 0.9, 'matched_terms' => ['rex']]]])]);
        $this->get('/en/tim-kiem?q=rex')->assertInertia(fn (Assert $p) => $p->where('engine', 'tfidf')
            ->where('results.0.slug', 'trix-t-rex')->where('results.0.matched_terms', ['rex']));
    }

    public function test_tours_index_and_detail_with_similar_fallback(): void
    {
        $this->get('/vi/tour?difficulty=de')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tours/Index')
            ->where('tours.total', 1)->where('tours.data.0.steps_count', 3)->where('tours.data.0.scenes_count', 2)->has('themes'));
        $this->get('/vi/tour/dino-tour')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Tours/Show')
            ->has('tour.steps', 3)->where('tour.steps.0.scene.slug', 'main-hall')->where('tour.progress', null)->has('tour.similar'));
    }

    public function test_blog_index_category_filter_and_detail(): void
    {
        $this->get('/vi/blog?danh-muc=kham-pha')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Blog/Index')
            ->where('posts.total', 1)->where('categories.0.code', 'kham_pha')->where('categories.0.count', 1));
        $this->get('/vi/blog/trix-tro-ve')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Blog/Show')
            ->where('post.body_html', fn ($h) => str_contains($h, '<strong>')));
    }

    public function test_guestbook_about_and_contact_pages(): void
    {
        GuestbookEntry::create(['display_name' => 'An', 'message' => 'Hay quá', 'status' => 'approved']);
        GuestbookEntry::create(['display_name' => 'Bot', 'message' => 'Ẩn', 'status' => 'pending']);
        $this->get('/vi/so-luu-but')->assertInertia(fn (Assert $p) => $p->component('Guestbook/Index')->where('entries.total', 1));
        $this->get('/vi/gioi-thieu')->assertInertia(fn (Assert $p) => $p->component('About')->has('museum')->has('stats'));
        $this->get('/vi/lien-he')->assertInertia(fn (Assert $p) => $p->component('Contact/Create')->has('museum'));
    }
}
