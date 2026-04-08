<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('name');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->unique()->after('role');
            }
            if (!Schema::hasColumn('users', 'pin_hash')) {
                $table->string('pin_hash')->nullable()->after('phone');
            }

            // Keep email/password columns if they exist (skeleton), but app uses phone+pin.
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pin_hash')) $table->dropColumn('pin_hash');
            if (Schema::hasColumn('users', 'phone')) $table->dropUnique(['phone']);
            if (Schema::hasColumn('users', 'phone')) $table->dropColumn('phone');
            if (Schema::hasColumn('users', 'role')) $table->dropColumn('role');
        });
    }
};

