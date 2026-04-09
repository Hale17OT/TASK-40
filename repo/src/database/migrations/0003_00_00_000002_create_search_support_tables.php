<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trending_searches', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('pinned_by')->nullable();
            $table->timestamps();

            $table->foreign('pinned_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('banned_words', function (Blueprint $table) {
            $table->id();
            $table->string('word', 100)->unique();
            $table->timestamps();
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);
            $table->decimal('rate', 5, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['category', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('banned_words');
        Schema::dropIfExists('trending_searches');
    }
};
