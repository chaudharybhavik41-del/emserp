<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'opening_balance_date')) {
                $table->date('opening_balance_date')
                    ->nullable()
                    ->after('opening_balance_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'opening_balance_date')) {
                $table->dropColumn('opening_balance_date');
            }
        });
    }
};
