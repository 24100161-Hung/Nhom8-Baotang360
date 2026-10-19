<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gói vé, lịch ngày tham quan, giỏ hàng, đơn hàng, vé, hội viên và giao dịch thanh toán.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();                 // regular | premium | …
            $table->enum('package_type', ['single', 'membership']);
            $table->unsignedInteger('price_vnd');
            $table->unsignedSmallInteger('validity_days')->default(1);
            $table->json('benefits')->nullable();                 // {vi: [..], en: [..]}
            $table->unsignedTinyInteger('max_per_order')->default(10);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_package_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('ticket_packages')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unique(['package_id', 'locale']);
        });

        Schema::create('visit_days', function (Blueprint $table) {
            $table->id();
            $table->date('visit_date')->unique();
            $table->unsignedInteger('capacity')->default(300);
            $table->unsignedInteger('sold')->default(0);
            $table->enum('status', ['open', 'closed', 'sold_out'])->default('open');
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('session_key', 64)->nullable();
            $table->enum('status', ['active', 'converted', 'abandoned'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['session_key', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('ticket_packages')->restrictOnDelete();
            $table->date('visit_date')->nullable();               // null với hội viên
            $table->unsignedTinyInteger('quantity');
            $table->unsignedInteger('unit_price_vnd');
            $table->timestamps();
            $table->unique(['cart_id', 'package_id', 'visit_date']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->char('code', 13)->unique();                   // OD- + 10 ký tự ngẫu nhiên
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['pending', 'paid', 'cancelled', 'expired', 'refunded'])->default('pending');
            $table->unsignedInteger('subtotal_vnd');
            $table->unsignedInteger('discount_vnd')->default(0);
            $table->unsignedInteger('total_vnd');
            $table->enum('payment_method', ['card', 'bank', 'ewallet']);
            $table->string('contact_name', 120);
            $table->string('contact_email', 190);
            $table->string('contact_phone', 20);
            $table->string('invoice_address', 255)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'hold_expires_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('ticket_packages')->restrictOnDelete();
            $table->date('visit_date')->nullable();
            $table->unsignedTinyInteger('quantity');
            $table->unsignedInteger('unit_price_vnd');
            $table->unsignedInteger('line_total_vnd');
            $table->timestamps();
            $table->index('visit_date');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->char('code', 13)->unique();                   // TK- + 10 ký tự ngẫu nhiên
            $table->string('qr_token', 128)->unique();            // payload ký HMAC
            $table->string('holder_name', 120);
            $table->date('visit_date')->nullable();
            $table->enum('status', ['valid', 'used', 'void'])->default('valid');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('ticket_packages')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamps();
            $table->index(['user_id', 'status', 'ends_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 30)->default('sandbox');
            $table->enum('method', ['card', 'bank', 'ewallet']);
            $table->string('txn_ref', 40)->unique();
            $table->unsignedInteger('amount_vnd');
            $table->char('currency', 3)->default('VND');
            $table->enum('status', ['initiated', 'succeeded', 'failed', 'cancelled', 'refunded'])->default('initiated');
            $table->json('gateway_payload')->nullable();
            $table->string('signature', 128)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_qty_chk CHECK (quantity >= 1)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_qty_chk CHECK (quantity >= 1)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_chk CHECK (total_vnd = subtotal_vnd - discount_vnd)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('visit_days');
        Schema::dropIfExists('ticket_package_translations');
        Schema::dropIfExists('ticket_packages');
    }
};
