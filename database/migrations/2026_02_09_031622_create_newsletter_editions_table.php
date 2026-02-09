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
        Schema::create('newsletter_editions', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('content')->nullable();
            $table->json('top_sites')->nullable();
            $table->integer('subscriber_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('week_start')->nullable();
            $table->timestamp('week_end')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_editions');
    }
};
