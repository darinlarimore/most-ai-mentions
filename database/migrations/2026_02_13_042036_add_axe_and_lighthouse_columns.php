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
            // axe-core columns
            $table->unsignedInteger('axe_violations_count')->nullable();
            $table->unsignedInteger('axe_passes_count')->nullable();
            $table->json('axe_violations_summary')->nullable();

            // Lighthouse columns (scores 0-100)
            $table->unsignedTinyInteger('lighthouse_performance')->nullable();
            $table->unsignedTinyInteger('lighthouse_accessibility')->nullable();
            $table->unsignedTinyInteger('lighthouse_best_practices')->nullable();
            $table->unsignedTinyInteger('lighthouse_seo')->nullable();
        });

        Schema::table('score_histories', function (Blueprint $table) {
            $table->unsignedTinyInteger('lighthouse_performance')->nullable();
            $table->unsignedTinyInteger('lighthouse_accessibility')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->dropColumn([
                'axe_violations_count',
                'axe_passes_count',
                'axe_violations_summary',
                'lighthouse_performance',
                'lighthouse_accessibility',
                'lighthouse_best_practices',
                'lighthouse_seo',
            ]);
        });

        Schema::table('score_histories', function (Blueprint $table) {
            $table->dropColumn([
                'lighthouse_performance',
                'lighthouse_accessibility',
            ]);
        });
    }
};
