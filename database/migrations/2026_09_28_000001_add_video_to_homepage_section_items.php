<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_section_items', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('mobile_image_external_url');
            $table->string('video_external_url', 2048)->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_section_items', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'video_external_url']);
        });
    }
};
