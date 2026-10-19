<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Thứ tự: periods → tags → museum → scenes → artifacts → hotspots → tours → packages → posts → visit_days → demo. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            PeriodSeeder::class,
            TagSeeder::class,
            MuseumSeeder::class,
            SceneSeeder::class,
            ArtifactSeeder::class,
            HotspotSeeder::class,
            TourSeeder::class,
            PackageSeeder::class,
            BlogSeeder::class,
            VisitDaySeeder::class,
            DemoActivitySeeder::class,
        ]);

        // Đồng bộ số lần sử dụng thẻ từ bảng taggables
        DB::table('tags')->update([
            'usage_count' => DB::raw('(SELECT COUNT(*) FROM taggables WHERE taggables.tag_id = tags.id)'),
        ]);
    }
}
