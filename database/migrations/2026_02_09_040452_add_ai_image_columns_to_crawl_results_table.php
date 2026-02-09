<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->unsignedInteger('ai_image_count')->default(0)->after('rainbow_border_count');
            $table->unsignedInteger('ai_image_score')->default(0)->after('ai_image_count');
            $table->json('ai_image_details')->nullable()->after('ai_image_score');
            $table->integer('ai_image_hype_bonus')->default(0)->after('ai_image_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->dropColumn(['ai_image_count', 'ai_image_score', 'ai_image_details', 'ai_image_hype_bonus']);
        });
    }
};
