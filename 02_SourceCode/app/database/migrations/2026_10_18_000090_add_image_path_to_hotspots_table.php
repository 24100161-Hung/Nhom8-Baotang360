<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ảnh minh họa tùy chọn cho hotspot info (panel thông tin trong trình xem 360°)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspots', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('hotspots', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
