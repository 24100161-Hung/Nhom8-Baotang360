<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tour tham quan 360° có hướng dẫn: bước (scene + góc nhìn), bản dịch, tiến trình người xem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 150)->unique();
            $table->foreignId('theme_tag_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->enum('difficulty', ['de', 'vua', 'kho'])->default('de');
            $table->enum('mode', ['free', 'guided', 'both'])->default('both');
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'is_featured']);
        });

        Schema::create('tour_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->text('highlights')->nullable();
            $table->unique(['tour_id', 'locale']);
        });

        Schema::create('tour_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->foreignId('scene_id')->constrained('scenes')->restrictOnDelete();
            $table->foreignId('hotspot_id')->nullable()->constrained('hotspots')->nullOnDelete();
            $table->decimal('pitch', 6, 3)->default(0);
            $table->decimal('yaw', 6, 3)->default(0);
            $table->decimal('hfov', 5, 2)->default(100);
            $table->unsignedSmallInteger('dwell_seconds')->default(60);
            $table->boolean('auto_advance')->default(false);
            $table->timestamps();
            $table->unique(['tour_id', 'step_order']);
        });

        Schema::create('tour_step_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_step_id')->constrained('tour_steps')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('title', 190);
            $table->text('narration')->nullable();
            $table->unique(['tour_step_id', 'locale']);
        });

        Schema::create('tour_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_key', 64);
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->json('completed_steps')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['session_key', 'tour_id']);
            $table->index(['user_id', 'tour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_progress');
        Schema::dropIfExists('tour_step_translations');
        Schema::dropIfExists('tour_steps');
        Schema::dropIfExists('tour_translations');
        Schema::dropIfExists('tours');
    }
};
