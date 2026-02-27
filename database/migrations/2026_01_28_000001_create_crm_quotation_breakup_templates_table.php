<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_quotation_breakup_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->longText('content');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // If the earlier patch stored breakup templates under Standard Terms (module=sales, sub_module=quotation_breakup),
        // migrate them here and DELETE them from standard_terms to avoid exposing internal costing templates.
        if (Schema::hasTable('standard_terms')) {
            $rows = DB::table('standard_terms')
                ->where('module', 'sales')
                ->where('sub_module', 'quotation_breakup')
                ->get();

            foreach ($rows as $row) {
                DB::table('crm_quotation_breakup_templates')->insertOrIgnore([
                    'code'       => $row->code,
                    'name'       => $row->name,
                    'is_active'  => (bool) ($row->is_active ?? true),
                    'is_default' => (bool) ($row->is_default ?? false),
                    'sort_order' => (int) ($row->sort_order ?? 0),
                    'content'    => (string) ($row->content ?? ''),
                    'created_by' => $row->created_by ?? null,
                    'updated_by' => $row->updated_by ?? null,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            DB::table('standard_terms')
                ->where('module', 'sales')
                ->where('sub_module', 'quotation_breakup')
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotation_breakup_templates');
    }
};
