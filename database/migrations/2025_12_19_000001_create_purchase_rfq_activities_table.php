<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_rfq_activities')) {
            return;
        }

        Schema::create('purchase_rfq_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('purchase_rfq_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 50);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['purchase_rfq_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_rfq_activities');
    }
};
