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
            $table->unsignedInteger('pages_crawled')->default(0)->after('ai_mention_count');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->dropColumn('pages_crawled');
        });
    }
};
