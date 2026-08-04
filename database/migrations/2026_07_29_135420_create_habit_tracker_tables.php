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
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'slug']);
            $table->index(['team_id', 'is_active', 'sort_order']);
        });

        Schema::create('daily_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('mood_score')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Also serves every "this user, this team, this date range" lookup,
            // which is the only access pattern the tracker uses.
            $table->unique(['team_id', 'user_id', 'date']);
        });

        Schema::create('habit_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_check_in_id')->constrained()->cascadeOnDelete();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->unique(['daily_check_in_id', 'habit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('habit_completions');
        Schema::dropIfExists('daily_check_ins');
        Schema::dropIfExists('habits');
    }
};
