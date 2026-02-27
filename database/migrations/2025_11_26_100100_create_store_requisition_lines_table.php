<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_requisition_lines')) {
            return;
        }

        Schema::create('store_requisition_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('store_requisition_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('uom_id');

            $table->string('description', 255)->nullable();

            $table->decimal('required_qty', 15, 3);
            $table->decimal('issued_qty', 15, 3)->default(0);

            $table->string('preferred_make', 100)->nullable();
            $table->string('segment_reference', 100)->nullable(); // e.g. "36m Segment A"
            $table->string('remarks', 255)->nullable();

            $table->timestamps();

            $table->index('store_requisition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_requisition_lines');
    }
};
