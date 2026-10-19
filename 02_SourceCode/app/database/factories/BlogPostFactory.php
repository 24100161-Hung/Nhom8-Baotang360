<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BlogPost> */
class BlogPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => fn () => (BlogCategory::query()->first() ?? self::category('kham_pha'))->id,
            'slug' => 'bai-viet-'.fake()->unique()->numberBetween(1, 999999),
            'published_at' => now()->subDay(),
            'status' => 'published',
        ];
    }

    public static function category(string $code): BlogCategory
    {
        $cat = BlogCategory::firstOrCreate(['code' => $code], ['slug' => str_replace('_', '-', $code)]);
        $cat->syncTranslations(['vi' => ['name' => $code], 'en' => ['name' => $code]], 'name');

        return $cat;
    }

    public function configure(): static
    {
        return $this->afterCreating(function (BlogPost $p) {
            $p->syncTranslations([
                'vi' => ['title' => 'Bài '.$p->slug, 'excerpt' => 'Tóm tắt', 'body' => 'Nội dung **bài viết**'],
                'en' => ['title' => 'Post '.$p->slug, 'excerpt' => 'Excerpt', 'body' => 'Post **body**'],
            ], 'title');
        });
    }
}
