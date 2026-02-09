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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('url')->unique();
            $table->string('domain')->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->integer('hype_score')->default(0);
            $table->float('user_rating_avg')->default(0);
            $table->integer('user_rating_count')->default(0);
            $table->integer('crawl_count')->default(0);
            $table->string('status')->default('pending'); // pending, queued, crawling, completed, failed
            $table->timestamp('last_crawled_at')->nullable();
            $table->integer('cooldown_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
