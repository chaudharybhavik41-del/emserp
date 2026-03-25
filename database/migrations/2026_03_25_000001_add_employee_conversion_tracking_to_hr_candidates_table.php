<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_candidates', function (Blueprint $table) {
            $table->foreignId('converted_hr_employee_id')->nullable()->after('resume_mime_type')
                ->constrained('hr_employees')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_hr_employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('hr_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_hr_employee_id');
            $table->dropColumn('converted_at');
        });
    }
};
