<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_documents')) {
            return;
        }

        Schema::create('support_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('support_folder_id')
                ->nullable()
                ->constrained('support_folders')
                ->nullOnDelete();

            $table->string('title', 255);
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['support_folder_id', 'is_active']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_documents');
    }
};
