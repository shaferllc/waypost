<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('value_level', 16)->nullable()->after('planning_status');
            $table->string('effort_level', 16)->nullable()->after('value_level');
            $table->string('eisenhower_quadrant', 24)->nullable()->after('effort_level');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['value_level', 'effort_level', 'eisenhower_quadrant']);
        });
    }
};
