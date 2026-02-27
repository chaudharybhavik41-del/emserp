<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            // Ensure 1-to-1 integrity: one employee can link to only one user, and vice-versa.
            // MySQL allows multiple NULLs in a unique index, so employees without users are fine.
            $table->unique('user_id', 'hr_employees_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropUnique('hr_employees_user_id_unique');
        });
    }
};
