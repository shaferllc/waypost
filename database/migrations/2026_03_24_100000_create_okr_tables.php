<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okr_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('okr_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('okr_goal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('okr_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('okr_objective_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okr_key_results');
        Schema::dropIfExists('okr_objectives');
        Schema::dropIfExists('okr_goals');
    }
};
