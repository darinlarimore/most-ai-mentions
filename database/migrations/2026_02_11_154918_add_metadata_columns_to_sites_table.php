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
        Schema::table('sites', function (Blueprint $table) {
            $table->json('tech_stack')->nullable()->after('category');
            $table->string('server_ip', 45)->nullable()->after('tech_stack');
            $table->string('server_software')->nullable()->after('server_ip');
            $table->string('tls_issuer')->nullable()->after('server_software');
            $table->string('page_title')->nullable()->after('tls_issuer');
            $table->text('meta_description')->nullable()->after('page_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'tech_stack',
                'server_ip',
                'server_software',
                'tls_issuer',
                'page_title',
                'meta_description',
            ]);
        });
    }
};
