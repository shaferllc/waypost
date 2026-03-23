<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(2)->after('position');
            $table->date('due_date')->nullable()->after('priority');
            $table->json('tags')->nullable()->after('due_date');
            $table->foreignId('theme_id')->nullable()->after('tags')->constrained('roadmap_themes')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('theme_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('theme_id');
            $table->dropColumn(['tags', 'due_date', 'priority']);
        });
    }
};
