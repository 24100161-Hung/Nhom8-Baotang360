<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sự kiện hành vi tham quan 360° (không lưu PII), lượt xem, nhật ký tìm kiếm và nhật ký kiểm toán.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_events', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64);                    // băm của phiên, không phải session id thật
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event_type', [
                'scene_enter', 'scene_leave', 'hotspot_click', 'tour_start', 'tour_step',
                'tour_complete', 'tour_abandon', 'artifact_view', 'audio_play',
            ]);
            $table->foreignId('scene_id')->nullable()->constrained('scenes')->nullOnDelete();
            $table->foreignId('hotspot_id')->nullable()->constrained('hotspots')->nullOnDelete();
            $table->foreignId('tour_id')->nullable()->constrained('tours')->nullOnDelete();
            $table->unsignedSmallInteger('step_order')->nullable();
            $table->foreignId('artifact_id')->nullable()->constrained('artifacts')->nullOnDelete();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->char('locale', 2)->nullable();
            $table->enum('device', ['mobile', 'tablet', 'desktop'])->default('desktop');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
            $table->index(['tour_id', 'event_type']);
            $table->index(['scene_id', 'event_type']);
            $table->index('session_key');
        });

        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('locale', 2);
            $table->date('viewed_on');
            $table->timestamp('counted_at')->nullable();          // NULL = chưa cộng vào view_count
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index('viewed_on');
            $table->index('counted_at');
        });

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('locale', 2);
            $table->string('query', 255);
            $table->unsignedSmallInteger('result_count');
            $table->enum('engine', ['tfidf', 'fulltext', 'like']);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('visit_events');
    }
};
