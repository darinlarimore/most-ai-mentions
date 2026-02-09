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
            $table->string('annotated_screenshot_path')->nullable()->after('screenshot_path');
            $table->dropColumn(['crawled_html', 'annotated_html']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->dropColumn('annotated_screenshot_path');
            $table->longText('crawled_html')->nullable()->after('rainbow_border_count');
            $table->longText('annotated_html')->nullable()->after('crawled_html');
        });
    }
};
