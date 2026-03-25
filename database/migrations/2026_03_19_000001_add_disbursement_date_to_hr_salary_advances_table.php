<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_salary_advances', function (Blueprint $table) {
            $table->date('disbursement_date')->nullable()->after('disbursed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('hr_salary_advances', function (Blueprint $table) {
            $table->dropColumn('disbursement_date');
        });
    }
};
