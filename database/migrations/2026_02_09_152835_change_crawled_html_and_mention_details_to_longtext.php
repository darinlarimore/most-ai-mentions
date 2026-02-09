<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->longText('crawled_html')->nullable()->change();
            $table->longText('mention_details')->nullable()->change();
            $table->longText('computed_styles')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('crawl_results', function (Blueprint $table) {
            $table->text('crawled_html')->nullable()->change();
            $table->text('mention_details')->nullable()->change();
            $table->text('computed_styles')->nullable()->change();
        });
    }
};
