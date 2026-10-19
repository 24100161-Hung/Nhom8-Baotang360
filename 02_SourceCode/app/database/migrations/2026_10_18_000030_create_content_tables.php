<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lớp diễn giải đa ngữ, đa mức (scene, artifact, museum) và tài nguyên đa phương tiện có siêu dữ liệu bản quyền.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interpretations', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 40);                  // alias morph: museum | scene | artifact
            $table->unsignedBigInteger('subject_id');
            $table->char('locale', 2);
            $table->unsignedTinyInteger('depth_level');           // 1 phổ thông, 2 chuyên sâu
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->text('sources')->nullable();                  // mỗi dòng một nguồn
            $table->boolean('is_machine_translated')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id', 'locale', 'depth_level'], 'interp_subject_locale_depth_unique');
            $table->index(['subject_type', 'subject_id']);
            $table->index(['locale', 'depth_level']);
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
                $table->fullText(['title', 'summary', 'body'], 'interp_fulltext');
            }
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->enum('media_type', ['image', 'audio', 'video', 'panorama', 'document']);
            $table->char('locale', 2)->nullable();                // audio guide: vi | en | de | nl
            $table->string('path', 255)->nullable();              // tệp trong storage/app/public
            $table->string('external_url', 500)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->string('source_name', 190)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('author', 190)->nullable();
            $table->string('license', 32)->default('simulated');
            $table->string('rights_holder', 190)->nullable();
            $table->date('captured_at')->nullable();
            $table->text('provenance_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'media_type']);
            $table->index('license');
        });

        Schema::create('media_captions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media_assets')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('caption', 500)->nullable();
            $table->text('transcript')->nullable();
            $table->unique(['media_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_captions');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('interpretations');
    }
};
