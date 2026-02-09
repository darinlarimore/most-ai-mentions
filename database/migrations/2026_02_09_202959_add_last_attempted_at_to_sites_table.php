<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('last_attempted_at')->nullable()->after('last_crawled_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('last_attempted_at');
        });
    }
};
