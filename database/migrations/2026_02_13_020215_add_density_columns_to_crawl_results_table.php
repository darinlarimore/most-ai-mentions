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
            $table->unsignedInteger('total_word_count')->nullable()->after('ai_mention_count');
            $table->decimal('ai_density_percent', 5, 2)->nullable()->after('total_word_count');
            $table->integer('density_score')->default(0)->after('ai_density_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->dropColumn(['total_word_count', 'ai_density_percent', 'density_score']);
        });
    }
};
