<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('standard_term_id')
                ->nullable()
                ->after('freight_terms')
                ->constrained('standard_terms');

            $table->longText('terms_text')
                ->nullable()
                ->after('standard_term_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['standard_term_id']);
            $table->dropColumn(['standard_term_id', 'terms_text']);
        });
    }
};
