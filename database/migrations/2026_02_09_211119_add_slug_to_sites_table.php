<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('domain');
        });

        // Populate slugs for existing sites
        $sites = DB::table('sites')->select('id', 'domain')->get();
        foreach ($sites as $site) {
            $domain = preg_replace('/^www\./', '', $site->domain);
            DB::table('sites')->where('id', $site->id)->update([
                'slug' => Str::slug(str_replace('.', '-', $domain)),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
