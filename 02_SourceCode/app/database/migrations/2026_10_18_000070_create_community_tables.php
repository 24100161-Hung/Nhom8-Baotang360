<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cộng đồng (đánh giá, yêu thích, sổ lưu bút), blog theo danh mục và tin nhắn liên hệ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 40);                  // artifact | tour | museum
            $table->unsignedBigInteger('subject_id');
            $table->unsignedTinyInteger('rating');
            $table->string('title', 150)->nullable();
            $table->text('body');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'subject_type', 'subject_id'], 'reviews_user_subject_unique');
            $table->index(['subject_type', 'subject_id', 'status']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 40);                  // artifact | tour
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();
            $table->unique(['user_id', 'subject_type', 'subject_id'], 'favorites_user_subject_unique');
        });

        Schema::create('guestbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name', 80);
            $table->text('message');
            $table->char('locale', 2)->default('vi');
            $table->enum('status', ['pending', 'approved', 'hidden'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();                 // kham_pha | su_kien | khoa_hoc
            $table->string('slug', 80)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('blog_categories')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 120);
            $table->unique(['category_id', 'locale']);
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('blog_categories')->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug', 150)->unique();
            $table->string('cover_path', 500)->nullable();        // đường dẫn disk public hoặc URL ngoài
            $table->json('cover_meta')->nullable();               // {source_url, author, license}
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        Schema::create('blog_post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('title', 255);
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->unique(['post_id', 'locale']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 20)->nullable();
            $table->string('subject', 190);
            $table->text('body');
            $table->enum('status', ['new', 'read', 'replied'])->default('new');
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->text('reply_note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_chk CHECK (rating BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('blog_post_translations');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_category_translations');
        Schema::dropIfExists('blog_categories');
        Schema::dropIfExists('guestbook_entries');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('reviews');
    }
};
