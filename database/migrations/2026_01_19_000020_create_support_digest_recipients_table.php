<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_digest_recipients')) {
            return;
        }

        Schema::create('support_digest_recipients', function (Blueprint $table) {
            $table->id();

            // Either link to an existing user, or store an external email.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('email', 255)->nullable();

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique('email');
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_digest_recipients');
    }
};
