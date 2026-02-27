<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrmQuotationsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_quotations')) {
            return;
        }

        Schema::create('crm_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('code'); // base code, unique with revision_no
            $table->unsignedInteger('revision_no')->default(0);

            $table->foreignId('lead_id')->constrained('crm_leads');
            $table->foreignId('party_id')->nullable()->constrained('parties');

            $table->string('project_name');

            $table->string('status', 50)->default('draft'); // draft/sent/accepted/rejected/...

            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);

            $table->date('valid_till')->nullable();
            $table->text('revision_reason')->nullable();

            $table->text('payment_terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('other_terms')->nullable();

            $table->dateTime('sent_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotations');
    }
}
