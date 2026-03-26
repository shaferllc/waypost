<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_login_code_enabled')) {
                $table->boolean('email_login_code_enabled')->default(false);
            }
            if (! Schema::hasColumn('users', 'email_login_magic_link_enabled')) {
                $table->boolean('email_login_magic_link_enabled')->default(false);
            }
        });

        if (Schema::hasColumn('users', 'email_code_login_enabled')) {
            DB::table('users')->where('email_code_login_enabled', true)->update([
                'email_login_code_enabled' => true,
                'email_login_magic_link_enabled' => true,
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('email_code_login_enabled');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_code_login_enabled')) {
                $table->boolean('email_code_login_enabled')->default(false);
            }
        });

        DB::table('users')
            ->where(function ($query): void {
                $query->where('email_login_code_enabled', true)
                    ->orWhere('email_login_magic_link_enabled', true);
            })
            ->update(['email_code_login_enabled' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_login_code_enabled',
                'email_login_magic_link_enabled',
            ]);
        });
    }
};
