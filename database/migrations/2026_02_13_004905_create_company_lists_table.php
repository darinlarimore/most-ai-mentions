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
        Schema::create('company_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('source_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('company_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_list_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('domain');
            $table->integer('rank')->nullable();
            $table->timestamps();

            $table->unique(['company_list_id', 'domain']);
            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_list_entries');
        Schema::dropIfExists('company_lists');
    }
};
