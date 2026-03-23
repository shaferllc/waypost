<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('target_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestamps();

            $table->unique(['source_task_id', 'target_task_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_links');
    }
};
