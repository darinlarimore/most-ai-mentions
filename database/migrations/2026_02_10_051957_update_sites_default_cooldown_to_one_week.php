<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->integer('cooldown_hours')->default(168)->change();
        });

        DB::table('sites')->where('cooldown_hours', 24)->update(['cooldown_hours' => 168]);
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->integer('cooldown_hours')->default(24)->change();
        });

        DB::table('sites')->where('cooldown_hours', 168)->update(['cooldown_hours' => 24]);
    }
};
