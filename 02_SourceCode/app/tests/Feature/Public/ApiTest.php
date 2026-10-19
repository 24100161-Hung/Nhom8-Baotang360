<?php

namespace Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use BuildsMuseumContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(null, 503)]);
        $this->buildMuseum();
    }

    public function test_scenes_list_and_detail_with_hotspots(): void
    {
        $this->getJson('/api/v1/scenes?locale=en')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'main-hall')->assertJsonPath('meta.locale', 'en');
        $this->getJson('/api/v1/scenes/dinosaur-era')->assertOk()
            ->assertJsonPath('data.panorama.type', 'multires')->assertJsonPath('data.hotspots.0.artifact.slug', 'trix-t-rex');
        $this->getJson('/api/v1/scenes/khong-co')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    public function test_artifacts_paginated_and_detail(): void
    {
        $this->getJson('/api/v1/artifacts?per_page=5&taxon=dinosauria')->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'title', 'scientific_name', 'cover', 'url']], 'meta' => ['current_page', 'total'], 'links'])
            ->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/artifacts/trix-t-rex')->assertOk()->assertJsonPath('data.slug', 'trix-t-rex')
            ->assertJsonMissingPath('data.my_review');
    }

    public function test_tours_show_and_similar(): void
    {
        $this->getJson('/api/v1/tours')->assertOk()->assertJsonPath('data.0.slug', 'dino-tour');
        $this->getJson('/api/v1/tours/dino-tour')->assertOk()->assertJsonCount(3, 'data.steps');
        $this->getJson('/api/v1/tours/dino-tour/similar')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_packages_visit_days_posts_and_search(): void
    {
        $this->getJson('/api/v1/packages')->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data' => [['code', 'price_vnd', 'benefits']]]);
        $this->getJson('/api/v1/visit-days')->assertOk()->assertJsonPath('data.0.status', 'open')->assertJsonPath('data.0.remaining', 300);
        $this->getJson('/api/v1/visit-days?from=2026-13-01')->assertStatus(422);
        $this->getJson('/api/v1/posts')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/search?q=Tour')->assertOk()->assertJsonStructure(['data', 'meta' => ['engine']]);
        $this->getJson('/api/v1/search')->assertStatus(422);
    }

    public function test_me_requires_sanctum_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $user = User::factory()->visitor()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.email', $user->email)->assertJsonPath('data.role', 'visitor')->assertJsonMissingPath('data.password');
    }

    public function test_tokens_endpoint_issues_token_and_rejects_bad_credentials(): void
    {
        User::factory()->admin()->create(['email' => 'admin@baotang360.test']);
        $token = $this->postJson('/api/v1/tokens', ['email' => 'admin@baotang360.test', 'password' => 'password', 'device_name' => 'cli'])
            ->assertCreated()->assertJsonPath('user.role', 'admin')->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->postJson('/api/v1/tokens', ['email' => 'admin@baotang360.test', 'password' => 'nope', 'device_name' => 'cli'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_openapi_document_lists_endpoints(): void
    {
        $response = $this->get('/api/v1/openapi.json')->assertOk()->assertHeader('Content-Type', 'application/json');
        $doc = json_decode((string) file_get_contents($response->baseResponse->getFile()->getPathname()), true);
        $this->assertSame('3.0.3', $doc['openapi']);
        foreach (['/scenes', '/scenes/{slug}', '/artifacts', '/tours/{slug}/similar', '/visit-days', '/me', '/tokens'] as $path) {
            $this->assertArrayHasKey($path, $doc['paths']);
        }
    }
}
