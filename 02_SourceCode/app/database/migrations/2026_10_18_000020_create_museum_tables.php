<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảo tàng, không gian 360° (scene), hiện vật và điểm nóng (hotspot) trên ảnh toàn cảnh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('museums', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 190);
            $table->string('city', 100)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('website', 255)->nullable();
            $table->enum('license', ['cc_by_sa_3', 'cc_by_4', 'cc0', 'other'])->default('cc_by_sa_3');
            $table->text('attribution_text')->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->json('floorplan')->nullable();               // {image, width, height}
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->timestamps();
        });

        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('code', 40)->unique();                 // main_hall | dinosaur | seduction | death | …
            $table->tinyInteger('floor')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('panorama_path', 255)->nullable();      // ảnh equirectangular gốc (disk public)
            $table->string('tiles_path', 255)->nullable();         // thư mục multires (disk public)
            $table->json('tile_config')->nullable();              // {cubeResolution, tileResolution, maxLevel, extension}
            $table->enum('tiles_state', ['none', 'pending', 'running', 'done', 'error'])->default('none');
            $table->string('preview_path', 255)->nullable();
            $table->decimal('hfov_default', 5, 2)->default(100);
            $table->decimal('pitch_default', 6, 3)->default(0);
            $table->decimal('yaw_default', 6, 3)->default(0);
            $table->decimal('minimap_x', 5, 4)->default(0.5);
            $table->decimal('minimap_y', 5, 4)->default(0.5);
            $table->decimal('north_offset', 6, 3)->default(0);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedInteger('view_count')->default(0);
            $table->string('source_name', 190)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('author', 150)->nullable();
            $table->string('license', 32)->nullable();
            $table->boolean('is_simulated')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('museum_id')->constrained('museums')->restrictOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained('scenes')->nullOnDelete();
            $table->string('slug', 150)->unique();
            $table->string('catalog_number', 60)->nullable();      // số đăng ký Naturalis, ví dụ RGM.1332066
            $table->string('scientific_name', 190)->nullable();
            $table->string('taxon_group', 40)->nullable();         // dinosauria | mammalia | aves | insecta | mineral | …
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->char('origin_country', 2)->nullable();
            $table->string('origin_locality', 190)->nullable();
            $table->string('material', 120)->nullable();
            $table->decimal('length_cm', 9, 2)->nullable();
            $table->decimal('width_cm', 9, 2)->nullable();
            $table->decimal('height_cm', 9, 2)->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->smallInteger('year_collected')->nullable();
            $table->string('collector', 190)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('image_license', 32)->nullable();
            $table->string('image_author', 190)->nullable();
            $table->boolean('is_highlight')->default(false);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'is_highlight']);
            $table->index('taxon_group');
            $table->index('material');
            $table->index('origin_country');
            $table->index('catalog_number');
        });

        Schema::create('hotspots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained('scenes')->cascadeOnDelete();
            $table->enum('type', ['info', 'scene', 'artifact']);
            $table->decimal('pitch', 6, 3);
            $table->decimal('yaw', 6, 3);
            $table->foreignId('target_scene_id')->nullable()->constrained('scenes')->nullOnDelete();
            $table->decimal('target_pitch', 6, 3)->nullable();
            $table->decimal('target_yaw', 6, 3)->nullable();
            $table->decimal('target_hfov', 5, 2)->nullable();
            $table->foreignId('artifact_id')->nullable()->constrained('artifacts')->nullOnDelete();
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->timestamps();
            $table->index(['scene_id', 'status']);
        });

        Schema::create('hotspot_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotspot_id')->constrained('hotspots')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('title', 190);
            $table->text('text')->nullable();
            $table->unique(['hotspot_id', 'locale']);
        });

        // Ràng buộc miền giá trị góc nhìn (MySQL 8.0.16+ thực thi CHECK)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE hotspots ADD CONSTRAINT hotspots_pitch_chk CHECK (pitch BETWEEN -90 AND 90)');
            DB::statement('ALTER TABLE hotspots ADD CONSTRAINT hotspots_yaw_chk CHECK (yaw BETWEEN -180 AND 180)');
            DB::statement('ALTER TABLE scenes ADD CONSTRAINT scenes_minimap_chk CHECK (minimap_x BETWEEN 0 AND 1 AND minimap_y BETWEEN 0 AND 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_translations');
        Schema::dropIfExists('hotspots');
        Schema::dropIfExists('artifacts');
        Schema::dropIfExists('scenes');
        Schema::dropIfExists('museums');
    }
};
