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
            $table->dropColumn([
                'lighthouse_performance',
                'lighthouse_accessibility',
                'lighthouse_perf_bonus',
                'lighthouse_a11y_bonus',
                'ai_image_count',
                'ai_image_score',
                'ai_image_details',
                'ai_image_hype_bonus',
            ]);
        });

        Schema::table('score_histories', function (Blueprint $table) {
            $table->dropColumn([
                'lighthouse_performance',
                'lighthouse_accessibility',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->integer('lighthouse_performance')->nullable();
            $table->integer('lighthouse_accessibility')->nullable();
            $table->integer('lighthouse_perf_bonus')->default(0);
            $table->integer('lighthouse_a11y_bonus')->default(0);
            $table->unsignedInteger('ai_image_count')->default(0);
            $table->unsignedInteger('ai_image_score')->default(0);
            $table->json('ai_image_details')->nullable();
            $table->integer('ai_image_hype_bonus')->default(0);
        });

        Schema::table('score_histories', function (Blueprint $table) {
            $table->integer('lighthouse_performance')->nullable();
            $table->integer('lighthouse_accessibility')->nullable();
        });
    }
};
