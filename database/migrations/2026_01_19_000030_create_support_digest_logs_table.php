<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_digest_logs')) {
            return;
        }

        Schema::create('support_digest_logs', function (Blueprint $table) {
            $table->id();
            $table->date('digest_date');
            $table->string('status', 20)->default('sent'); // sent / failed
            $table->timestamp('sent_at')->nullable();

            $table->json('recipients')->nullable();
            $table->json('summary')->nullable();
            $table->text('error')->nullable();

            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['digest_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_digest_logs');
    }
};
