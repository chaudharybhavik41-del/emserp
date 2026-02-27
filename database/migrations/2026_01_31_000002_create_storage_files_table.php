<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storage_files')) {
            return;
        }

        Schema::create('storage_files', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('storage_folder_id')->index();

            $table->string('original_name', 255);
            $table->string('stored_name', 255);

            // Use local disk by default (storage/app), which is private by default in Laravel
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);

            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();

            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('storage_folder_id')->references('id')->on('storage_folders')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['storage_folder_id', 'stored_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_files');
    }
};
