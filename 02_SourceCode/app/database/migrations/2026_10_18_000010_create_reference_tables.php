<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng tham chiếu: thời kỳ địa chất/lịch sử và thẻ phân loại (đa hình qua taggables).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();                 // cretaceous, pleistocene, holocene, modern…
            $table->decimal('start_mya', 8, 3)->nullable();        // triệu năm trước
            $table->decimal('end_mya', 8, 3)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('period_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unique(['period_id', 'locale']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->enum('tag_group', ['chat_lieu', 'nguon_goc', 'nhom_sinh_vat', 'chu_de', 'phong']);
            $table->foreignId('parent_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            $table->index('tag_group');
        });

        Schema::create('tag_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unique(['tag_id', 'locale']);
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->string('taggable_type', 40);                  // alias morph: artifact | scene | tour | post
            $table->unsignedBigInteger('taggable_id');
            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique');
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tag_translations');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('period_translations');
        Schema::dropIfExists('periods');
    }
};
