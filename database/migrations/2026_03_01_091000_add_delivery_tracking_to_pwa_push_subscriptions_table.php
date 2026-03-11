<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pwa_push_subscriptions', function (Blueprint $table) {
            $table->timestamp('last_push_attempt_at')->nullable()->after('last_seen_at');
            $table->timestamp('last_push_success_at')->nullable()->after('last_push_attempt_at');
            $table->string('last_push_status', 32)->nullable()->after('last_push_success_at');
            $table->text('last_push_error')->nullable()->after('last_push_status');
            $table->unsignedInteger('push_attempt_count')->default(0)->after('last_push_error');

            $table->index(['last_push_status', 'last_push_attempt_at'], 'pwa_push_subscriptions_status_attempt_index');
        });
    }

    public function down(): void
    {
        Schema::table('pwa_push_subscriptions', function (Blueprint $table) {
            $table->dropIndex('pwa_push_subscriptions_status_attempt_index');
            $table->dropColumn([
                'last_push_attempt_at',
                'last_push_success_at',
                'last_push_status',
                'last_push_error',
                'push_attempt_count',
            ]);
        });
    }
};
