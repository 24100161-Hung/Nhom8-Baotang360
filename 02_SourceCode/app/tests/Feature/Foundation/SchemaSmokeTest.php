<?php

namespace Tests\Feature\Foundation;

use App\Http\Resources\ArtifactCardResource;
use App\Http\Resources\HotspotItemResource;
use App\Http\Resources\SceneCardResource;
use App\Http\Resources\TourCardResource;
use App\Models\Artifact;
use App\Models\Hotspot;
use App\Models\Review;
use App\Models\Scene;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_business_tables_exist(): void
    {
        $tables = ['roles', 'users', 'password_reset_tokens', 'sessions', 'periods', 'period_translations', 'tags', 'tag_translations',
            'taggables', 'museums', 'scenes', 'hotspots', 'hotspot_translations', 'artifacts', 'interpretations', 'media_assets',
            'media_captions', 'tours', 'tour_translations', 'tour_steps', 'tour_step_translations', 'tour_progress', 'visit_events',
            'page_views', 'search_logs', 'audit_logs', 'ticket_packages', 'ticket_package_translations', 'visit_days', 'carts',
            'cart_items', 'orders', 'order_items', 'tickets', 'memberships', 'payments', 'reviews', 'favorites', 'guestbook_entries',
            'blog_categories', 'blog_category_translations', 'blog_posts', 'blog_post_translations', 'contact_messages'];
        $this->assertCount(44, $tables);
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table $table");
        }
    }

    public function test_review_rating_check_constraint_rejects_out_of_range(): void
    {
        $user = User::factory()->create();
        $artifact = Artifact::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('reviews')->insert([
            'user_id' => $user->id, 'subject_type' => 'artifact', 'subject_id' => $artifact->id,
            'rating' => 6, 'body' => 'x', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_card_resources_render_with_factories(): void
    {
        $scene = Scene::factory()->create();
        $artifact = Artifact::factory()->create(['scene_id' => $scene->id]);
        Hotspot::factory()->create(['scene_id' => $scene->id, 'type' => 'artifact', 'artifact_id' => $artifact->id]);
        $tour = Tour::factory()->withSteps(2, [$scene])->create();
        Review::create(['user_id' => User::factory()->create()->id, 'subject_type' => 'tour', 'subject_id' => $tour->id, 'rating' => 4, 'body' => 'ok', 'status' => 'approved']);

        $sceneCard = SceneCardResource::one(Scene::query()->with(SceneCardResource::eager())->withCount(SceneCardResource::counts())->find($scene->id));
        $this->assertSame(1, $sceneCard['hotspot_count']);
        $this->assertSame(1, $sceneCard['artifact_count']);
        $this->assertStringContainsString('/vi/tham-quan-360/'.$scene->slug, $sceneCard['url']);

        $card = ArtifactCardResource::one(Artifact::query()->with(ArtifactCardResource::eager())->withRatings()->find($artifact->id));
        $this->assertSame($scene->slug, $card['scene']['slug']);

        $hotspot = HotspotItemResource::one(Hotspot::query()->with(HotspotItemResource::eager())->first());
        $this->assertSame($artifact->slug, $hotspot['artifact']['slug']);

        $tourCard = TourCardResource::one(Tour::query()->with(TourCardResource::eager())->withCount('steps')->withRatings()->find($tour->id));
        $this->assertSame(2, $tourCard['steps_count']);
        $this->assertSame(4.0, $tourCard['rating_avg']);
    }
}
