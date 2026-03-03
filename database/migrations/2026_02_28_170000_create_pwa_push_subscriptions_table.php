<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pwa_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64);
            $table->text('public_key')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('content_encoding', 100)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'endpoint_hash'], 'pwa_push_subscriptions_user_endpoint_unique');
            $table->index(['user_id', 'last_seen_at'], 'pwa_push_subscriptions_user_seen_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pwa_push_subscriptions');
    }
};
