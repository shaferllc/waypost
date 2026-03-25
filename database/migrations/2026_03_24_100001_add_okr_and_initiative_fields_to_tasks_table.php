<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('okr_objective_id')->nullable()->after('theme_id')->constrained('okr_objectives')->nullOnDelete();
            $table->date('starts_at')->nullable()->after('okr_objective_id');
            $table->date('ends_at')->nullable()->after('starts_at');
            $table->string('planning_status', 32)->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('okr_objective_id');
            $table->dropColumn(['starts_at', 'ends_at', 'planning_status']);
        });
    }
};
