<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttachmentsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attachments')) {
            return;
        }

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');

            $table->string('category', 50)->nullable(); // e.g. 'party-doc'
            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes

            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id'], 'attachments_attachable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
}
