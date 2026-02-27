<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'standard_term_id')) {
                $table->foreignId('standard_term_id')
                    ->nullable()
                    ->constrained('standard_terms')
                    ->nullOnDelete()
                    ->after('other_terms');
            }

            if (! Schema::hasColumn('crm_quotations', 'terms_text')) {
                $table->longText('terms_text')
                    ->nullable()
                    ->after('standard_term_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (Schema::hasColumn('crm_quotations', 'terms_text')) {
                $table->dropColumn('terms_text');
            }

            if (Schema::hasColumn('crm_quotations', 'standard_term_id')) {
                $table->dropConstrainedForeignId('standard_term_id');
            }
        });
    }
};
