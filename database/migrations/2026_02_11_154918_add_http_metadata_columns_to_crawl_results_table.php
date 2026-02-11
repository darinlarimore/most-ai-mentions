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
            $table->json('redirect_chain')->nullable()->after('annotated_screenshot_path');
            $table->string('final_url')->nullable()->after('redirect_chain');
            $table->unsignedInteger('response_time_ms')->nullable()->after('final_url');
            $table->unsignedInteger('html_size_bytes')->nullable()->after('response_time_ms');
            $table->json('detected_tech_stack')->nullable()->after('html_size_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->dropColumn([
                'redirect_chain',
                'final_url',
                'response_time_ms',
                'html_size_bytes',
                'detected_tech_stack',
            ]);
        });
    }
};
