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
        Schema::create('crawl_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->integer('total_score')->default(0);
            $table->integer('ai_mention_count')->default(0);
            $table->json('mention_details')->nullable(); // [{text, font_size, has_animation, has_glow, context}]
            $table->integer('mention_score')->default(0);
            $table->integer('font_size_score')->default(0);
            $table->integer('animation_score')->default(0);
            $table->integer('visual_effects_score')->default(0);
            $table->integer('lighthouse_performance')->nullable(); // 0-100
            $table->integer('lighthouse_accessibility')->nullable(); // 0-100
            $table->integer('lighthouse_perf_bonus')->default(0);
            $table->integer('lighthouse_a11y_bonus')->default(0);
            $table->integer('animation_count')->default(0);
            $table->integer('glow_effect_count')->default(0);
            $table->integer('rainbow_border_count')->default(0);
            $table->text('crawled_html')->nullable();
            $table->json('computed_styles')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('status')->default('completed'); // completed, failed, partial
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crawl_results');
    }
};
